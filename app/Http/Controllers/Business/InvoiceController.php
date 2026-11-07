<?php

namespace App\Http\Controllers\Business;

use App\Domain\Invoicing\InvoiceService;
use App\Domain\Invoicing\InvoiceSettings;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderReturn;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;

/**
 * Toptancı → Faturalar: bayi siparişlerinin satış ve iade faturaları (e-Arşiv / e-Fatura, Uyumsoft). İş kuralları
 * App\Domain\Invoicing\InvoiceService'tedir; burada yalnızca doğrulanır ve çağrılır. Kayıtlar işletme bağlamıyla
 * sınırlıdır (BelongsToBusiness): başka işletmenin faturası / siparişi bağlanamaz (404).
 */
class InvoiceController extends Controller
{
    public const TABS = [
        'bekleyen' => 'Fatura bekleyen',
        'iade' => 'İade bekleyen',
        'taslak' => 'Taslak',
        'sorunlu' => 'Sorunlu',
        'kesilen' => 'Kesilen',
        'iptal' => 'İptal / reddedilen',
    ];

    public function index(Request $request, InvoiceSettings $settings): View
    {
        $tab = is_string($request->query('tab')) && array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'bekleyen';
        $q = is_string($request->query('q')) ? mb_substr(trim($request->query('q')), 0, 40) : '';

        $byTab = fn (string $t) => match ($t) {
            'taslak' => Invoice::where('status', 'draft')->whereNull('error_message'),
            'sorunlu' => Invoice::where(fn ($w) => $w->where('status', 'draft')->whereNotNull('error_message')->orWhereIn('status', Invoice::UNSETTLED)),
            'kesilen' => Invoice::where('status', 'sent'),
            'iptal' => Invoice::whereIn('status', Invoice::CLOSED),
            default => null,
        };

        $orders = null;
        $invoices = null;
        $refunds = null;
        if ($tab === 'bekleyen') {
            $orders = InvoiceService::awaitingSale()->with('relationship.dealer')
                ->when($q !== '', fn ($w) => $w->where('order_no', 'like', '%'.addcslashes($q, '%_\\').'%'))
                ->orderByDesc('id')->paginate(30)->withQueryString();
        } elseif ($tab === 'iade') {
            $refunds = InvoiceService::awaitingRefund();
        } else {
            $invoices = $byTab($tab)->with('order.relationship.dealer')
                ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('invoice_no', 'like', '%'.addcslashes($q, '%_\\').'%')
                    ->orWhereHas('order', fn ($o) => $o->where('order_no', 'like', '%'.addcslashes($q, '%_\\').'%'))))
                ->orderByDesc('id')->paginate(30)->withQueryString();
        }

        $counts = [
            'bekleyen' => InvoiceService::awaitingSale()->count(),
            'iade' => $refunds?->count() ?? InvoiceService::awaitingRefund()->count(),
        ];
        foreach (['taslak', 'sorunlu', 'kesilen', 'iptal'] as $t) {
            $counts[$t] = $byTab($t)->count();
        }

        $record = $settings->record();

        return view('business.invoices.index', [
            'tab' => $tab, 'q' => $q, 'counts' => $counts, 'orders' => $orders, 'invoices' => $invoices, 'refunds' => $refunds,
            'driver' => $record->driver, 'autoIssue' => (bool) $record->auto_issue, 'sellerProblem' => $settings->sellerProblem(),
        ]);
    }

    public function show(Invoice $invoice, InvoiceSettings $settings): View
    {
        $invoice->load(['lines', 'order.relationship.dealer', 'orderReturn']);

        return view('business.invoices.show', [
            'invoice' => $invoice,
            'sellerProblem' => $settings->sellerProblem(),
            'cancellable' => InvoiceService::cancellable($invoice),
            'cancelDeadline' => $invoice->status === 'sent' && $invoice->document_type === 'e_arsiv' ? InvoiceService::cancelDeadline($invoice) : null,
            'driver' => $settings->record()->driver,
        ]);
    }

    public function createSale(Order $order, InvoiceService $service): RedirectResponse
    {
        return $this->attempt(fn () => redirect()->route('business.invoices.show', $service->createSale($order)));
    }

    public function createMany(Request $request, InvoiceService $service): RedirectResponse
    {
        $ids = $request->validate(['orders' => ['required', 'array', 'max:100'], 'orders.*' => ['integer']], ['orders.required' => 'En az bir sipariş seçin.'])['orders'];
        $count = $service->createMany($ids);

        return redirect()->route('business.invoices.index', ['tab' => 'taslak'])->with('status', "{$count} fatura taslağı hazırlandı. Kontrol edip gönderin.");
    }

    public function createRefund(Request $request, Order $order, InvoiceService $service): RedirectResponse
    {
        $returnId = $request->validate(['order_return_id' => ['nullable', 'integer']])['order_return_id'] ?? null;
        $return = $returnId ? OrderReturn::where('order_id', $order->id)->findOrFail($returnId) : null;

        return $this->attempt(fn () => redirect()->route('business.invoices.show', $service->createRefund($order, $return)));
    }

    public function update(Request $request, Invoice $invoice, InvoiceService $service): RedirectResponse
    {
        return $this->attempt(function () use ($request, $invoice, $service) {
            $service->update($invoice, $request->only([
                'buyer_name', 'buyer_tax_number', 'buyer_tax_office', 'buyer_address', 'buyer_district', 'buyer_city',
                'buyer_email', 'buyer_phone', 'note', 'issue_date', 'notify_dealer',
            ]));

            return back()->with('status', 'Taslak kaydedildi.');
        });
    }

    public function send(Invoice $invoice, InvoiceService $service): RedirectResponse
    {
        return $this->attempt(function () use ($invoice, $service) {
            $invoice = $service->send($invoice);

            return redirect()->route('business.invoices.show', $invoice)->with('status', "Fatura kesildi: {$invoice->invoice_no}.");
        });
    }

    public function sendMany(Request $request, InvoiceService $service): RedirectResponse
    {
        $ids = $request->validate(['invoices' => ['required', 'array', 'max:50'], 'invoices.*' => ['integer']], ['invoices.required' => 'En az bir taslak seçin.'])['invoices'];
        $result = $service->sendMany($ids);
        $message = count($result['sent']).' fatura kesildi.';
        if ($result['failed']) {
            $message .= ' '.count($result['failed']).' taslak gönderilemedi; "Sorunlu" sekmesinde nedenini görebilirsiniz.';
        }

        return redirect()->route('business.invoices.index', ['tab' => $result['failed'] ? 'sorunlu' : 'kesilen'])->with('status', $message);
    }

    public function destroy(Invoice $invoice, InvoiceService $service): RedirectResponse
    {
        return $this->attempt(function () use ($invoice, $service) {
            $service->deleteDraft($invoice);

            return redirect()->route('business.invoices.index', ['tab' => 'taslak'])->with('status', 'Taslak silindi.');
        });
    }

    public function check(Invoice $invoice, InvoiceService $service): RedirectResponse
    {
        return $this->attempt(function () use ($invoice, $service) {
            $invoice = $service->check($invoice);

            return back()->with('status', $invoice->status === 'sent' ? "Fatura kesilmiş: {$invoice->invoice_no}." : 'Fatura sağlayıcıda bulunamadı; taslağa alındı.');
        });
    }

    public function release(Invoice $invoice, InvoiceService $service): RedirectResponse
    {
        return $this->attempt(function () use ($invoice, $service) {
            $service->release($invoice);

            return back()->with('status', 'Fatura taslağa alındı.');
        });
    }

    public function cancel(Request $request, Invoice $invoice, InvoiceService $service): RedirectResponse
    {
        return $this->attempt(function () use ($request, $invoice, $service) {
            $reason = $request->input('cancel_reason');
            $service->cancel($invoice, is_string($reason) ? $reason : '');

            return back()->with('status', $invoice->isManual()
                ? 'Fatura kayıtta iptal edildi. Uyumsoft portalında da iptal ettiğinizden emin olun.'
                : 'Fatura iptal edildi. Sipariş yeniden "Fatura bekleyen"e düştü.');
        });
    }

    public function status(Invoice $invoice, InvoiceService $service): RedirectResponse
    {
        return $this->attempt(function () use ($invoice, $service) {
            $state = $service->syncStatus($invoice);

            return back()->with('status', match ($state) {
                'ok' => 'Uyumsoft: fatura onaylandı.',
                'pending' => 'Uyumsoft: fatura işleniyor; biraz sonra yeniden sorgulayın.',
                'error' => 'Uyumsoft / GİB faturayı reddetti. Sipariş yeniden "Fatura bekleyen"e düştü.',
                'cancelled' => 'Fatura Uyumsoft portalında iptal edilmiş; kayıt güncellendi.',
                'missing' => 'Uyumsoft\'ta bu fatura bulunamadı.',
                default => 'Uyumsoft durumu: '.$invoice->fresh()->provider_status,
            });
        });
    }

    public function refetchPdf(Invoice $invoice, InvoiceService $service): RedirectResponse
    {
        return $this->attempt(function () use ($invoice, $service) {
            $service->refetchPdf($invoice);

            return back()->with('status', 'Fatura PDF\'i alındı.');
        });
    }

    public function pdf(Invoice $invoice): Response
    {
        abort_unless($invoice->status === 'sent' || $invoice->status === 'cancelled', 404);
        $pdf = InvoiceService::pdfContents($invoice);
        abort_if($pdf === null, 404);

        return $this->pdfResponse($pdf, ($invoice->invoice_no ?: 'fatura').'.pdf');
    }

    public function preview(Invoice $invoice, InvoiceService $service): Response
    {
        abort_unless($invoice->isDraft() || in_array($invoice->status, Invoice::UNSETTLED, true), 404);

        return $this->pdfResponse($service->preview($invoice), 'onizleme-'.$invoice->local_id.'.pdf');
    }

    public function attachManual(Request $request, Order $order, InvoiceService $service): RedirectResponse
    {
        $data = $request->validate(['kind' => ['required', 'in:sale,refund'], 'order_return_id' => ['nullable', 'integer']]);
        $return = isset($data['order_return_id']) ? OrderReturn::where('order_id', $order->id)->findOrFail($data['order_return_id']) : null;

        return $this->attempt(function () use ($request, $order, $data, $return, $service) {
            $pdf = $request->file('pdf');
            $invoice = $service->attachManual($order, $data['kind'], $return,
                $request->only(['invoice_no', 'issue_date', 'document_type', 'total', 'confirm_difference']), $pdf instanceof UploadedFile ? $pdf : null);

            return redirect()->route('business.invoices.show', $invoice)->with('status', "Elle kesilen fatura kaydedildi: {$invoice->invoice_no}.");
        });
    }

    private function pdfResponse(string $pdf, string $name): Response
    {
        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.preg_replace('/[^A-Za-z0-9._-]/', '', $name).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** İş kuralı hatası sayfada gösterilir. */
    private function attempt(callable $action): RedirectResponse
    {
        try {
            return $action();
        } catch (DomainException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()])->withInput();
        }
    }
}
