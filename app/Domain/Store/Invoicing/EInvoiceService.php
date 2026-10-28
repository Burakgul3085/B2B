<?php

namespace App\Domain\Store\Invoicing;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\CurrentActor;
use App\Domain\Store\Invoicing\Providers\DenemeProvider;
use App\Domain\Store\Invoicing\Providers\EInvoiceProvider;
use App\Domain\Store\Invoicing\Providers\EInvoiceRejected;
use App\Domain\Store\Invoicing\Providers\EInvoiceUncertain;
use App\Domain\Store\Invoicing\Providers\UyumsoftProvider;
use App\Domain\Store\Orders\OrderMailer;
use App\Domain\Support\Decimal;
use App\Models\Store\StoreCreditNote;
use App\Models\Store\StoreEInvoice;
use App\Models\Store\StoreInvoice;
use App\Models\Store\StoreOrder;
use App\Models\Store\StoreReturn;
use App\Models\Store\StoreSetting;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Faturalar (yönetim): ödeme alınınca fatura KESİLMEZ. Yönetici siparişten taslak oluşturur; kalemleri (ad, miktar,
 * KDV dahil birim fiyat, indirim, KDV oranı) ve alıcı bilgilerini değiştirip kendisi gönderir.
 *
 *  - Taslak siparişi, ödemeyi ve stoku değiştirmez; yalnızca faturayı belirler. Tutarlar her zaman sunucuda hesaplanır.
 *  - Fatura toplamı siparişte ödenenden (iade faturasında iade edilenden) farklıysa gönderimde ayrıca onay istenir.
 *  - Alıcı e-Fatura mükellefiyse e-Fatura, değilse e-Arşiv kesilir (sağlayıcıdan sorgulanır).
 *  - Gönderilen fatura değişmez; e-Arşiv iptal edilebilir. e-Fatura mükellefi alıcıda iade faturasını alıcı keser
 *    (buradan kesilmez); e-Fatura iptali alıcıyla GİB portalından yürütülür.
 *  - Bağlantı koparsa fatura "sonuç belirsiz" kalır ve sağlayıcıdan sorgulanmadan yeniden gönderilemez (çift fatura olmaz).
 *  - Kesilen fatura store_invoices (satış) / store_credit_notes (iade) kaydına da yazılır: müşteri sipariş sayfasından indirir.
 */
class EInvoiceService
{
    /** Satış faturası kesilebilen sipariş durumları (ödendi / onaylandı ve sonrası). */
    public const BILLABLE = ['paid', 'preparing', 'shipped', 'delivered'];

    public const MAX_LINES = 200;

    public function __construct(private AuditLogger $audit, private CurrentActor $actor, private OrderMailer $mailer) {}

    public function provider(): EInvoiceProvider
    {
        return StoreSetting::get('invoice_driver') === 'uyumsoft' ? app(UyumsoftProvider::class) : app(DenemeProvider::class);
    }

    /** Satış faturası bekleyen siparişler: kesilebilir durumda, faturası (elle veya e-Fatura) ve açık taslağı yok. */
    public static function awaitingSale(): Builder
    {
        return StoreOrder::query()->whereIn('status', self::BILLABLE)->whereDoesntHave('invoice')
            ->whereDoesntHave('eInvoices', fn ($q) => $q->where('kind', 'sale')->where('status', '!=', 'cancelled'));
    }

    /**
     * İade faturası bekleyen siparişler: satış faturası var, para iadesi yapıldı veya sipariş iptal edildi, iade faturası yok.
     * Satış faturası e-Fatura olan (alıcı mükellef) siparişler hariç: iade faturasını alıcı keser, gelen belge elle eklenir.
     */
    public static function awaitingRefund(): Builder
    {
        return StoreOrder::query()->whereHas('invoice')
            ->where(fn ($q) => $q->where('refunded_total', '>', 0)->orWhereIn('status', ['cancelled', 'not_delivered']))
            ->whereDoesntHave('creditNotes')
            ->whereDoesntHave('eInvoices', fn ($q) => $q->where('kind', 'refund')->where('status', '!=', 'cancelled'))
            ->whereDoesntHave('eInvoices', fn ($q) => $q->where('kind', 'sale')->where('status', 'sent')->where('document_type', 'e_fatura'));
    }

