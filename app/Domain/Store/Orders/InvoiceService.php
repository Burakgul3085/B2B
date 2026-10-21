<?php

namespace App\Domain\Store\Orders;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\CurrentActor;
use App\Models\Store\StoreInvoice;
use App\Models\Store\StoreOrder;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sipariş faturası (elle): fatura numarası, tarihi ve PDF'i siparişe eklenir; müşteri sipariş sayfasından indirir.
 * PDF yerel diskte, web'den doğrudan erişilemeyen klasörde saklanır; yalnızca yetkili rotalardan indirilir.
 * e-Arşiv sağlayıcısı (Uyumsoft) bağlandığında aynı kayıt otomatik oluşturulacak.
 */
class InvoiceService
{
    public const MAX_KB = 5120;

    public function __construct(private AuditLogger $audit, private CurrentActor $actor, private OrderMailer $mailer) {}

    /** @throws DomainException|ValidationException */
    public function attach(StoreOrder $order, array $input, ?UploadedFile $file): StoreInvoice
    {
        $existing = $order->invoice()->first();
        $data = Validator::make($input + ['file' => $file], [
            'invoice_no' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-\/]+$/', 'unique:store_invoices,invoice_no'.($existing ? ','.$existing->id : '')],
            'issued_on' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.$order->created_at->toDateString()],
            'file' => [$existing ? 'nullable' : 'required', 'file', 'max:'.self::MAX_KB, 'mimes:pdf'],
        ], ['invoice_no.regex' => 'Fatura numarası yalnızca harf, rakam, - ve / içerebilir.'],
            ['invoice_no' => 'fatura numarası', 'issued_on' => 'fatura tarihi', 'file' => 'PDF dosyası'])->validate();

        if (in_array($order->status, ['pending_payment', 'payment_failed'], true)) {
            throw new DomainException('Ödemesi tamamlanmamış siparişe fatura eklenemez.');
        }
        if ($file && ! str_starts_with((string) file_get_contents($file->getRealPath(), false, null, 0, 5), '%PDF-')) {
            throw new DomainException('Yüklenen dosya geçerli bir PDF değil.');
        }

        $path = $file ? Storage::disk('local')->putFileAs("store/invoices/{$order->id}", $file, Str::random(40).'.pdf') : $existing?->file_path;
        $actor = $this->actor->get();
        $oldPath = $existing?->file_path;

        $invoice = DB::transaction(function () use ($order, $data, $path, $existing, $actor) {
            $values = ['invoice_no' => strtoupper($data['invoice_no']), 'issued_on' => $data['issued_on'], 'file_path' => $path, 'provider' => 'manual'];
            if ($existing) {
                $old = $existing->only(['invoice_no', 'issued_on']);
                $existing->update($values);
                $this->audit->log('store.invoice_replaced', $order, ['invoice_no' => $old['invoice_no']], ['order_no' => $order->order_no, 'invoice_no' => $existing->invoice_no]);

                return $existing;
            }
            $invoice = $order->invoice()->create($values + ['created_by_type' => $actor['type'], 'created_by_id' => $actor['id']]);
            $order->events()->create(['status' => 'invoice', 'note' => "Fatura eklendi: {$invoice->invoice_no}", 'actor_type' => $actor['type'], 'actor_id' => $actor['id']]);
            $this->audit->log('store.invoice_added', $order, [], ['order_no' => $order->order_no, 'invoice_no' => $invoice->invoice_no]);

            return $invoice;
        });

        // Yeni PDF yüklendiyse eskisi, kayıt güncellendikten sonra silinir.
        if ($oldPath && $oldPath !== $invoice->file_path) {
            Storage::disk('local')->delete($oldPath);
        }
        if (! $existing) {
            $this->mailer->invoiceReady($order, $invoice);
        }

        return $invoice;
    }

    /** Fatura PDF'i (yönetim ve müşteri rotaları; erişim denetimi rotada yapılır). */
    public function download(StoreOrder $order): StreamedResponse
    {
        $invoice = $order->invoice()->first();
        abort_unless($invoice?->file_path && Storage::disk('local')->exists($invoice->file_path), 404);

        return Storage::disk('local')->download($invoice->file_path, "Fatura-{$invoice->invoice_no}.pdf", [
            'Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
