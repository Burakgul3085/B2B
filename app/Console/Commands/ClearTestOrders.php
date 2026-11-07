<?php

namespace App\Console\Commands;

use App\Domain\Audit\AuditLogger;
use App\Domain\Platform\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

use function Laravel\Prompts\confirm;

/**
 * Teslimden önce deneme verisini temizler (yalnızca sunucudan, SSH ile). data:reset'ten dardır: ürün, katalog, stok
 * miktarı ve tüm ayarlar kalır.
 *
 * Silinenler:
 *  - Mağaza: siparişler ve bağlı kayıtlar (kalem, olay, onay, ödeme, ödeme kodu, baskı işi), faturalar (e-Arşiv,
 *    elle yüklenen, iade faturası), iadeler ve fotoğrafları, para iadeleri, kupon kullanımları, siparişlerin stok hareketleri.
 *  - B2B: tüm bayiler (kullanıcı, adres, ticari koşul, başvuru, cari ve hareketleri, tahsilat, sepet, mesaj, bildirim,
 *    ürün talebi), siparişler, ödemeleri, iadeler ve iade talepleri, faturalar, kampanya kullanımları, siparişlerin stok hareketleri.
 * Kalanlar: iyzico / havale / Uyumsoft / e-posta / SMS ayarları (store_settings, business_settings, invoice_settings…),
 * yöneticiler ve toptancı kullanıcısı, ürünler, kategoriler, markalar, stok miktarları, kampanya ve kupon tanımları,
 * mağaza müşteri hesapları, yasal metinler, sayfalar, blog, banner'lar, işlem geçmişi, e-posta kayıtları.
 *
 * Stok: silinen sipariş / iade stok hareketlerinin ürün (B2B: depo + ürün, mağaza: varyant) bazında toplamı sıfır
 * olmalıdır (iptal edilmiş deneme siparişleri). Değilse stok miktarı geçmişle çelişeceği için hiçbir şey silinmez.
 *
 * Silme, yabancı anahtar denetimi AÇIKKEN tek transaction'da yapılır: listede unutulan bir bağ varsa veritabanı reddeder ve
 * her şey geri alınır. --dry-run aynı silmeyi yapıp geri alır (sayılar gerçektir, hiçbir şey değişmez, dosya silinmez).
 * Gerçek silmeden önce tam yedek alınır. Kayıtlar DELETE ile silinir; sipariş / fatura numaraları kaldığı yerden sürer.
 */
class ClearTestOrders extends Command
{
    protected $signature = 'data:clear-orders {--dry-run : Silmeyi deneyip geri al, yalnızca sayıları göster} {--force : Onay sormadan sil} {--no-backup : Önce yedek alma (yalnızca testte)}';

    protected $description = 'Mağaza ve B2B sipariş / fatura verisini ve bayileri siler; ürünleri, stok miktarlarını ve tüm ayarları korur';

    /** Bu komutun dokunduğu tablolar (dışındakilere hiçbir koşulda dokunulmaz). */
    public const TABLES = [
        // Mağaza
        'store_e_invoice_lines', 'store_e_invoices', 'store_invoices', 'store_credit_notes', 'store_refunds',
        'store_return_photos', 'store_return_items', 'store_returns', 'store_discount_redemptions', 'store_payment_codes',
        'store_order_consents', 'store_order_events', 'store_payments', 'store_order_items',
        'store_orders', 'store_print_jobs', 'store_print_files', 'store_stock_movements',
        // B2B
        'invoice_lines', 'invoices', 'promotion_redemptions', 'order_return_request_items', 'order_return_requests',
        'order_return_items', 'order_returns', 'current_account_movements', 'collections', 'order_payments',
        'order_status_history', 'order_items', 'orders', 'stock_movements', 'cart_items', 'carts', 'conversation_reads',
        'messages', 'conversations', 'attachments', 'app_notifications', 'product_requests', 'dealer_product_prices',
        'discount_rules', 'current_accounts', 'dealer_addresses', 'dealer_applications', 'auth_tokens',
        'dealer_relationships', 'dealer_users', 'dealers', 'saved_reports',
    ];

    /** B2B'de silinen kayıtlara bağlı ek dosya türleri (duyuru ekleri kalır). */
    public const ATTACHMENT_TYPES = ['message', 'order_return_request', 'dealer_application'];