    /** @throws DomainException */
    public function createSale(StoreOrder $order): StoreEInvoice
    {
        if (! in_array($order->status, self::BILLABLE, true)) {
            throw new DomainException('Fatura, ödemesi alınmış veya onaylanmış siparişe kesilir.');
        }
        if ($order->invoice()->exists()) {
            throw new DomainException('Bu siparişin faturası zaten var.');
        }
        if ($existing = $order->eInvoices()->where('kind', 'sale')->where('status', '!=', 'cancelled')->first()) {
            return $existing; // açık taslak varsa o açılır
        }

        $lines = $order->items()->orderBy('id')->get()->map(fn ($i) => [
            'order_item_id' => $i->id,
            'name' => mb_substr($i->product_name.($i->option_label ? " ({$i->option_label})" : ''), 0, 200),
            'sku' => $i->sku,
            'quantity' => (string) $i->quantity,
            'unit_price' => (string) $i->unit_price,
            'discount' => (string) $i->discount_amount,
            'vat_rate' => (string) $i->vat_rate,
        ])->all();
        array_push($lines, ...$this->feeLines($order));

        return $this->create($order, 'sale', null, $lines, (string) $order->total);
    }

    /**
     * İade faturası taslağı. İade talebi seçilirse yalnızca iade edilen adetler, seçilmezse (iptal / tam iade) tüm kalemler gelir.
     *
     * @throws DomainException
     */
    public function createRefund(StoreOrder $order, ?int $returnId = null): StoreEInvoice
    {
        $sale = $order->invoice()->first();
        if (! $sale) {
            throw new DomainException('Önce siparişin satış faturası kesilmelidir; iade faturası satış faturasına dayanır.');
        }
        if ($existing = $order->eInvoices()->where('kind', 'refund')->where('status', '!=', 'cancelled')->whereIn('status', ['draft', 'sending', 'unknown'])->first()) {
            return $existing;
        }
        $return = $returnId ? $order->returns()->with('items')->find($returnId) : null;
        $items = $order->items()->orderBy('id')->get()->keyBy('id');
        // Satış faturası buradan kesildiyse onun (düzeltilmiş olabilecek) alıcısı ve kalemleri esas alınır.
        $saleDoc = $order->eInvoices()->where('kind', 'sale')->where('status', 'sent')->with('lines')->latest('id')->first();

        if ($return) {
            $lines = $return->items->map(function ($ri) use ($items) {
                $item = $items[$ri->order_item_id];
                $share = $item->quantity > 0 ? Decimal::round(bcdiv(bcmul((string) $item->discount_amount, (string) $ri->quantity, 6), (string) $item->quantity, 6), 2) : '0.00';

                return [
                    'order_item_id' => $item->id, 'name' => mb_substr($item->product_name.($item->option_label ? " ({$item->option_label})" : ''), 0, 200), 'sku' => $item->sku,
                    'quantity' => (string) $ri->quantity, 'unit_price' => (string) $item->unit_price, 'discount' => $share, 'vat_rate' => (string) $item->vat_rate,
                ];
            })->values()->all();
            $expected = (string) ($return->refund_amount ?? $order->refunded_total);
        } else {
            // Satış faturasının kalemleri (buradan kesildiyse onun düzenlenmiş hâli) olduğu gibi gelir.
            $lines = $saleDoc
                ? $saleDoc->lines->map(fn ($l) => $l->only(['order_item_id', 'name', 'sku', 'quantity', 'unit_price', 'discount', 'vat_rate']))->all()
                : $items->map(fn ($i) => [
                    'order_item_id' => $i->id, 'name' => mb_substr($i->product_name.($i->option_label ? " ({$i->option_label})" : ''), 0, 200), 'sku' => $i->sku,
                    'quantity' => (string) $i->quantity, 'unit_price' => (string) $i->unit_price, 'discount' => (string) $i->discount_amount, 'vat_rate' => (string) $i->vat_rate,
                ])->values()->all();
            if (! $saleDoc) {
                array_push($lines, ...$this->feeLines($order));
            }
            $expected = bccomp((string) $order->refunded_total, '0', 2) > 0 ? (string) $order->refunded_total : ($saleDoc ? (string) $saleDoc->total : (string) $order->total);
        }

        return $this->create($order, 'refund', $return, $lines, $expected, $saleDoc);
    }

