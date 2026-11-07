<?php

namespace App\Domain\Invoicing;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\CurrentActor;
use App\Domain\Invoicing\Providers\DenemeProvider;
use App\Domain\Invoicing\Providers\InvoiceProvider;
use App\Domain\Invoicing\Providers\InvoiceRejected;
use App\Domain\Invoicing\Providers\InvoiceUncertain;
use App\Domain\Notifications\Notifier;
use App\Domain\Ordering\ReturnService;
use App\Domain\Support\Decimal;
use App\Domain\Support\Money;
use App\Domain\Tenancy\TenantContext;
use App\Models\DealerUser;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderReturn;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * B2B faturaları (toptancı → bayi). Mağazanın fatura servisinden tamamen ayrıdır; yalnızca B2B siparişleri ve iadeleri
 * faturalanır. Kurallar (docs/35-b2b-fatura-raporu.md):
 *
 *  - Ne zaman: kart / havale siparişi ödemesi alınınca ("Ödendi"); sonra öde ve eski yöntemler mal yola çıkınca
 *    (VUK 231: teslimden itibaren 7 gün). Otomatik kesim açıksa (Fatura Ayarları) bu anda kendiliğinden kesilir;
 *    açık değilse sipariş "Fatura bekleyen"de durur, toptancı taslağı kontrol edip gönderir.
 *  - Tutar: kalemler siparişin (iadede iade kaydının) hesaplanmış tutarlarından BİREBİR gelir ve değiştirilemez; fatura,
 *    bayinin carisine yazılan ve ödediği tutarla aynıdır. Taslakta yalnızca alıcı bilgisi, açıklama ve tarih düzeltilir.
 *    Gönderimden önce fatura toplamı siparişin güncel tutarıyla yeniden karşılaştırılır; tutmazsa gönderilmez.
 *  - Belge: bayi e-Fatura mükellefiyse e-Fatura, değilse e-Arşiv (Uyumsoft'tan sorgulanır). Bayi serileri mağazadan ayrıdır.
 *  - İade: her iade kaydına bir iade faturası. Sipariş tamamen iade / iptal edildiyse ve satış faturası 7 gün içindeyse
 *    iade faturası yerine satış faturası iptal edilir. e-Fatura mükellefi bayide iade faturasını bayi keser (elle eklenir).
 *  - Çift fatura olmaz: bir siparişin aynı anda tek açık satış belgesi vardır (veritabanında da tekil). Bağlantı koparsa
 *    belge "sonuç belirsiz" kalır ve Uyumsoft'tan sorgulanmadan yeniden gönderilemez. Uyumsoft portalında elle kesilen
 *    fatura da numarasıyla kaydedilir.
 */
class InvoiceService
{
    /** Satış faturası kesilebilen sipariş durumları. */
    public const BILLABLE = ['approved', 'preparing', 'shipped', 'completed'];

    /** Bu durumlarda mal bayiye gitmiştir (ödeme yöntemi ne olursa olsun fatura kesilir). */
    public const DELIVERED = ['shipped', 'completed'];

    public const MAX_PDF_KB = 5120;

    public function __construct(
        private AuditLogger $audit,
        private CurrentActor $actor,
        private InvoiceSettings $settings,
        private Notifier $notifier,
        private TenantContext $tenant,
    ) {}

    public function provider(): InvoiceProvider
    {
        return $this->settings->provider();
    }

    /* ---------------- Sorgular ---------------- */

    /** Satış faturası kesilebilir mi: ödemesi alınmış ya da yola çıkmış, iptal / ret edilmemiş sipariş. */
    public static function billable(Order $order): bool
    {
        return in_array($order->status, self::BILLABLE, true)
            && ($order->payment_status === 'paid' || in_array($order->status, self::DELIVERED, true));
    }

    /** Faturanın doğduğu an: ödeme alındı (paid_at) veya yola çıktı (shipped_at); hangisi önceyse. */
    public static function triggeredAt(Order $order): ?CarbonInterface
    {
        $times = array_filter([
            $order->payment_status === 'paid' ? $order->paid_at : null,
            in_array($order->status, self::DELIVERED, true) ? ($order->shipped_at ?? $order->completed_at) : null,
        ]);

        return $times ? min($times) : null;
    }

    /** Satış faturası bekleyen siparişler: kesilebilir durumda ve açık satış belgesi (taslak dahil) yok. */
    public static function awaitingSale(): Builder
    {
        return Order::query()->whereIn('status', self::BILLABLE)
            ->where(fn ($q) => $q->where('payment_status', 'paid')->orWhereIn('status', self::DELIVERED))
            ->whereDoesntHave('invoices', fn ($q) => $q->where('kind', 'sale')->whereNotIn('status', Invoice::CLOSED));
    }

    /**
     * İade faturası bekleyenler: satış faturası kesilmiş siparişin iade kayıtları ve iade kaydı olmadan iptal edilen
     * siparişler (ödemesi alınıp yola çıkmadan iptal edilen). Satış faturası e-Fatura olanlar hariç (iadeyi bayi keser).
     *
     * @return Collection<int, array{order: Order, return: ?OrderReturn, sale: Invoice, cancel_instead: bool}>
     */
    public static function awaitingRefund(int $limit = 200): Collection
    {
        $sales = Invoice::query()->where('kind', 'sale')->where('status', 'sent')
            ->where(fn ($q) => $q->where('document_type', 'e_arsiv')->orWhereNull('document_type'))
            ->whereHas('order', fn ($q) => $q->where('status', 'cancelled')->orWhereExists(
                fn ($r) => $r->select(DB::raw(1))->from('order_returns')->whereColumn('order_returns.order_id', 'orders.id')
            ))
            ->with('order')->orderByDesc('id')->limit($limit)->get();

        $open = Invoice::query()->where('kind', 'refund')->whereNotIn('status', Invoice::CLOSED)
            ->whereIn('order_id', $sales->pluck('order_id'))->get(['order_id', 'order_return_id'])
            ->map(fn ($i) => $i->order_id.'-'.(int) $i->order_return_id)->flip();

        $rows = collect();
        foreach ($sales as $sale) {
            $order = $sale->order;
            $returns = OrderReturn::where('order_id', $order->id)->orderBy('id')->get();
            $cancelInstead = self::cancelInsteadOfRefund($order) !== null;
            if ($returns->isEmpty() && $order->status === 'cancelled' && ! isset($open[$order->id.'-0'])) {
                $rows->push(['order' => $order, 'return' => null, 'sale' => $sale, 'cancel_instead' => $cancelInstead]);
            }
            foreach ($returns as $return) {
                if (isset($open[$order->id.'-'.$return->id])) {
                    continue;
                }
                $rows->push(['order' => $order, 'return' => $return, 'sale' => $sale, 'cancel_instead' => $cancelInstead]);
            }
        }

        return $rows;
    }

    /**
     * Sipariş tamamen iade / iptal edildiyse ve satış faturası henüz iptal edilebiliyorsa (7 gün, e-Arşiv, buradan
     * kesilmiş, dayanan iade faturası yok) o fatura: iade faturası kesilmez, satış faturası iptal edilir.
     */
    public static function cancelInsteadOfRefund(Order $order): ?Invoice
    {
        if ($order->status !== 'cancelled') {
            return null;
        }
        if (Invoice::where('order_id', $order->id)->where('kind', 'refund')->where('status', 'sent')->exists()) {
            return null;
        }
        $sale = Invoice::where('order_id', $order->id)->where('kind', 'sale')->where('status', 'sent')->latest('id')->first();

        return $sale && self::cancellable($sale) ? $sale : null;
    }

    /** e-Arşiv faturasının iptal edilebileceği son an: fatura tarihinden itibaren 7. günün sonu (İstanbul saati). */
    public static function cancelDeadline(Invoice $invoice): CarbonInterface
    {
        return CarbonImmutable::parse($invoice->issue_date->toDateString(), 'Europe/Istanbul')->addDays(7)->endOfDay();
    }

    public static function cancellable(Invoice $invoice): bool
    {
        return $invoice->status === 'sent' && ! $invoice->isManual() && $invoice->document_type === 'e_arsiv'
            && now()->lte(self::cancelDeadline($invoice));
    }

    /* ---------------- Taslak ---------------- */

    /** Satış faturası taslağı (açık belge varsa o döner). @throws DomainException */
    public function createSale(Order $order): Invoice
    {
        $invoice = DB::transaction(function () use ($order) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($existing = $this->openDocument($order, 'sale', null)) {
                return $existing;
            }
            if (! self::billable($order)) {
                throw new DomainException('Satış faturası, ödemesi alınmış (kart / havale) veya yola çıkmış siparişe kesilir.');
            }

            return $this->create($order, 'sale', null, $this->saleLines($order), (string) $order->grand_total);
        });
        $this->audited($invoice, 'sale');

        return $invoice;
    }

    /**
     * İade faturası taslağı: iade kaydı verilirse o iadenin kalemleri, verilmezse (iade kaydı olmadan iptal edilen sipariş)
     * satış faturasının tamamı.
     *
     * @throws DomainException
     */
    public function createRefund(Order $order, ?OrderReturn $return = null): Invoice
    {
        $invoice = DB::transaction(function () use ($order, $return) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($return && $return->order_id !== $order->id) {
                throw new DomainException('İade kaydı bu siparişe ait değil.');
            }
            if ($existing = $this->openDocument($order, 'refund', $return)) {
                return $existing;
            }
            $sale = $this->saleInvoice($order) ?? throw new DomainException('Önce siparişin satış faturası kesilmelidir; iade faturası satış faturasına dayanır.');
            if ($sale->document_type === 'e_fatura') {
                throw new DomainException('Satış faturası e-Fatura: bayi e-Fatura mükellefi olduğu için iade faturasını bayi size keser. Gelen iade faturasını "Elle kesilen faturayı ekle" ile kaydedin.');
            }
            if ($sent = self::cancelInsteadOfRefund($order)) {
                throw new DomainException("Sipariş tamamen iade / iptal edildi ve satış faturası {$sent->invoice_no} henüz iptal edilebilir (son gün "
                    .self::cancelDeadline($sent)->format('d.m.Y').'). İade faturası kesmeyin; satış faturasını iptal edin.');
            }
            if (! $return && ($order->status !== 'cancelled' || OrderReturn::where('order_id', $order->id)->exists())) {
                throw new DomainException('İade faturası iade kaydına kesilir; iade kaydı olmadan yalnızca iptal edilen siparişe tam iade faturası kesilir.');
            }
            [$lines, $expected] = $return ? $this->returnLines($order, $return, $sale) : $this->fullRefundLines($sale);

            return $this->create($order, 'refund', $return, $lines, $expected);
        });
        $this->audited($invoice, 'refund');

        return $invoice;
    }

    /**
     * Seçilen siparişler için satış faturası taslağı oluşturur.
     *
     * @param  list<int>  $orderIds
     */
    public function createMany(array $orderIds): int
    {
        $count = 0;
        foreach (self::awaitingSale()->whereIn('id', array_slice(array_map('intval', $orderIds), 0, 100))->get() as $order) {
            try {
                $this->createSale($order);
                $count++;
            } catch (DomainException $e) {
                report($e);
            }
        }

        return $count;
    }

    /**
     * Taslağı kaydeder: alıcı bilgileri, açıklama, tarih, bayiye bildirim. Kalemler ve tutarlar değiştirilemez.
     *
     * @throws DomainException|ValidationException
     */
    public function update(Invoice $invoice, array $input): Invoice
    {
        $this->assertDraft($invoice);
        $data = $this->validateDraft($invoice, $input);
        $before = $invoice->only(array_keys($data));
        $invoice->fill($data + ['error_message' => null])->save();
        $this->audit->log('invoice.draft_updated', $invoice, $before, $invoice->only(array_keys($data)));

        return $invoice;
    }

    public function deleteDraft(Invoice $invoice): void
    {
        $this->assertDraft($invoice);
        $this->audit->log('invoice.draft_deleted', $invoice, ['order_id' => $invoice->order_id, 'kind' => $invoice->kind, 'local_id' => $invoice->local_id, 'total' => (string) $invoice->total], []);
        $invoice->delete();
    }

    /* ---------------- Gönderim ---------------- */

    /** Faturayı sağlayıcıya gönderir. @throws DomainException */
    public function send(Invoice $invoice): Invoice
    {
        $this->assertDraft($invoice);
        $invoice->load(['lines', 'order']);
        try {
            $this->assertSendable($invoice);
        } catch (DomainException $e) {
            $invoice->update(['error_message' => mb_substr($e->getMessage(), 0, 500)]);
            throw $e;
        }
        $provider = $this->provider();
        if (! $provider->isConfigured()) {
            $invoice->update(['error_message' => 'Fatura sağlayıcısı ayarlanmamış (Ayarlar → Fatura Ayarları).']);
            throw new DomainException($invoice->error_message);
        }

        // Bayi e-Fatura mükellefi mi? Mükellefse e-Fatura, değilse e-Arşiv.
        try {
            $lookup = $provider->lookup((string) $invoice->buyer_tax_number);
        } catch (InvoiceUncertain|InvoiceRejected $e) {
            $invoice->update(['error_message' => mb_substr('Bayinin e-Fatura durumu sorgulanamadı: '.$e->getMessage(), 0, 500)]);
            throw new DomainException($invoice->error_message);
        }
        if ($lookup['registered'] && $invoice->kind === 'refund') {
            // Malı iade eden e-Fatura mükellefidir: iade faturasını o keser.
            $invoice->update(['error_message' => 'Bayi e-Fatura mükellefi: iade faturasını bayi size keser. Bu taslağı silin; gelen iade faturasını "Elle kesilen faturayı ekle" ile kaydedin.']);
            throw new DomainException($invoice->error_message);
        }
        $documentType = $lookup['registered'] ? 'e_fatura' : 'e_arsiv';
        $record = $this->settings->record();
        if ($provider->key() === 'uyumsoft' && blank($documentType === 'e_fatura' ? $record->series_efatura : $record->series_earsiv)) {
            $invoice->update(['error_message' => 'Bayi faturası serisi tanımlı değil (Fatura Ayarları). Mağazadan ayrı seri yazmadan fatura kesilmez.']);
            throw new DomainException($invoice->error_message);
        }

        // Aynı taslak iki kez gönderilmesin: yalnızca taslak durumundaki kayıt "gönderiliyor"a geçebilir.
        $claimed = Invoice::whereKey($invoice->id)->where('status', 'draft')->update([
            'status' => 'sending', 'provider' => $provider->key(), 'document_type' => $documentType,
            'ettn' => $invoice->ettn ?: (string) Str::uuid(), 'sent_by_id' => $this->actor->get()['id'], 'updated_at' => now(),
        ]);
        if ($claimed !== 1) {
            throw new DomainException('Bu fatura şu anda gönderiliyor veya gönderildi.');
        }
        $invoice->refresh()->load(['lines', 'order']);

        try {
            $result = $provider->send($invoice, $documentType, $lookup['alias']);
        } catch (InvoiceRejected $e) {
            $invoice->update(['status' => 'draft', 'error_message' => mb_substr($e->getMessage(), 0, 500)]);
            $this->audit->log('invoice.rejected', $invoice, [], ['local_id' => $invoice->local_id, 'error' => $invoice->error_message]);
            throw new DomainException('Fatura kesilmedi. '.$e->getMessage());
        } catch (InvoiceUncertain $e) {
            $invoice->update(['status' => 'unknown', 'error_message' => mb_substr($e->getMessage(), 0, 500)]);
            $this->audit->log('invoice.uncertain', $invoice, [], ['local_id' => $invoice->local_id, 'error' => $invoice->error_message]);
            throw new DomainException('Gönderimin sonucu alınamadı; fatura kesilmiş olabilir. "Uyumsoft\'tan sorgula" ile kontrol edin. ('.$e->getMessage().')');
        }

        return $this->finalize($invoice, $result);
    }

    /**
     * Seçilen taslakları sırayla gönderir.
     *
     * @param  list<int>  $ids
     * @return array{sent: list<string>, failed: array<string, string>}
     */
    public function sendMany(array $ids): array
    {
        $sent = [];
        $failed = [];
        foreach (Invoice::with('order')->whereIn('id', array_slice(array_map('intval', $ids), 0, 50))->where('status', 'draft')->where('source', 'system')->get() as $invoice) {
            try {
                $sent[] = $this->send($invoice)->invoice_no;
            } catch (DomainException $e) {
                $failed[$invoice->order->order_no] = $e->getMessage();
            }
        }

        return compact('sent', 'failed');
    }

    /**
     * Otomatik kesim (zamanlanmış komut): ayar açıksa, açıldığı andan sonra ödemesi alınan / yola çıkan siparişlere
     * satış faturası hazırlanıp gönderilir. Sorun çıkan taslak "Sorunlu"da kalır ve kendiliğinden yeniden denenmez.
     *
     * @return array{sent: int, failed: int}
     */
    public function autoIssue(int $limit = 20): array
    {
        $settings = $this->settings->record();
        if (! $settings->auto_issue || ! $settings->auto_since) {
            return ['sent' => 0, 'failed' => 0];
        }
        $since = $settings->auto_since;
        $orders = self::awaitingSale()
            ->where(fn ($q) => $q->where(fn ($p) => $p->where('payment_status', 'paid')->where('paid_at', '>=', $since))
                ->orWhere(fn ($s) => $s->whereIn('status', self::DELIVERED)->where('shipped_at', '>=', $since)))
            ->orderBy('id')->limit($limit)->get();

        $sent = $failed = 0;
        foreach ($orders as $order) {
            $at = self::triggeredAt($order);
            if (! $at || $at->lt($since)) {
                continue; // ödeme / sevk otomatik kesim açılmadan önceydi: toptancı kendisi keser
            }
            try {
                $invoice = $this->createSale($order);
                if ($invoice->isDraft() && $invoice->error_message === null) {
                    $this->send($invoice);
                    $sent++;
                }
            } catch (DomainException $e) {
                $failed++;
            } catch (\Throwable $e) {
                report($e);
                $failed++;
            }
        }

        return compact('sent', 'failed');
    }

    /** Sonucu belirsiz kalan gönderimi sağlayıcıdan sorgular. @throws DomainException */
    public function check(Invoice $invoice): Invoice
    {
        if (! in_array($invoice->status, Invoice::UNSETTLED, true)) {
            throw new DomainException('Yalnızca sonucu belirsiz kalan fatura sorgulanabilir.');
        }
        try {
            $result = $this->providerFor($invoice)->find($invoice);
        } catch (InvoiceUncertain|InvoiceRejected $e) {
            throw new DomainException('Sağlayıcıya ulaşılamadı, biraz sonra yeniden deneyin. ('.$e->getMessage().')');
        }
        if (! $result) {
            $claimed = Invoice::whereKey($invoice->id)->whereIn('status', Invoice::UNSETTLED)->update([
                'status' => 'draft', 'error_message' => 'Sağlayıcıda bu fatura bulunamadı; kesilmemiş. Kontrol edip yeniden gönderebilirsiniz.', 'updated_at' => now(),
            ]);
            if ($claimed === 1) {
                $this->audit->log('invoice.not_found_at_provider', $invoice, [], ['local_id' => $invoice->local_id]);
            }

            return $invoice->refresh();
        }

        return $this->finalize($invoice->load(['lines', 'order']), $result);
    }

    /** Toptancı Uyumsoft portalında faturanın kesilmediğini gördüyse taslağa geri alır. @throws DomainException */
    public function release(Invoice $invoice): void
    {
        $claimed = Invoice::whereKey($invoice->id)->whereIn('status', Invoice::UNSETTLED)->update([
            'status' => 'draft', 'error_message' => 'Taslağa alındı (Uyumsoft\'ta kesilmediği kontrol edildi). Yeniden göndermeden önce Uyumsoft portalını bir kez daha kontrol edin.', 'updated_at' => now(),
        ]);
        if ($claimed !== 1) {
            throw new DomainException('Yalnızca sonucu belirsiz kalan fatura taslağa alınabilir.');
        }
        $this->audit->log('invoice.released', $invoice, [], ['local_id' => $invoice->local_id]);
    }

    /* ---------------- Elle kesilen fatura ---------------- */

    /**
     * Uyumsoft portalında elle kesilen (ya da e-Fatura mükellefi bayinin size kestiği iade) faturayı kaydeder; böylece
     * aynı siparişe buradan ikinci fatura kesilmez. Kalemler siparişten gelir; toplam elle yazılır ve farklıysa onay ister.
     *
     * @throws DomainException|ValidationException
     */
    public function attachManual(Order $order, string $kind, ?OrderReturn $return, array $input, ?UploadedFile $pdf): Invoice
    {
        $data = Validator::make($input + ['pdf' => $pdf], [
            'invoice_no' => ['required', 'string', 'regex:/^[A-Z0-9]{3}20\d{2}\d{9}$/'],
            'issue_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('Europe/Istanbul')->toDateString()],
            'document_type' => ['required', 'in:e_arsiv,e_fatura'],
            'total' => ['required', 'string', 'max:20'],
            'pdf' => ['nullable', 'file', 'mimes:pdf', 'max:'.self::MAX_PDF_KB],
        ], [
            'invoice_no.regex' => 'Fatura numarası GİB biçiminde olmalı: 3 karakter seri + yıl + 9 hane (ör. BYA2026000000012).',
        ], ['invoice_no' => 'fatura numarası', 'issue_date' => 'fatura tarihi', 'document_type' => 'belge tipi', 'total' => 'fatura toplamı', 'pdf' => 'PDF'])->validate();

        $total = Decimal::parse($data['total'], 2);
        if ($total === null || bccomp($total, '0', 2) <= 0) {
            throw ValidationException::withMessages(['total' => 'Fatura toplamını yazın (ör. 1.250,00).']);
        }
        if ($pdf && ! str_starts_with((string) file_get_contents($pdf->getRealPath(), false, null, 0, 5), '%PDF')) {
            throw ValidationException::withMessages(['pdf' => 'Dosya geçerli bir PDF değil.']);
        }
        if (! in_array($kind, ['sale', 'refund'], true)) {
            throw new DomainException('Geçersiz belge türü.');
        }
        if (Invoice::where('invoice_no', $data['invoice_no'])->exists()) {
            throw ValidationException::withMessages(['invoice_no' => 'Bu fatura numarası zaten kayıtlı.']);
        }

        $invoice = DB::transaction(function () use ($order, $kind, $return, $data, $total, $input) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($return && $return->order_id !== $order->id) {
                throw new DomainException('İade kaydı bu siparişe ait değil.');
            }
            if ($kind === 'sale') {
                if (! in_array($order->status, self::BILLABLE, true)) {
                    throw new DomainException('Satış faturası onaylanmış ve iptal edilmemiş siparişe eklenir.');
                }
                $lines = $this->saleLines($order);
                $expected = (string) $order->grand_total;
            } else {
                $sale = $this->saleInvoice($order) ?? throw new DomainException('Önce siparişin satış faturası kaydedilmelidir.');
                if (! $return && ($order->status !== 'cancelled' || OrderReturn::where('order_id', $order->id)->exists())) {
                    throw new DomainException('İade faturası iade kaydına eklenir; iade kaydı olmadan yalnızca iptal edilen siparişe eklenir.');
                }
                [$lines, $expected] = $return ? $this->returnLines($order, $return, $sale) : $this->fullRefundLines($sale);
            }
            $floor = $kind === 'refund' ? $this->saleInvoice($order)->issue_date->toDateString() : $order->created_at->timezone('Europe/Istanbul')->toDateString();
            if ($data['issue_date'] < $floor) {
                throw ValidationException::withMessages(['issue_date' => 'Fatura tarihi '.($kind === 'refund' ? 'satış faturasının' : 'siparişin').' tarihinden ('.date('d.m.Y', strtotime($floor)).') önce olamaz.']);
            }
            if (bccomp($total, $expected, 2) !== 0 && ! filter_var($input['confirm_difference'] ?? false, FILTER_VALIDATE_BOOL)) {
                throw ValidationException::withMessages(['total' => 'Yazdığınız toplam ('.Money::format($total).') '.($kind === 'refund' ? 'iade' : 'sipariş').' tutarından ('.Money::format($expected).') farklı. Doğruysa farkı onaylayın.']);
            }

            // Açık taslak varsa elle kesilen fatura onun yerini alır; gönderilmiş / sonucu belirsiz belge varsa eklenmez.
            $open = $this->openDocument($order, $kind, $return);
            if ($open && ! $open->isDraft()) {
                throw new DomainException('Bu '.($kind === 'sale' ? 'siparişin satış' : 'iadenin').' faturası zaten var ('.($open->invoice_no ?: $open->statusLabel()).').');
            }
            $open?->delete();

            $invoice = $this->create($order, $kind, $return, $lines, $expected, [
                'status' => 'sent', 'source' => 'manual', 'provider' => 'manual', 'document_type' => $data['document_type'],
                'invoice_no' => $data['invoice_no'], 'issue_date' => $data['issue_date'], 'sent_at' => now(),
                'sent_by_id' => $this->actor->get()['id'],
            ]);
            $invoice->update(['total' => $total]);

            return $invoice;
        });
        if ($pdf) {
            $this->storePdf($invoice, (string) file_get_contents($pdf->getRealPath()));
        }
        $this->audit->log('invoice.manual_attached', $invoice, [], [
            'order_id' => $order->id, 'kind' => $kind, 'invoice_no' => $invoice->invoice_no, 'document_type' => $invoice->document_type,
            'total' => (string) $invoice->total, 'expected_total' => (string) $invoice->expected_total, 'pdf' => $pdf !== null,
        ]);
        if ($pdf && $kind === 'sale') {
            $this->notifyDealer($invoice);
        }

        return $invoice;
    }

    /* ---------------- İptal ---------------- */

    /**
     * Gönderilmiş e-Arşiv faturasını iptal eder (7 gün). Elle kesilmiş fatura yalnızca kayıtta iptal edilir: iptali
     * Uyumsoft portalında da yapılmalıdır. İptal sonrası sipariş yeniden "Fatura bekleyen"e düşer.
     *
     * @throws DomainException|ValidationException
     */
    public function cancel(Invoice $invoice, string $reason): void
    {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 300) {
            throw ValidationException::withMessages(['cancel_reason' => 'İptal nedenini yazın (en fazla 300 karakter).']);
        }
        if ($invoice->status !== 'sent') {
            throw new DomainException('Yalnızca kesilmiş fatura iptal edilebilir.');
        }
        if ($invoice->kind === 'sale' && Invoice::where('order_id', $invoice->order_id)->where('kind', 'refund')->where('status', 'sent')->exists()) {
            throw new DomainException('Bu satış faturasına dayanan bir iade faturası var; önce iade faturasını iptal edin.');
        }
        if (! $invoice->isManual()) {
            if ($invoice->document_type !== 'e_arsiv') {
                throw new DomainException('e-Fatura buradan iptal edilemez: iptali bayiyle GİB portalından yürütün veya bayi size iade faturası kessin.');
            }
            if (! self::cancellable($invoice)) {
                throw new DomainException('e-Arşiv faturası düzenlendiği tarihten itibaren 7 gün içinde iptal edilebilir; bu süre '
                    .self::cancelDeadline($invoice)->format('d.m.Y').' tarihinde doldu. Düzeltme için iade faturası kesin.');
            }
            try {
                $this->providerFor($invoice)->cancel($invoice, $reason);
            } catch (InvoiceRejected|InvoiceUncertain $e) {
                throw new DomainException('Fatura iptal edilemedi. '.$e->getMessage());
            }
        }
        $invoice->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => $reason]);
        $this->audit->log('invoice.cancelled', $invoice, [], ['invoice_no' => $invoice->invoice_no, 'reason' => $reason, 'manual' => $invoice->isManual()]);
    }

    /* ---------------- Durum ve PDF ---------------- */

    /**
     * Kesilmiş faturanın sağlayıcıdaki son durumu: reddedildiyse "Reddedildi" (yeni belge hazırlanabilir), portaldan
     * iptal edildiyse "İptal", onaylandıysa bayiye bildirim. Sonuç: ok | pending | error | cancelled | other | missing.
     *
     * @throws DomainException sağlayıcıya ulaşılamazsa
     */
    public function syncStatus(Invoice $invoice): string
    {
        if ($invoice->status !== 'sent' || $invoice->isManual()) {
            throw new DomainException('Yalnızca buradan kesilmiş faturanın durumu sorgulanır.');
        }
        try {
            $info = $this->providerFor($invoice)->status($invoice);
        } catch (InvoiceUncertain|InvoiceRejected $e) {
            throw new DomainException('Sağlayıcıya ulaşılamadı, biraz sonra yeniden deneyin. ('.$e->getMessage().')');
        }
        if (! $info) {
            $invoice->update(['provider_checked_at' => now()]);

            return 'missing';
        }
        $invoice->update(['provider_status' => mb_substr($info['status'], 0, 30), 'provider_checked_at' => now()]);

        match ($info['state']) {
            'error' => $this->closeFromProvider($invoice, 'failed', $info),
            'cancelled' => $this->closeFromProvider($invoice, 'cancelled', $info),
            'ok' => $this->notifyDealer($invoice),
            default => null,
        };

        return $info['state'];
    }

    /**
     * Durumu sorgulanacak faturalar: son 10 günde Uyumsoft'tan kesilenler. Sonuçlanmamışlar her seferinde, sonuçlananlar
     * en fazla 15 dakikada bir sorgulanır.
     */
    public static function statusCandidates(): Builder
    {
        return Invoice::query()->where('status', 'sent')->where('provider', 'uyumsoft')->where('sent_at', '>=', now()->subDays(10))
            ->where(fn ($q) => $q->whereNull('provider_status')->orWhereIn('provider_status', ['NotPrepared', 'NotSend', 'Draft', 'Queued', 'Processing'])
                ->orWhereNull('provider_checked_at')->orWhere('provider_checked_at', '<', now()->subMinutes(15)))
            ->orderBy('provider_checked_at');
    }

    /** Sonucu belirsiz kalmış ve birkaç dakikadır bekleyen gönderimler (zamanlanmış komut Uyumsoft'tan sorgular). */
    public static function unsettledCandidates(): Builder
    {
        return Invoice::query()->where('provider', 'uyumsoft')->where(fn ($q) => $q
            ->where(fn ($u) => $u->where('status', 'unknown')->where('updated_at', '<', now()->subMinutes(2)))
            ->orWhere(fn ($s) => $s->where('status', 'sending')->where('updated_at', '<', now()->subMinutes(10))))
            ->where('updated_at', '>=', now()->subDays(3))->orderBy('updated_at');
    }

    /** Sağlayıcıdan PDF alınamadıysa yeniden ister. @throws DomainException */
    public function refetchPdf(Invoice $invoice): void
    {
        if ($invoice->status !== 'sent' || $invoice->isManual() || $invoice->pdf_path) {
            return;
        }
        try {
            $result = $this->providerFor($invoice)->find($invoice);
        } catch (InvoiceUncertain|InvoiceRejected $e) {
            throw new DomainException('Sağlayıcıya ulaşılamadı, biraz sonra yeniden deneyin. ('.$e->getMessage().')');
        }
        if (! $result || $result['pdf'] === '') {
            throw new DomainException('PDF henüz alınamadı; biraz sonra yeniden deneyin.');
        }
        $this->storePdf($invoice, $result['pdf']);
    }

    /** Taslağın önizleme PDF'i ("geçerli fatura değildir"). */
    public function preview(Invoice $invoice): string
    {
        return app(InvoicePdf::class)->render($invoice, 'ÖNİZLEME — GEÇERLİ FATURA DEĞİLDİR');
    }

    /** Kesilmiş faturanın kayıtlı PDF'i (yoksa null). */
    public static function pdfContents(Invoice $invoice): ?string
    {
        if (! $invoice->pdf_path || ! Storage::disk('local')->exists($invoice->pdf_path)) {
            return null;
        }

        return Storage::disk('local')->get($invoice->pdf_path);
    }

    /* ---------------- İç işler ---------------- */

    private function finalize(Invoice $invoice, array $result): Invoice
    {
        $claimed = Invoice::whereKey($invoice->id)->whereIn('status', Invoice::UNSETTLED)->update([
            'status' => 'sent', 'invoice_no' => $result['invoice_no'], 'ettn' => $result['ettn'],
            'sent_at' => now(), 'error_message' => null, 'updated_at' => now(),
        ]);
        $invoice->refresh();
        if ($claimed !== 1) {
            return $invoice; // başka bir işlem (sorgu / zamanlanmış komut) sonucu zaten işledi
        }
        if ($result['pdf'] !== '') {
            $this->storePdf($invoice, $result['pdf']);
        }
        $this->audit->log('invoice.sent', $invoice, [], [
            'order_id' => $invoice->order_id, 'invoice_no' => $invoice->invoice_no, 'kind' => $invoice->kind,
            'document_type' => $invoice->document_type, 'provider' => $invoice->provider, 'total' => (string) $invoice->total,
        ]);

        // Uyumsoft numarayı verdikten sonra GİB denetimini yapar; bayiye bildirim durum onaylanınca gider (syncStatus).
        if ($invoice->provider !== 'uyumsoft') {
            $this->notifyDealer($invoice);
        }

        return $invoice;
    }

    private function closeFromProvider(Invoice $invoice, string $status, array $info): void
    {
        $failed = $status === 'failed';
        $invoice->update($failed
            ? ['status' => 'failed', 'error_message' => mb_substr('Uyumsoft / GİB faturayı reddetti ('.$info['status'].'): '.($info['message'] ?? 'ayrıntı için Uyumsoft portalındaki kayıtlara bakın').' Belgeyi düzeltip yeniden hazırlayın.', 0, 500)]
            : ['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => 'Uyumsoft portalında iptal edildi']);
        $this->audit->log($failed ? 'invoice.failed' : 'invoice.cancelled_at_provider', $invoice, [], [
            'invoice_no' => $invoice->invoice_no, 'provider_status' => $info['status'], 'message' => $info['message'],
        ]);
    }

    /** "Faturanız hazır" bildirimi (bir kez; satış faturası, bildirim açık). İndirme bağlantısı panele girişle açılır. */
    private function notifyDealer(Invoice $invoice): void
    {
        if ($invoice->dealer_notified_at || ! $invoice->notify_dealer || $invoice->kind !== 'sale' || $invoice->status !== 'sent') {
            return;
        }
        $claimed = Invoice::whereKey($invoice->id)->whereNull('dealer_notified_at')->update(['dealer_notified_at' => now()]);
        if ($claimed === 1) {
            try {
                $this->notifier->invoiceReady($invoice->load('order.relationship.dealer'));
            } catch (\Throwable $e) {
                report($e); // bildirim gitmese de fatura kesilmiştir
            }
        }
    }

    private function storePdf(Invoice $invoice, string $pdf): void
    {
        $path = 'businesses/'.$this->tenant->businessId()."/invoices/{$invoice->order_id}/".Str::random(40).'.pdf';
        Storage::disk('local')->put($path, $pdf);
        $old = $invoice->pdf_path;
        $invoice->update(['pdf_path' => $path]);
        if ($old && $old !== $path) {
            Storage::disk('local')->delete($old);
        }
    }

    /** Fatura kesimi sırasında belgenin ait olduğu sağlayıcı (deneme ile kesilen, ayar değişse de denemeden sorgulanır). */
    private function providerFor(Invoice $invoice): InvoiceProvider
    {
        return $invoice->provider === 'deneme' ? app(DenemeProvider::class) : $this->provider();
    }

    private function openDocument(Order $order, string $kind, ?OrderReturn $return): ?Invoice
    {
        return Invoice::where('order_id', $order->id)->where('kind', $kind)->whereNotIn('status', Invoice::CLOSED)
            ->when($kind === 'refund', fn ($q) => $return ? $q->where('order_return_id', $return->id) : $q->whereNull('order_return_id'))
            ->first();
    }

    private function saleInvoice(Order $order): ?Invoice
    {
        return Invoice::where('order_id', $order->id)->where('kind', 'sale')->where('status', 'sent')->with('lines')->latest('id')->first();
    }

    /**
     * Satış kalemleri: onaylanan miktarlar, siparişin KDV hariç birim fiyatı, iskonto (fiyat listesi + kampanya payı) ve
     * hesaplanmış tutarlar; teslimat ücreti ayrı hizmet kalemi. Toplam siparişin genel toplamıyla aynı olmalıdır.
     *
     * @throws DomainException
     */
    private function saleLines(Order $order): array
    {
        $lines = [];
        foreach ($order->items()->get() as $item) {
            if ($item->quantity_approved === null || bccomp((string) $item->quantity_approved, '0', 3) <= 0) {
                continue;
            }
            $lines[] = $this->line($item->id, $item->name, $item->sku, $item->unit_name, (string) $item->quantity_approved, (string) $item->unit_price,
                bcadd((string) $item->line_discount, (string) ($item->order_discount ?? '0'), 2), (string) $item->vat_rate,
                (string) $item->line_net, (string) $item->line_vat, (string) $item->line_total);
        }
        if ($lines === []) {
            throw new DomainException('Siparişte onaylanmış kalem yok; fatura kesilemez.');
        }
        if (($fee = $this->feeLine((string) ($order->delivery_fee ?? '0'))) !== null) {
            $lines[] = $fee;
        }
        $this->assertTotal($lines, (string) $order->grand_total, 'siparişin genel toplamıyla');

        return $lines;
    }

    /**
     * İade kalemleri: iade kaydının miktarı ve tutarları (cariye / karta iade edilenle aynı). Siparişin tamamı bu iadeyle
     * döndüyse teslimat ücreti de iade edildiği için satış faturasındaki teslimat kalemi eklenir.
     *
     * @return array{0: list<array>, 1: string}
     */
    private function returnLines(Order $order, OrderReturn $return, Invoice $sale): array
    {
        $items = $order->items()->get()->keyBy('id');
        $lines = [];
        foreach ($return->items()->orderBy('id')->get() as $ri) {
            $item = $items[$ri->order_item_id] ?? throw new DomainException('İade kalemi siparişte bulunamadı.');
            $subtotal = Decimal::round(bcmul((string) $item->unit_price, (string) $ri->quantity, 10), 2);
            $lines[] = $this->line($item->id, $item->name, $item->sku, $item->unit_name, (string) $ri->quantity, (string) $item->unit_price,
                bcsub($subtotal, (string) $ri->line_net, 2), (string) $item->vat_rate, (string) $ri->line_net, (string) $ri->line_vat, (string) $ri->line_total);
        }
        if ($lines === []) {
            throw new DomainException('İade kaydında kalem yok.');
        }
        $expected = (string) $return->grand_total;
        if ($this->returnIncludesFee($order, $return)) {
            $saleFee = $sale->lines->first(fn ($l) => $l->order_item_id === null);
            $fee = $saleFee
                ? $saleFee->only(['order_item_id', 'name', 'sku', 'unit_code', 'quantity', 'unit_price', 'discount', 'vat_rate', 'net_amount', 'vat_amount', 'line_total'])
                : $this->feeLine((string) $order->delivery_fee);
            if ($fee !== null) {
                $lines[] = $fee;
                $expected = bcadd($expected, (string) $fee['line_total'], 2);
            }
        }
        $this->assertTotal($lines, $expected, 'iade tutarıyla');

        return [$lines, $expected];
    }

    /** İade kaydı olmadan iptal edilen sipariş: satış faturasının tüm kalemleri. @return array{0: list<array>, 1: string} */
    private function fullRefundLines(Invoice $sale): array
    {
        $lines = $sale->lines->map(fn ($l) => $l->only(['order_item_id', 'name', 'sku', 'unit_code', 'quantity', 'unit_price', 'discount', 'vat_rate', 'net_amount', 'vat_amount', 'line_total']))->all();

        return [$lines, (string) $sale->total];
    }

    /** Bu iade siparişin son iadesiyse ve onunla birlikte tüm kalemler döndüyse teslimat ücreti de iade edilmiştir. */
    private function returnIncludesFee(Order $order, OrderReturn $return): bool
    {
        if (bccomp((string) ($order->delivery_fee ?? '0'), '0', 2) <= 0 || $order->status !== 'cancelled') {
            return false;
        }
        if ((int) OrderReturn::where('order_id', $order->id)->max('id') !== $return->id) {
            return false;
        }
        $order->load('items');

        return collect(ReturnService::returnable($order))->every(fn ($q) => bccomp($q, '0', 3) <= 0);
    }

    /** Teslimat ücreti (KDV dahil) → hizmet kalemi (Fatura Ayarları'ndaki KDV oranıyla). */
    private function feeLine(string $gross): ?array
    {
        if (bccomp($gross, '0', 2) <= 0) {
            return null;
        }
        $rate = (string) $this->settings->record()->delivery_vat;
        $split = InvoiceMath::fromGross($gross, $rate);

        return ['order_item_id' => null, 'name' => 'Teslimat hizmeti', 'sku' => null, 'unit_code' => 'ADET', 'quantity' => '1',
            'unit_price' => $split['net_amount'], 'discount' => '0.00', 'vat_rate' => $rate] + $split;
    }

    /**
     * Siparişin hesaplanmış tutarlarıyla kalem; tutarlar fatura kuralıyla yeniden hesaplanıp karşılaştırılır (bozuk veri
     * faturaya geçmez).
     *
     * @throws DomainException
     */
    private function line(?int $itemId, string $name, ?string $sku, ?string $unit, string $qty, string $unitPrice, string $discount, string $rate, string $net, string $vat, string $total): array
    {
        $check = InvoiceMath::line($qty, $unitPrice, $discount, $rate);
        if (bccomp($check['net_amount'], $net, 2) !== 0 || bccomp($check['vat_amount'], $vat, 2) !== 0 || bccomp($check['line_total'], $total, 2) !== 0 || bccomp($discount, '0', 2) < 0) {
            throw new DomainException("\"{$name}\" kaleminin tutarları tutarsız; fatura hazırlanmadı. Siparişi kontrol edin.");
        }

        return ['order_item_id' => $itemId, 'name' => mb_substr($name, 0, 255), 'sku' => $sku, 'unit_code' => $unit, 'quantity' => $qty,
            'unit_price' => $unitPrice, 'discount' => bcadd($discount, '0', 2), 'vat_rate' => $rate,
            'net_amount' => $net, 'vat_amount' => $vat, 'line_total' => $total];
    }

    /** @throws DomainException */
    private function assertTotal(array $lines, string $expected, string $what): void
    {
        $total = InvoiceMath::totals($lines)['total'];
        if (bccomp($total, $expected, 2) !== 0) {
            throw new DomainException('Fatura toplamı ('.Money::format($total).") {$what} (".Money::format($expected).') tutmuyor; fatura hazırlanmadı.');
        }
    }

    /** @param  array<string, mixed>  $overrides */
    private function create(Order $order, string $kind, ?OrderReturn $return, array $lines, string $expected, array $overrides = []): Invoice
    {
        $actor = $this->actor->get();
        $order->loadMissing('relationship.dealer');
        $dealer = $order->relationship->dealer;
        $totals = InvoiceMath::totals($lines);

        try {
            $invoice = Invoice::create($overrides + [
                'order_id' => $order->id, 'order_return_id' => $return?->id, 'kind' => $kind, 'status' => 'draft', 'source' => 'system',
                'local_id' => strtoupper(($kind === 'sale' ? 'B' : 'BI').'-'.$order->order_no.'-'.Str::random(6)),
                'buyer_name' => mb_substr(trim((string) $dealer->legal_name), 0, 160),
                'buyer_tax_number' => preg_replace('/\D/', '', (string) $dealer->tax_number) ?: null,
                'buyer_tax_office' => trim((string) $dealer->tax_office) ?: null,
                'buyer_address' => mb_substr(trim((string) ($dealer->address ?: $order->ship_address)), 0, 500),
                'buyer_district' => trim((string) ($dealer->district ?: $order->ship_district)) ?: null,
                'buyer_city' => trim((string) ($dealer->city ?: $order->ship_city)),
                'buyer_email' => $dealer->email ?: DealerUser::where('dealer_id', $dealer->id)->value('email'),
                'buyer_phone' => $dealer->phone ?: null,
                'issue_date' => now('Europe/Istanbul')->toDateString(),
                'subtotal' => $totals['subtotal'], 'vat_total' => $totals['vat_total'], 'total' => $totals['total'],
                'expected_total' => $expected,
                'notify_dealer' => (bool) $this->settings->record()->notify_dealer,
                'created_by_type' => $actor['type'], 'created_by_id' => $actor['id'],
            ]);
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'invoices_open_unique')) {
                throw new DomainException('Bu belge için açık bir fatura zaten var; sayfayı yenileyin.');
            }
            throw $e;
        }
        foreach (array_values($lines) as $i => $l) {
            $invoice->lines()->create(['position' => $i + 1] + array_intersect_key($l, array_flip([
                'order_item_id', 'name', 'sku', 'unit_code', 'quantity', 'unit_price', 'discount', 'vat_rate', 'net_amount', 'vat_amount', 'line_total',
            ])));
        }

        return $invoice;
    }

    private function audited(Invoice $invoice, string $kind): void
    {
        if ($invoice->wasRecentlyCreated) {
            $this->audit->log('invoice.draft_created', $invoice, [], ['order_id' => $invoice->order_id, 'kind' => $kind, 'local_id' => $invoice->local_id, 'total' => (string) $invoice->total]);
        }
    }

    /**
     * Gönderim öncesi son denetim: tutar bütünlüğü (kalemler = fatura toplamı = siparişin / iadenin güncel tutarı),
     * siparişin hâlâ faturalanabilir olması, satıcı ve alıcı bilgileri, tarih.
     *
     * @throws DomainException
     */
    private function assertSendable(Invoice $invoice): void
    {
        $order = $invoice->order;
        $lines = $invoice->lines->map(fn ($l) => $l->only(['net_amount', 'vat_amount', 'line_total', 'vat_rate']));
        $totals = InvoiceMath::totals($lines);
        if ($lines->isEmpty() || bccomp($totals['total'], '0', 2) <= 0) {
            throw new DomainException('Faturada en az bir kalem olmalı ve toplam sıfırdan büyük olmalı.');
        }
        if (bccomp($totals['total'], (string) $invoice->total, 2) !== 0 || bccomp($totals['total'], (string) $invoice->expected_total, 2) !== 0) {
            throw new DomainException('Fatura kalemleri toplamı fatura tutarıyla tutmuyor; taslağı silip yeniden hazırlayın.');
        }
        if ($invoice->kind === 'sale') {
            if (! self::billable($order)) {
                throw new DomainException('Sipariş iptal edilmiş veya henüz ödemesi alınmamış / yola çıkmamış; satış faturası kesilemez. Taslağı silebilirsiniz.');
            }
            if (bccomp((string) $order->grand_total, (string) $invoice->expected_total, 2) !== 0) {
                throw new DomainException('Taslak hazırlandıktan sonra siparişin tutarı değişmiş ('.Money::format($order->grand_total).'). Taslağı silip yeniden hazırlayın.');
            }
        } else {
            $sale = $this->saleInvoice($order);
            if (! $sale) {
                throw new DomainException('Satış faturası olmadan (veya iptal edilmişken) iade faturası gönderilemez.');
            }
            if ($sale->document_type === 'e_fatura') {
                throw new DomainException('Satış faturası e-Fatura: iade faturasını bayi keser. Bu taslağı silin.');
            }
            if ($invoice->order_return_id && ! OrderReturn::whereKey($invoice->order_return_id)->exists()) {
                throw new DomainException('İade kaydı bulunamadı; taslağı silin.');
            }
            if ($sent = self::cancelInsteadOfRefund($order)) {
                throw new DomainException("Satış faturası {$sent->invoice_no} henüz iptal edilebilir; iade faturası yerine satış faturasını iptal edin.");
            }
        }
        if ($problem = $this->settings->sellerProblem()) {
            throw new DomainException($problem);
        }
        if (! in_array(strlen((string) $invoice->buyer_tax_number), [10, 11], true)) {
            throw new DomainException('Bayinin vergi numarası (10 haneli VKN veya 11 haneli TCKN) eksik. Taslakta düzeltin.');
        }
        if (trim((string) $invoice->buyer_address) === '' || trim((string) $invoice->buyer_city) === '' || trim((string) $invoice->buyer_name) === '') {
            throw new DomainException('Bayinin unvanı, adresi veya ili eksik. Taslakta düzeltin.');
        }
        if (strlen((string) $invoice->buyer_tax_number) === 10 && trim((string) $invoice->buyer_tax_office) === '') {
            throw new DomainException('VKN ile kesilen faturada bayinin vergi dairesi gerekir. Taslakta düzeltin.');
        }
        $today = now('Europe/Istanbul')->toDateString();
        if ($invoice->issue_date->toDateString() > $today || $invoice->issue_date->toDateString() < now('Europe/Istanbul')->subDays(7)->toDateString()) {
            throw new DomainException('Fatura tarihi bugünden en fazla 7 gün geride olabilir. Taslakta tarihi güncelleyin.');
        }
    }

    /** @throws ValidationException */
    private function validateDraft(Invoice $invoice, array $input): array
    {
        $data = Validator::make($input, [
            'buyer_name' => ['required', 'string', 'max:160'],
            'buyer_tax_number' => ['required', 'regex:/^(\d{10}|\d{11})$/'],
            'buyer_tax_office' => ['nullable', 'string', 'max:80'],
            'buyer_address' => ['required', 'string', 'max:500'],
            'buyer_district' => ['nullable', 'string', 'max:60'],
            'buyer_city' => ['required', 'string', 'max:60'],
            'buyer_email' => ['nullable', 'email', 'max:160'],
            'buyer_phone' => ['nullable', 'string', 'max:30'],
            'note' => ['nullable', 'string', 'max:500'],
            'issue_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('Europe/Istanbul')->toDateString(), 'after_or_equal:'.now('Europe/Istanbul')->subDays(7)->toDateString()],
        ], [
            'buyer_tax_number.regex' => 'Vergi numarası 10 haneli VKN veya 11 haneli TCKN olmalı.',
            'issue_date.after_or_equal' => 'Fatura tarihi en fazla 7 gün geriye verilebilir.',
        ], [
            'buyer_name' => 'unvan', 'buyer_tax_number' => 'VKN / TCKN', 'buyer_tax_office' => 'vergi dairesi', 'buyer_address' => 'adres',
            'buyer_district' => 'ilçe', 'buyer_city' => 'il', 'buyer_email' => 'e-posta', 'buyer_phone' => 'telefon', 'note' => 'açıklama', 'issue_date' => 'fatura tarihi',
        ])->after(function ($v) use ($input) {
            $tax = is_string($input['buyer_tax_number'] ?? null) ? $input['buyer_tax_number'] : '';
            if (strlen($tax) === 10 && (! is_string($input['buyer_tax_office'] ?? null) || trim($input['buyer_tax_office']) === '')) {
                $v->errors()->add('buyer_tax_office', 'VKN ile kesilen faturada vergi dairesi gerekir.');
            }
        })->validate();

        $order = $invoice->order;
        $floor = $order->created_at->timezone('Europe/Istanbul')->toDateString();
        if ($invoice->kind === 'refund' && ($sale = $this->saleInvoice($order))) {
            $floor = max($floor, $sale->issue_date->toDateString());
        }
        if ($data['issue_date'] < $floor) {
            throw ValidationException::withMessages(['issue_date' => 'Fatura tarihi '.($invoice->kind === 'refund' ? 'satış faturasının' : 'siparişin').' tarihinden ('.date('d.m.Y', strtotime($floor)).') önce olamaz.']);
        }

        return [
            'buyer_name' => trim($data['buyer_name']), 'buyer_tax_number' => $data['buyer_tax_number'],
            'buyer_tax_office' => trim((string) ($data['buyer_tax_office'] ?? '')) ?: null, 'buyer_address' => trim($data['buyer_address']),
            'buyer_district' => trim((string) ($data['buyer_district'] ?? '')) ?: null, 'buyer_city' => trim($data['buyer_city']),
            'buyer_email' => $data['buyer_email'] ?? null, 'buyer_phone' => trim((string) ($data['buyer_phone'] ?? '')) ?: null,
            'note' => trim((string) ($data['note'] ?? '')) ?: null, 'issue_date' => $data['issue_date'],
            'notify_dealer' => filter_var($input['notify_dealer'] ?? false, FILTER_VALIDATE_BOOL),
        ];
    }

    /** @throws DomainException */
    private function assertDraft(Invoice $invoice): void
    {
        if (! $invoice->isDraft() || $invoice->isManual()) {
            throw new DomainException('Yalnızca taslak fatura değiştirilebilir veya gönderilebilir.');
        }
    }
}