    public function handle(AuditLogger $audit, BackupService $backups): int
    {
        $dry = (bool) $this->option('dry-run');
        if (! $dry && ! $this->option('force') && ! confirm('Deneme siparişleri, faturalar ve bayiler kalıcı olarak silinsin mi? (Önce yedek alınır)', default: false)) {
            return self::FAILURE;
        }
        if (! $dry && ! $this->option('no-backup')) {
            $this->info('Yedek alındı: '.$backups->create());
        }

        try {
            [$counts, $files] = $this->clear($dry);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());
            $this->error('Hiçbir şey silinmedi.');

            return self::FAILURE;
        }

        $this->table(['Tablo', 'Silinecek kayıt'], collect($counts)->filter()->map(fn ($n, $t) => [$t, $n])->values()->all());
        $this->line('Toplam kayıt: '.array_sum($counts).' · Dosya: '.count($files));

        if ($dry) {
            $this->info('Deneme: silme denendi ve geri alındı; hiçbir şey değişmedi.');

            return self::SUCCESS;
        }

        $disk = Storage::disk('local');
        foreach ($files as $path) {
            $disk->delete($path);
        }
        $audit->log('system.test_orders_cleared', null, [], ['deleted' => array_filter($counts), 'files' => count($files)]);
        $this->info('Silindi. Ürünler, stok miktarları ve tüm ayarlar korundu.');