    /**
     * Taslağı kaydeder: alıcı bilgileri ve kalemler. Tutarlar yeniden hesaplanır.
     *
     * @throws DomainException|ValidationException
     */
    public function update(StoreEInvoice $invoice, array $input): StoreEInvoice
    {
        $this->assertDraft($invoice);
        $data = $this->validate($invoice, $input);
        $before = $this->summary($invoice);

        DB::transaction(function () use ($invoice, $data) {
            $invoice->lines()->delete();
            $this->saveLines($invoice, $data['lines']);
            $invoice->fill($data['buyer'] + [
                'issue_date' => $data['issue_date'],
                'note' => $data['note'],
                'notify_customer' => $data['notify_customer'],
                'error_message' => null,
            ]);
            $this->recalculate($invoice);
            $invoice->difference_confirmed_at = $invoice->hasDifference() && $data['confirm_difference'] ? now() : null;
            $invoice->save();
        });

        $this->audit->log('store.e_invoice_updated', $invoice->order, $before, $this->summary($invoice->fresh('lines')));

        return $invoice;
    }

    /**
     * Faturayı sağlayıcıya gönderir. Fark varsa onay gerekir ($confirmDifference veya taslakta kaydedilmiş onay).
     *
     * @throws DomainException
     */
    public function send(StoreEInvoice $invoice, bool $confirmDifference = false): StoreEInvoice
    {
        $this->assertDraft($invoice);
        $invoice->loadMissing(['lines', 'order']);
        if ($invoice->lines->isEmpty() || bccomp((string) $invoice->total, '0', 2) <= 0) {
            throw new DomainException('Faturada en az bir kalem olmalı ve toplam sıfırdan büyük olmalı.');
        }
        if ($invoice->hasDifference() && ! $confirmDifference && ! $invoice->difference_confirmed_at) {
            throw new DomainException('Fatura toplamı ('.number_format((float) $invoice->total, 2, ',', '.').' ₺) '
                .($invoice->kind === 'refund' ? 'iade edilen' : 'müşterinin ödediği').' tutardan ('.number_format((float) $invoice->expected_total, 2, ',', '.').' ₺) farklı. Göndermek için farkı onaylayın.');
        }
        if ($invoice->kind === 'refund' && ! $invoice->order->invoice()->exists()) {
            throw new DomainException('Satış faturası olmadan iade faturası gönderilemez.');
        }
        if ($invoice->kind === 'sale' && ! in_array($invoice->order->status, self::BILLABLE, true)) {
            throw new DomainException('Sipariş iptal edilmiş veya ödemesi alınmamış; satış faturası kesilemez. Taslağı silebilirsiniz.');
        }
        $provider = $this->provider();
        if (! $provider->isConfigured()) {
            throw new DomainException('Fatura sağlayıcısı ayarlanmamış (Ayarlar → Fatura).');
        }

        // Alıcı e-Fatura mükellefi mi? (VKN / TCKN yazılmış alıcılar sorgulanır; bireysel ve kimliksiz alıcı e-Arşiv'dir.)
        $documentType = 'e_arsiv';
        $alias = null;
        $taxNumber = (string) $invoice->buyer_tax_number;
        if ($taxNumber !== '' && $taxNumber !== '11111111111') {
            try {
                $lookup = $provider->lookup($taxNumber);
            } catch (EInvoiceUncertain|EInvoiceRejected $e) {
                $invoice->update(['error_message' => 'Alıcının e-Fatura durumu sorgulanamadı: '.$e->getMessage()]);
                throw new DomainException($invoice->error_message);
            }
            if ($lookup['registered'] && $invoice->kind === 'refund') {
                // Malı iade eden e-Fatura mükellefidir: iade faturasını o keser, satıcı keserse belge ters yönde olur.
                $invoice->update(['error_message' => 'Alıcı e-Fatura mükellefi: iade faturasını alıcı size keser. Bu taslağı silin; gelen iade faturasını muhasebenize kaydedin.']);
                throw new DomainException($invoice->error_message);
            }
            if ($lookup['registered']) {
                $documentType = 'e_fatura';
                $alias = $lookup['alias'];
            }
        }

        // Aynı taslak iki kez gönderilmesin: yalnızca taslak durumundaki kayıt "gönderiliyor"a geçebilir.
        $claimed = StoreEInvoice::whereKey($invoice->id)->where('status', 'draft')->update([
            'status' => 'sending', 'provider' => $provider->key(), 'document_type' => $documentType,
            'ettn' => $invoice->ettn ?: (string) Str::uuid(), 'sent_by_id' => $this->actor->get()['id'], 'updated_at' => now(),
        ]);
        if ($claimed !== 1) {
            throw new DomainException('Bu fatura şu anda gönderiliyor veya gönderildi.');
        }
        $invoice->refresh()->load(['lines', 'order']);

        try {
            $result = $provider->send($invoice, $documentType, $alias);
        } catch (EInvoiceRejected $e) {
            $invoice->update(['status' => 'draft', 'error_message' => mb_substr($e->getMessage(), 0, 500)]);
            $this->audit->log('store.e_invoice_rejected', $invoice->order, [], ['local_id' => $invoice->local_id, 'error' => $invoice->error_message]);
            throw new DomainException('Fatura kesilmedi. '.$e->getMessage());
        } catch (EInvoiceUncertain $e) {
            $invoice->update(['status' => 'unknown', 'error_message' => mb_substr($e->getMessage(), 0, 500)]);
            $this->audit->log('store.e_invoice_uncertain', $invoice->order, [], ['local_id' => $invoice->local_id, 'error' => $invoice->error_message]);
            throw new DomainException('Gönderimin sonucu alınamadı; fatura kesilmiş olabilir. "Sağlayıcıdan sorgula" ile kontrol edin. ('.$e->getMessage().')');
        }

        return $this->finalize($invoice, $result);
    }

