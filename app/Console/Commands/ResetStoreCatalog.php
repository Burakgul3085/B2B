<?php

namespace App\Console\Commands;

use App\Domain\Audit\AuditLogger;
use App\Domain\Platform\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

use function Laravel\Prompts\confirm;

/**
 * Mağazayı gerçek verilere hazırlar (yalnızca sunucudan, SSH ile): demo siparişleri ve bunlara bağlı her şeyi
 * (ödeme, fatura, iade, olay, onay, kupon kullanımı), ürünleri, seçenekleri, görselleri, kategorileri, markaları,
 * stok ve stok hareketlerini siler. Önce tam yedek alınır.
 * Kalanlar: müşteri hesapları ve adresleri (sepetleri boşalır), kampanya ve kuponlar (ürün/kategoriye bağlı olanlar
 * bağı kopunca kapatılır), seçkiler, banner'lar, blog, sayfalar, ayarlar, yöneticiler, işlem geçmişi.
 * Sipariş numaraları kaldığı yerden devam eder (YB + 100000 + kimlik; kimlik sayacı sıfırlanmaz).
 */
class ResetStoreCatalog extends Command
{
    protected $signature = 'store:reset-catalog {--force : Onay sormadan sil (SSH ile tek komut)} {--no-backup : Önce yedek alma (yalnızca testte)}';

    protected $description = 'Mağazadaki demo sipariş, ürün, kategori, marka ve stok verilerini siler (önce yedek alır)';

    /** Silinecek tablolar, sırayla (bağlı kayıtlar önce). */
    private const ORDER_TABLES = ['store_return_items', 'store_refunds', 'store_returns', 'store_credit_notes', 'store_invoices',
        'store_discount_redemptions', 'store_payments', 'store_order_consents', 'store_order_events', 'store_order_items', 'store_orders'];

    private const CATALOG_TABLES = ['store_stock_movements', 'store_stock_holds', 'store_stock_alerts', 'store_cart_items', 'store_favorites',
        'store_reviews', 'store_collection_product', 'store_blog_post_product', 'store_product_images', 'store_product_variants', 'store_products'];

    private const DIRECTORIES = ['store/products', 'store/brands', 'store/categories', 'store/invoices'];

    public function handle(AuditLogger $audit, BackupService $backups): int
    {
        $tables = [...self::ORDER_TABLES, ...self::CATALOG_TABLES, 'store_categories', 'store_brands'];
        $counts = collect($tables)->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()]);
        $blocked = DB::table('store_cod_blocklist')->whereNotNull('order_id')->count();
        $this->table(['Tablo', 'Kayıt'], $counts->map(fn ($n, $t) => [$t, $n])->values()->push(['store_cod_blocklist (siparişten)', $blocked])->all());

        if (! $this->option('force') && ! confirm('Bu kayıtlar kalıcı olarak silinsin mi? (Önce yedek alınır)', default: false)) {
            return self::FAILURE;
        }

        if (! $this->option('no-backup')) {
            $this->info('Yedek alındı: '.$backups->create());
        }

        $scoped = DB::transaction(function () {
            // Ürüne veya kategoriye bağlı kampanyalar bağ kopunca tüm ürünlere uygulanmasın diye kapatılır.
            $scoped = DB::table('store_discounts')->where(fn ($q) => $q->whereNotNull('product_ids')->where('product_ids', '!=', '[]')
                ->orWhere(fn ($q) => $q->whereNotNull('category_ids')->where('category_ids', '!=', '[]')))->pluck('name', 'id');
            DB::table('store_discounts')->whereIn('id', $scoped->keys())->update(['product_ids' => null, 'category_ids' => null, 'is_active' => false, 'updated_at' => now()]);

            DB::table('store_cod_blocklist')->whereNotNull('order_id')->delete();
            DB::table('store_stock_movements')->update(['reverses_id' => null]); // ters kayıt bağı (kendi tablosuna) önce çözülür
            foreach ([...self::ORDER_TABLES, ...self::CATALOG_TABLES] as $table) {
                DB::table($table)->delete();
            }
            DB::table('store_categories')->update(['parent_id' => null]);
            DB::table('store_categories')->delete();
            DB::table('store_brands')->delete();

            return $scoped;
        });

        foreach (self::DIRECTORIES as $dir) {
            Storage::disk('local')->deleteDirectory($dir);
        }
        $audit->log('store.catalog_reset', null, [], ['deleted' => $counts->filter()->all(), 'disabled_discounts' => $scoped->values()->all()]);

        $this->info('Silindi: '.$counts->sum().' kayıt.');
        if ($scoped->isNotEmpty()) {
            $this->warn('Ürüne/kategoriye bağlı olduğu için kapatılan kampanyalar: '.$scoped->implode(', ').'.');
        }

        return self::SUCCESS;
    }
}