        return self::SUCCESS;
    }

    /** @return array{0: array<string, int>, 1: list<string>} */
    private function clear(bool $dry): array
    {
        $counts = [];
        $files = [];
        $del = function (string $table, $query) use (&$counts) {
            $counts[$table] = ($counts[$table] ?? 0) + $query->delete();
        };

        DB::beginTransaction();
        try {
            /* ---------------- Mağaza ---------------- */
            $storeOrders = DB::table('store_orders')->pluck('id');
            $storeReturns = DB::table('store_returns')->pluck('id');
            $storeMoves = $this->linkedMovements('store_stock_movements', ['store_order', 'store_return']);
            $this->assertNetZero('store_stock_movements', $storeMoves, ['variant_id'], 'Mağaza');
            $printJobs = DB::table('store_print_jobs')->whereNotNull('order_id')->pluck('file_id', 'id');

            $files = [
                ...DB::table('store_e_invoices')->whereNotNull('pdf_path')->pluck('pdf_path'),
                ...DB::table('store_invoices')->whereNotNull('file_path')->pluck('file_path'),
                ...DB::table('store_credit_notes')->whereNotNull('file_path')->pluck('file_path'),
                ...DB::table('store_return_photos')->pluck('path'),
            ];

            $del('store_stock_movements', DB::table('store_stock_movements')->whereIn('id', $storeMoves)->orderByDesc('id'));
            $del('store_e_invoice_lines', DB::table('store_e_invoice_lines'));
            $del('store_e_invoices', DB::table('store_e_invoices'));
            $del('store_invoices', DB::table('store_invoices'));
            $del('store_credit_notes', DB::table('store_credit_notes'));
            $del('store_refunds', DB::table('store_refunds'));
            $del('store_return_photos', DB::table('store_return_photos'));
            $del('store_return_items', DB::table('store_return_items'));
            $del('store_returns', DB::table('store_returns')->whereIn('id', $storeReturns));
            $del('store_discount_redemptions', DB::table('store_discount_redemptions'));
            $del('store_payment_codes', DB::table('store_payment_codes')->whereNotNull('order_id'));
            $del('store_order_consents', DB::table('store_order_consents'));
            $del('store_order_events', DB::table('store_order_events'));
            $del('store_payments', DB::table('store_payments'));
            $del('store_order_items', DB::table('store_order_items'));
            $del('store_orders', DB::table('store_orders')->whereIn('id', $storeOrders));
            if ($printJobs->isNotEmpty()) {
                $del('store_print_jobs', DB::table('store_print_jobs')->whereIn('id', $printJobs->keys()));
                // Yalnızca bu siparişlere ait, başka iş veya sepetle bağı kalmamış baskı dosyaları.
                $orphans = DB::table('store_print_files')->whereIn('id', $printJobs->values()->unique())->whereNull('cart_id')
                    ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('store_print_jobs')->whereColumn('store_print_jobs.file_id', 'store_print_files.id'));
                foreach ((clone $orphans)->get(['path', 'pdf_path', 'preview_path']) as $f) {
                    array_push($files, ...array_filter([$f->path, $f->pdf_path, $f->preview_path]));
                }
                $del('store_print_files', $orphans);
            }

            /* ---------------- B2B ---------------- */
            $b2bMoves = $this->linkedMovements('stock_movements', ['order', 'order_return']);
            $this->assertNetZero('stock_movements', $b2bMoves, ['warehouse_id', 'product_id'], 'B2B');
            $attachments = DB::table('attachments')->whereIn('attachable_type', self::ATTACHMENT_TYPES);
            array_push($files, ...DB::table('invoices')->whereNotNull('pdf_path')->pluck('pdf_path'), ...(clone $attachments)->pluck('stored_path'));

            $del('stock_movements', DB::table('stock_movements')->whereIn('id', $b2bMoves)->orderByDesc('id'));
            $del('invoice_lines', DB::table('invoice_lines'));
            $del('invoices', DB::table('invoices'));
            $del('promotion_redemptions', DB::table('promotion_redemptions'));
            $del('order_return_request_items', DB::table('order_return_request_items'));
            $del('order_return_requests', DB::table('order_return_requests'));
            $del('order_return_items', DB::table('order_return_items'));
            $del('current_account_movements', DB::table('current_account_movements')->orderByDesc('id'));
            $del('order_returns', DB::table('order_returns'));
            $del('collections', DB::table('collections'));
            $del('order_payments', DB::table('order_payments'));
            $del('order_status_history', DB::table('order_status_history'));
            $del('order_items', DB::table('order_items'));
            $del('orders', DB::table('orders'));
            $del('cart_items', DB::table('cart_items'));
            $del('carts', DB::table('carts'));
            $del('conversation_reads', DB::table('conversation_reads'));
            $del('messages', DB::table('messages'));
            $del('conversations', DB::table('conversations'));
            $del('attachments', $attachments);
            $del('app_notifications', DB::table('app_notifications')->whereNotNull('dealer_relationship_id'));
            $del('product_requests', DB::table('product_requests'));
            $del('dealer_product_prices', DB::table('dealer_product_prices'));
            $del('discount_rules', DB::table('discount_rules')->whereNotNull('dealer_relationship_id'));
            $del('current_accounts', DB::table('current_accounts'));
            $del('dealer_addresses', DB::table('dealer_addresses'));
            $del('dealer_applications', DB::table('dealer_applications'));
            $del('auth_tokens', DB::table('auth_tokens')->where('guard', 'dealer'));
            $del('saved_reports', DB::table('saved_reports')->where('owner_type', 'dealer'));
            $del('dealer_relationships', DB::table('dealer_relationships'));
            $del('dealer_users', DB::table('dealer_users'));
            $del('dealers', DB::table('dealers'));

            $dry ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e instanceof RuntimeException ? $e : new RuntimeException('Silme veritabanında durduruldu: '.$e->getMessage(), 0, $e);
        }

        return [$counts, array_values(array_unique(array_filter($files)))];
    }

    /**
     * Sipariş / iade kaynaklı stok hareketleri ve bunları geri alan ters kayıtlar.
     *
     * @return list<int>
     */
    private function linkedMovements(string $table, array $referenceTypes): array
    {
        $ids = DB::table($table)->whereIn('reference_type', $referenceTypes)->pluck('id')->all();
        if ($table !== 'store_stock_movements') {
            return $ids;
        }
        for ($added = $ids; $added !== [];) {
            $added = DB::table($table)->whereIn('reverses_id', $added)->whereNotIn('id', $ids)->pluck('id')->all();
            $ids = [...$ids, ...$added];
        }

        return $ids;
    }

    /** Silinecek hareketlerin ürün bazında toplamı sıfır değilse stok miktarı geçmişle çelişir: durdurulur. */
    private function assertNetZero(string $table, array $ids, array $keys, string $side): void
    {
        if ($ids === []) {
            return;
        }
        $open = DB::table($table)->whereIn('id', $ids)->groupBy($keys)->havingRaw('SUM(quantity) <> 0')
            ->selectRaw(implode(', ', $keys).', SUM(quantity) AS net')->get();
        if ($open->isNotEmpty()) {
            $list = $open->map(fn ($r) => implode('/', array_map(fn ($k) => $k.'='.$r->{$k}, $keys)).' net '.$r->net)->implode('; ');
            throw new RuntimeException("{$side}: iptal edilmemiş sipariş / iade stok hareketi var ({$list}). Bu kayıtlar silinirse stok miktarı geçmişle çelişir; önce siparişi iptal edin.");
        }
    }
}