    /**
     * Seçilen taslakları sırayla gönderir. Farkı onaylanmamış taslaklar atlanır.
     *
     * @param  list<int>  $ids
     * @return array{sent: list<string>, failed: array<string, string>}
     */
    public function sendMany(array $ids): array
    {
        $sent = [];
        $failed = [];
        foreach (StoreEInvoice::with('order')->whereIn('id', array_slice($ids, 0, 50))->where('status', 'draft')->get() as $invoice) {
            try {
                $sent[] = $this->send($invoice)->invoice_no;
            } catch (DomainException $e) {
                $failed[$invoice->order->order_no] = $e->getMessage();
            }
        }

        return compact('sent', 'failed');
    }

    /**
     * Seçilen siparişler için satış faturası taslağı oluşturur.
     *
     * @param  list<int>  $orderIds
     */
    public function createMany(array $orderIds): int
    {
        $count = 0;
        foreach (self::awaitingSale()->whereIn('id', array_slice($orderIds, 0, 100))->get() as $order) {
            $this->createSale($order);
            $count++;
        }

        return $count;
    }

    /** Sonucu belirsiz kalan gönderimi sağlayıcıdan sorgular. @throws DomainException */
    public function check(StoreEInvoice $invoice): StoreEInvoice
    {
        if (! in_array($invoice->status, ['unknown', 'sending'], true)) {
            throw new DomainException('Yalnızca sonucu belirsiz kalan fatura sorgulanabilir.');
        }
        try {
            $result = $this->provider()->find($invoice);
        } catch (EInvoiceUncertain|EInvoiceRejected $e) {
            throw new DomainException('Sağlayıcıya ulaşılamadı, biraz sonra yeniden deneyin. ('.$e->getMessage().')');
        }
        if (! $result) {
            $invoice->update(['status' => 'draft', 'error_message' => 'Sağlayıcıda bu fatura bulunamadı; kesilmemiş. Kontrol edip yeniden gönderebilirsiniz.']);

            return $invoice;
        }

        return $this->finalize($invoice->load(['lines', 'order']), $result);
    }

    /** Yönetici sağlayıcının panelinde faturanın kesilmediğini gördüyse taslağa geri alır. */
    public function release(StoreEInvoice $invoice): void
    {
        if (! in_array($invoice->status, ['unknown', 'sending'], true)) {
            throw new DomainException('Yalnızca sonucu belirsiz kalan fatura taslağa alınabilir.');
        }
        $invoice->update(['status' => 'draft', 'error_message' => 'Yönetici tarafından taslağa alındı (sağlayıcıda kesilmediği kontrol edildi).']);
        $this->audit->log('store.e_invoice_released', $invoice->order, [], ['local_id' => $invoice->local_id]);
    }

