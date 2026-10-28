<?php

namespace App\Http\Controllers\Store\Admin;

use App\Domain\Store\Invoicing\EInvoiceService;
use App\Domain\Store\Invoicing\InvoiceMath;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreEInvoice;
use App\Models\Store\StoreOrder;
use App\Models\Store\StoreSetting;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Faturalar: ödenen siparişlerin faturası burada elle hazırlanır ve gönderilir (otomatik kesilmez).
 * Sekmeler: fatura bekleyen, iade faturası bekleyen, taslaklar, sorunlu, gönderilen, iptal.
 */
class InvoiceController extends Controller
{
    public const TABS = [
        'bekleyen' => 'Fatura bekleyen',
        'iade' => 'İade faturası bekleyen',
        'taslak' => 'Taslaklar',
        'sorunlu' => 'Sorunlu',
        'gonderilen' => 'Gönderilen',
        'iptal' => 'İptal',
    ];

    public function __construct(private EInvoiceService $invoices) {}

    public function index(Request $request): View
    {
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'bekleyen';
        $q = trim((string) $request->query('q'));
        $search = function ($query, string $orderPath = '') use ($q) {
            if ($q === '') {
                return $query;
            }
            $like = '%'.addcslashes($q, '%_\\').'%';
            $columns = fn ($w) => $w->where('order_no', 'like', $like)->orWhere('billing_name', 'like', $like)->orWhere('shipping_name', 'like', $like)->orWhere('email', 'like', $like);

            return $orderPath === '' ? $query->where($columns) : $query->where(fn ($w) => $w->whereHas('order', $columns)->orWhere('invoice_no', 'like', $like)->orWhere('buyer_name', 'like', $like));
        };

        $documents = fn () => StoreEInvoice::query()->with('order');
        $counts = [
            'bekleyen' => EInvoiceService::awaitingSale()->count(),
            'iade' => EInvoiceService::awaitingRefund()->count(),
            'taslak' => StoreEInvoice::where('status', 'draft')->whereNull('error_message')->count(),
            'sorunlu' => StoreEInvoice::where(fn ($w) => $w->whereIn('status', ['unknown', 'sending'])->orWhere(fn ($d) => $d->where('status', 'draft')->whereNotNull('error_message')))->count(),
        ];

        $rows = match ($tab) {
            'bekleyen' => $search(EInvoiceService::awaitingSale())->latest('id'),
            'iade' => $search(EInvoiceService::awaitingRefund())->with('invoice')->latest('id'),
            'taslak' => $search($documents()->where('status', 'draft')->whereNull('error_message'), 'order')->latest('id'),
            'sorunlu' => $search($documents()->where(fn ($w) => $w->whereIn('status', ['unknown', 'sending'])->orWhere(fn ($d) => $d->where('status', 'draft')->whereNotNull('error_message'))), 'order')->latest('id'),
            'gonderilen' => $search($documents()->where('status', 'sent'), 'order')->latest('sent_at'),
            'iptal' => $search($documents()->where('status', 'cancelled'), 'order')->latest('cancelled_at'),
        };

        return view('store.admin.invoices.index', [
            'tab' => $tab, 'q' => $q, 'counts' => $counts,
            'rows' => $rows->paginate(30)->withQueryString(),
            'driver' => StoreSetting::get('invoice_driver'),
        ]);
    }

    public function createSale(StoreOrder $storeOrder): RedirectResponse
    {
        return $this->attempt(fn () => redirect()->route('store_admin.invoices.edit', $this->invoices->createSale($storeOrder)));
    }

    public function createRefund(Request $request, StoreOrder $storeOrder): RedirectResponse
    {
        return $this->attempt(fn () => redirect()->route('store_admin.invoices.edit', $this->invoices->createRefund($storeOrder, $request->integer('return_id') ?: null)));
    }

    public function createMany(Request $request): RedirectResponse
    {
        $count = $this->invoices->createMany(array_map('intval', (array) $request->input('orders', [])));

        return redirect()->route('store_admin.invoices.index', ['tab' => 'taslak'])->with('status', "{$count} sipariş için fatura taslağı hazırlandı. Kontrol edip gönderebilirsiniz.");
    }