    /** Gönderilmiş e-Arşiv faturasını iptal eder; müşteri tarafındaki fatura kaldırılır, yeni taslak hazırlanabilir. @throws DomainException|ValidationException */
    public function cancel(StoreEInvoice $invoice, string $reason): void
    {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 300) {
            throw ValidationException::withMessages(['cancel_reason' => 'İptal nedenini yazın (en fazla 300 karakter).']);
        }
        if ($invoice->status !== 'sent') {
            throw new DomainException('Yalnızca gönderilmiş fatura iptal edilebilir.');
        }
        if ($invoice->document_type !== 'e_arsiv') {
            throw new DomainException('e-Fatura buradan iptal edilemez: iptali alıcıyla GİB portalından yürütün veya alıcı size iade faturası kessin.');
        }
        if ($invoice->kind === 'sale' && $invoice->order->eInvoices()->where('kind', 'refund')->where('status', 'sent')->exists()) {
            throw new DomainException('Bu satış faturasına dayanan bir iade faturası var; önce iade faturasını iptal edin.');
        }
        try {
            if ($invoice->provider !== 'deneme') {
                $this->provider()->cancel($invoice, $reason);
            }
        } catch (EInvoiceRejected|EInvoiceUncertain $e) {
            throw new DomainException('Fatura iptal edilemedi. '.$e->getMessage());
        }