    public function edit(StoreEInvoice $storeEInvoice): View
    {
        $storeEInvoice->load(['lines', 'order.items', 'order.invoice', 'return']);

        return view('store.admin.invoices.edit', [
            'invoice' => $storeEInvoice,
            'order' => $storeEInvoice->order,
            'vatRates' => array_combine(InvoiceMath::VAT_RATES, array_map(fn ($r) => "%{$r}", InvoiceMath::VAT_RATES)),
            'driver' => StoreSetting::get('invoice_driver'),
        ]);
    }

    /** "Kaydet" veya "Kaydet ve gönder" (action=send). */
    public function update(Request $request, StoreEInvoice $storeEInvoice): RedirectResponse
    {
        try {
            $this->invoices->update($storeEInvoice, $request->all());
            if ($request->input('action') !== 'send') {
                return back()->with('status', 'Taslak kaydedildi.');
            }
            $sent = $this->invoices->send($storeEInvoice->fresh(), $request->boolean('confirm_difference'));

            return redirect()->route('store_admin.invoices.edit', $sent)->with('status', "Fatura kesildi: {$sent->invoice_no}.");
        } catch (DomainException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()])->withInput();
        }
    }

    public function sendMany(Request $request): RedirectResponse
    {
        $result = $this->invoices->sendMany(array_map('intval', (array) $request->input('invoices', [])));
        $redirect = redirect()->route('store_admin.invoices.index', ['tab' => $result['failed'] ? 'sorunlu' : 'gonderilen']);
        $message = count($result['sent']).' fatura kesildi.';
        if ($result['failed']) {
            $lines = collect($result['failed'])->map(fn ($m, $no) => "{$no}: {$m}")->take(10)->implode(' · ');

            return redirect()->route('store_admin.invoices.index', ['tab' => 'taslak'])->with('status', $message)->withErrors(['invoice' => count($result['failed']).' fatura gönderilemedi — '.$lines]);
        }

        return $redirect->with('status', $message);
    }

    public function check(StoreEInvoice $storeEInvoice): RedirectResponse
    {
        return $this->attempt(function () use ($storeEInvoice) {
            $invoice = $this->invoices->check($storeEInvoice);

            return back()->with('status', $invoice->status === 'sent' ? "Fatura kesilmiş: {$invoice->invoice_no}." : 'Sağlayıcıda bulunamadı; fatura taslağa alındı.');
        });
    }

    public function release(StoreEInvoice $storeEInvoice): RedirectResponse
    {
        return $this->attempt(function () use ($storeEInvoice) {
            $this->invoices->release($storeEInvoice);

            return back()->with('status', 'Fatura taslağa alındı.');
        });
    }

    public function cancel(Request $request, StoreEInvoice $storeEInvoice): RedirectResponse
    {
        return $this->attempt(function () use ($request, $storeEInvoice) {
            $this->invoices->cancel($storeEInvoice, (string) $request->input('cancel_reason'));

            return back()->with('status', 'Fatura iptal edildi. Gerekirse yeni taslak hazırlayabilirsiniz.');
        });
    }

    public function refetchPdf(StoreEInvoice $storeEInvoice): RedirectResponse
    {
        return $this->attempt(function () use ($storeEInvoice) {
            $this->invoices->refetchPdf($storeEInvoice);

            return back()->with('status', 'PDF alındı.');
        });
    }

    public function destroy(StoreEInvoice $storeEInvoice): RedirectResponse
    {
        return $this->attempt(function () use ($storeEInvoice) {
            $order = $storeEInvoice->order;
            $this->invoices->deleteDraft($storeEInvoice);

            return redirect()->route('store_admin.orders.show', $order)->with('status', 'Fatura taslağı silindi.');
        });
    }

    public function preview(StoreEInvoice $storeEInvoice): Response
    {
        return response($this->invoices->preview($storeEInvoice), 200, [
            'Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="Onizleme-'.$storeEInvoice->order->order_no.'.pdf"',
        ]);
    }

    public function pdf(StoreEInvoice $storeEInvoice): StreamedResponse
    {
        abort_unless($storeEInvoice->pdf_path && Storage::disk('local')->exists($storeEInvoice->pdf_path), 404);

        return Storage::disk('local')->download($storeEInvoice->pdf_path, "Fatura-{$storeEInvoice->invoice_no}.pdf", [
            'Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function attempt(callable $action): RedirectResponse
    {
        try {
            return $action();
        } catch (DomainException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        }
    }
}