        DB::transaction(function () use ($invoice, $reason) {
            $invoice->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => $reason]);
            if ($invoice->kind === 'sale') {
                StoreInvoice::where('order_id', $invoice->order_id)->where('invoice_no', $invoice->invoice_no)->delete();
            } else {
                StoreCreditNote::where('order_id', $invoice->order_id)->where('number', $invoice->invoice_no)->delete();
            }
            $actor = $this->actor->get();
            $invoice->order->events()->create(['status' => 'invoice', 'note' => "Fatura iptal edildi: {$invoice->invoice_no} ({$reason})", 'actor_type' => $actor['type'], 'actor_id' => $actor['id']]);
        });
        $this->audit->log('store.e_invoice_cancelled', $invoice->order, [], ['invoice_no' => $invoice->invoice_no, 'reason' => $reason]);
    }

    public function deleteDraft(StoreEInvoice $invoice): void
    {
        $this->assertDraft($invoice);
        $this->audit->log('store.e_invoice_draft_deleted', $invoice->order, $this->summary($invoice), []);
        $invoice->delete();
    }

    /** Sağlayıcıdan PDF alınamadıysa yeniden ister (Uyumsoft). */
    public function refetchPdf(StoreEInvoice $invoice): void
    {
        if ($invoice->status !== 'sent' || $invoice->pdf_path) {
            return;
        }
        $result = $this->provider()->find($invoice);
        if ($result && $result['pdf'] !== '') {
            $this->storePdf($invoice, $result['pdf']);
        } else {
            throw new DomainException('PDF henüz alınamadı; biraz sonra yeniden deneyin.');
        }
    }

    /** Taslağın önizleme PDF'i ("geçerli fatura değildir"). */
    public function preview(StoreEInvoice $invoice): string
    {
        return app(InvoicePdf::class)->render($invoice, $invoice->status === 'sent' ? '' : 'ÖNİZLEME — GEÇERLİ FATURA DEĞİLDİR');
    }

    private function finalize(StoreEInvoice $invoice, array $result): StoreEInvoice
    {
        $actor = $this->actor->get();
        DB::transaction(function () use ($invoice, $result, $actor) {
            $invoice->update([
                'status' => 'sent', 'invoice_no' => $result['invoice_no'], 'ettn' => $result['ettn'],
                'sent_at' => now(), 'error_message' => null,
            ]);
            $order = $invoice->order;
            if ($invoice->kind === 'sale') {
                $order->invoice()->create([
                    'invoice_no' => $invoice->invoice_no, 'issued_on' => $invoice->issue_date, 'provider' => $invoice->provider,
                    'provider_uuid' => $invoice->ettn, 'created_by_type' => $actor['type'], 'created_by_id' => $actor['id'],
                ]);
            } else {
                StoreCreditNote::create([
                    'order_id' => $order->id, 'return_id' => $invoice->return_id, 'number' => $invoice->invoice_no, 'issued_on' => $invoice->issue_date,
                    'amount' => $invoice->total, 'created_by_type' => $actor['type'], 'created_by_id' => $actor['id'],
                ]);
            }
            $label = ($invoice->kind === 'sale' ? 'Fatura' : 'İade faturası').' kesildi ('.StoreEInvoice::DOCUMENT_TYPES[$invoice->document_type].')';
            $order->events()->create(['status' => 'invoice', 'note' => "{$label}: {$invoice->invoice_no}", 'actor_type' => $actor['type'], 'actor_id' => $actor['id']]);
        });
        if ($result['pdf'] !== '') {
            $this->storePdf($invoice, $result['pdf']);
        }
        $this->audit->log('store.e_invoice_sent', $invoice->order, [], [
            'invoice_no' => $invoice->invoice_no, 'kind' => $invoice->kind, 'document_type' => $invoice->document_type,
            'total' => (string) $invoice->total, 'expected_total' => (string) $invoice->expected_total,
        ]);

        if ($invoice->notify_customer && $invoice->kind === 'sale' && ($record = $invoice->order->invoice()->first())) {
            $this->mailer->invoiceReady($invoice->order, $record);
        }

        return $invoice;
    }

    private function storePdf(StoreEInvoice $invoice, string $pdf): void
    {
        $path = "store/invoices/{$invoice->order_id}/".Str::random(40).'.pdf';
        Storage::disk('local')->put($path, $pdf);
        $invoice->update(['pdf_path' => $path]);
        if ($invoice->kind === 'sale') {
            StoreInvoice::where('order_id', $invoice->order_id)->where('invoice_no', $invoice->invoice_no)->update(['file_path' => $path]);
        } else {
            StoreCreditNote::where('order_id', $invoice->order_id)->where('number', $invoice->invoice_no)->update(['file_path' => $path]);
        }
    }

    /** Kargo / teslimat ve kapıda ödeme bedeli: ayrı hizmet kalemleri. */
    private function feeLines(StoreOrder $order): array
    {
        $vat = (string) (StoreSetting::get('invoice_shipping_vat') ?? '20');
        $lines = [];
        if (bccomp((string) $order->shipping_fee, '0', 2) > 0) {
            $lines[] = ['order_item_id' => null, 'name' => $order->delivery_method === 'local' ? 'Teslimat hizmeti' : 'Kargo hizmeti', 'sku' => null,
                'quantity' => '1', 'unit_price' => (string) $order->shipping_fee, 'discount' => '0', 'vat_rate' => $vat];
        }
        if (bccomp((string) $order->cod_fee, '0', 2) > 0) {
            $lines[] = ['order_item_id' => null, 'name' => 'Kapıda ödeme hizmet bedeli', 'sku' => null,
                'quantity' => '1', 'unit_price' => (string) $order->cod_fee, 'discount' => '0', 'vat_rate' => $vat];
        }

        return $lines;
    }

    private function create(StoreOrder $order, string $kind, ?StoreReturn $return, array $lines, string $expected, ?StoreEInvoice $buyerFrom = null): StoreEInvoice
    {
        $actor = $this->actor->get();
        $corporate = $order->billing_type === 'corporate';
        $buyer = $buyerFrom
            ? $buyerFrom->only(['buyer_type', 'buyer_name', 'buyer_tax_office', 'buyer_tax_number', 'buyer_address', 'buyer_district', 'buyer_city', 'buyer_email', 'buyer_phone'])
            : [
                'buyer_type' => $corporate ? 'corporate' : 'individual',
                'buyer_name' => $order->billing_name ?: $order->shipping_name,
                'buyer_tax_office' => $corporate ? $order->billing_tax_office : null,
                'buyer_tax_number' => $order->billing_tax_number,
                'buyer_address' => $order->billing_address ?: $order->shippingOneLine(),
                'buyer_district' => $order->shipping_district,
                'buyer_city' => $order->shipping_province,
                'buyer_email' => $order->email,
                'buyer_phone' => $order->phone,
            ];

        $invoice = DB::transaction(function () use ($order, $kind, $return, $lines, $expected, $actor, $buyer) {
            $invoice = StoreEInvoice::create($buyer + [
                'order_id' => $order->id, 'return_id' => $return?->id, 'kind' => $kind, 'status' => 'draft',
                'local_id' => strtoupper(($kind === 'sale' ? 'S' : 'I').'-'.$order->order_no.'-'.Str::random(6)),
                'issue_date' => now('Europe/Istanbul')->toDateString(),
                'expected_total' => $expected,
                'notify_customer' => StoreSetting::get('invoice_notify') !== '0',
                'created_by_type' => $actor['type'], 'created_by_id' => $actor['id'],
            ]);
            $this->saveLines($invoice, $lines);
            $this->recalculate($invoice);
            $invoice->save();

            return $invoice;
        });
        $this->audit->log('store.e_invoice_draft_created', $order, [], ['kind' => $kind, 'local_id' => $invoice->local_id]);

        return $invoice;
    }

    private function saveLines(StoreEInvoice $invoice, array $lines): void
    {
        foreach (array_values($lines) as $i => $l) {
            $invoice->lines()->create([
                'order_item_id' => $l['order_item_id'] ?? null, 'position' => $i + 1, 'name' => $l['name'], 'sku' => $l['sku'] ?? null,
                'quantity' => $l['quantity'], 'unit_price' => $l['unit_price'], 'discount' => $l['discount'] ?: '0', 'vat_rate' => $l['vat_rate'],
            ] + array_diff_key(InvoiceMath::line((string) $l['quantity'], (string) $l['unit_price'], (string) ($l['discount'] ?: '0'), (string) $l['vat_rate']), ['gross' => true]));
        }
    }

    private function recalculate(StoreEInvoice $invoice): void
    {
        $totals = InvoiceMath::totals($invoice->lines()->get()->map(fn ($l) => $l->only(['net_amount', 'vat_amount', 'line_total', 'vat_rate'])));
        $invoice->subtotal = $totals['subtotal'];
        $invoice->vat_total = $totals['vat_total'];
        $invoice->total = $totals['total'];
    }

    /** @throws ValidationException */
    private function validate(StoreEInvoice $invoice, array $input): array
    {
        $data = Validator::make($input, [
            'buyer_type' => ['required', 'in:individual,corporate'],
            'buyer_name' => ['required', 'string', 'max:160'],
            'buyer_tax_office' => ['nullable', 'required_if:buyer_type,corporate', 'string', 'max:80'],
            'buyer_tax_number' => ['nullable', 'string', 'max:14', function ($attribute, $value, $fail) use ($input) {
                $digits = preg_replace('/\D/', '', (string) $value);
                if (($input['buyer_type'] ?? null) === 'corporate' && ! in_array(strlen($digits), [10, 11], true)) {
                    $fail('Kurumsal faturada 10 haneli VKN (şahıs firmasında 11 haneli TCKN) gerekir.');
                } elseif (($input['buyer_type'] ?? null) === 'individual' && $digits !== '' && strlen($digits) !== 11) {
                    $fail('TC kimlik numarası 11 hanelidir (boş bırakılırsa 11111111111 kullanılır).');
                }
            }],
            'buyer_address' => ['required', 'string', 'max:500'],
            'buyer_district' => ['nullable', 'string', 'max:60'],
            'buyer_city' => ['required', 'string', 'max:40'],
            'buyer_email' => ['nullable', 'email', 'max:160'],
            'buyer_phone' => ['nullable', 'string', 'max:20'],
            'note' => ['nullable', 'string', 'max:500'],
            'issue_date' => ['required', 'date', 'before_or_equal:'.now('Europe/Istanbul')->toDateString(), 'after_or_equal:'.now('Europe/Istanbul')->subDays(7)->toDateString()],
            'lines' => ['required', 'array', 'min:1', 'max:'.self::MAX_LINES],
            'lines.*.name' => ['required', 'string', 'max:200'],
            'lines.*.sku' => ['nullable', 'string', 'max:64'],
            'lines.*.order_item_id' => ['nullable', 'integer'],
            'lines.*.quantity' => ['required'],
            'lines.*.unit_price' => ['required'],
            'lines.*.discount' => ['nullable'],
            'lines.*.vat_rate' => ['required'],
        ], [
            'buyer_tax_office.required_if' => 'Kurumsal faturada vergi dairesi gerekir.',
            'issue_date.after_or_equal' => 'Fatura tarihi en fazla 7 gün geriye verilebilir.',
            'lines.required' => 'Faturada en az bir kalem olmalı.',
        ], [
            'buyer_name' => 'alıcı adı / unvanı', 'buyer_tax_office' => 'vergi dairesi', 'buyer_tax_number' => 'VKN / TCKN', 'buyer_address' => 'adres',
            'buyer_city' => 'il', 'buyer_district' => 'ilçe', 'buyer_email' => 'e-posta', 'issue_date' => 'fatura tarihi', 'note' => 'açıklama',
            'lines.*.name' => 'kalem adı', 'lines.*.vat_rate' => 'KDV oranı',
        ])->validate();

        $errors = [];
        $floor = $invoice->order->created_at->timezone('Europe/Istanbul')->toDateString();
        if ($invoice->kind === 'refund' && ($sale = $invoice->order->invoice()->first())) {
            $floor = max($floor, $sale->issued_on->toDateString());
        }
        if ($data['issue_date'] < $floor) {
            $errors['issue_date'] = 'Fatura tarihi '.($invoice->kind === 'refund' ? 'satış faturasının' : 'siparişin').' tarihinden ('.date('d.m.Y', strtotime($floor)).') önce olamaz.';
        }
        $taxNumber = preg_replace('/\D/', '', (string) ($data['buyer_tax_number'] ?? ''));

        $itemIds = $invoice->order->items()->pluck('id')->all();
        $lines = [];
        foreach (array_values($data['lines']) as $i => $l) {
            $quantity = Decimal::parse($l['quantity'], 3);
            $unit = Decimal::parse($l['unit_price'], 2);
            $discount = filled($l['discount'] ?? null) ? Decimal::parse($l['discount'], 2) : '0.00';
            if ($quantity === null || bccomp($quantity, '0', 3) <= 0) {
                $errors["lines.{$i}.quantity"] = ($i + 1).'. kalem: miktar sıfırdan büyük olmalı (en fazla 3 ondalık).';
            }
            if ($unit === null || bccomp($unit, '0', 2) < 0) {
                $errors["lines.{$i}.unit_price"] = ($i + 1).'. kalem: geçerli bir birim fiyat girin (ör. 49,90).';
            }
            if ($discount === null || bccomp($discount, '0', 2) < 0) {
                $errors["lines.{$i}.discount"] = ($i + 1).'. kalem: geçerli bir indirim tutarı girin.';
            } elseif ($quantity !== null && $unit !== null && bccomp($discount, Decimal::round(bcmul($quantity, $unit, 6), 2), 2) > 0) {
                $errors["lines.{$i}.discount"] = ($i + 1).'. kalem: indirim satır tutarından büyük olamaz.';
            }
            if (! in_array((string) $l['vat_rate'], InvoiceMath::VAT_RATES, true)) {
                $errors["lines.{$i}.vat_rate"] = ($i + 1).'. kalem: KDV oranı %'.implode(', %', InvoiceMath::VAT_RATES).' olabilir.';
            }
            $itemId = isset($l['order_item_id']) && in_array((int) $l['order_item_id'], $itemIds, true) ? (int) $l['order_item_id'] : null;
            $lines[] = ['order_item_id' => $itemId, 'name' => trim($l['name']), 'sku' => $l['sku'] ?? null, 'quantity' => $quantity, 'unit_price' => $unit, 'discount' => $discount, 'vat_rate' => $l['vat_rate']];
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'buyer' => [
                'buyer_type' => $data['buyer_type'], 'buyer_name' => trim($data['buyer_name']),
                'buyer_tax_office' => $data['buyer_type'] === 'corporate' ? trim((string) $data['buyer_tax_office']) : (trim((string) ($data['buyer_tax_office'] ?? '')) ?: null),
                'buyer_tax_number' => $taxNumber ?: null, 'buyer_address' => trim($data['buyer_address']),
                'buyer_district' => trim((string) ($data['buyer_district'] ?? '')) ?: null, 'buyer_city' => trim($data['buyer_city']),
                'buyer_email' => $data['buyer_email'] ?? null, 'buyer_phone' => $data['buyer_phone'] ?? null,
            ],
            'issue_date' => $data['issue_date'],
            'note' => trim((string) ($data['note'] ?? '')) ?: null,
            'notify_customer' => filter_var($input['notify_customer'] ?? false, FILTER_VALIDATE_BOOL),
            'confirm_difference' => filter_var($input['confirm_difference'] ?? false, FILTER_VALIDATE_BOOL),
            'lines' => $lines,
        ];
    }

    /** İşlem geçmişi için özet (kalemler: ad, miktar, fiyat, indirim, KDV). */
    private function summary(StoreEInvoice $invoice): array
    {
        return [
            'buyer' => $invoice->buyer_name, 'tax_number' => $invoice->buyer_tax_number, 'total' => (string) $invoice->total,
            'lines' => $invoice->lines()->get()->map(fn ($l) => "{$l->name} × ".rtrim(rtrim((string) $l->quantity, '0'), '.')." @ {$l->unit_price} −{$l->discount} %".rtrim(rtrim((string) $l->vat_rate, '0'), '.'))->all(),
        ];
    }

    /** @throws DomainException */
    private function assertDraft(StoreEInvoice $invoice): void
    {
        if (! $invoice->isDraft()) {
            throw new DomainException('Yalnızca taslak fatura değiştirilebilir veya gönderilebilir.');
        }
    }
}
