<?php

namespace App\Console\Commands;

use App\Domain\Audit\AuditLogger;
use App\Domain\Platform\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

use function Laravel\Prompts\confirm;

/**
 * Canlıya gerçek verilerle başlamak için mağaza ve B2B'deki tüm iş verisini siler (yalnızca sunucudan, SSH ile).
 * Kalanlar: yöneticiler ve toptancı kullanıcısı, işletme ve mağaza ayarları (ödeme altyapısı, havale/EFT hesapları,
 * fatura, e-posta), kargo firmaları, yasal metinler, sabit sayfalar, blog, banner'lar, depo/birim/KDV tanımları,
 * işlem geçmişi ve kuyruk. Silinenler: ürün, kategori, marka, stok, sipariş, ödeme, fatura, iade, müşteri, bayi,
 * cari, mesaj ve bunların dosyaları.
 *
 * Kayıtlar DELETE ile silinir (TRUNCATE değil): kimlik sayaçları sıfırlanmaz, sipariş numaraları kaldığı yerden sürer.
 * Her tablo KEEP ya da DELETE listesindedir; listede olmayan tablo varsa komut çalışmaz (yeni tablo bilinçli sınıflanır).
 * --dry-run yalnızca silinecek kayıt sayılarını gösterir. Silmeden önce tam yedek (veritabanı + dosyalar) alınır.
 */
class ResetBusinessData extends Command
{
    protected $signature = 'data:reset {--dry-run : Yalnızca silinecek kayıt sayılarını göster} {--force : Onay sormadan sil} {--no-backup : Önce yedek alma (yalnızca testte)}';

    protected $description = 'Mağaza ve B2B iş verisini siler; ayarları, yöneticileri ve yasal metinleri korur (önce yedek alır)';

    /** Korunan tablolar. */
    public const KEEP = [
        // Altyapı
        'migrations', 'jobs', 'failed_jobs', 'job_batches', 'audit_logs', 'system_settings', 'platform_admins',
        // B2B: işletme, toptancı kullanıcısı ve ayarlar
        'businesses', 'business_users', 'business_settings', 'business_modules', 'business_counters', 'licenses',
        'email_settings', 'email_templates', 'warehouses', 'units', 'tax_rates', 'invoice_settings',
        // Mağaza: yöneticiler, ayarlar ve içerik
        'store_admins', 'store_settings', 'store_carriers', 'store_legal_documents', 'store_pages',
        'store_blog_categories', 'store_blog_posts', 'store_banners', 'tr_provinces', 'tr_districts',
    ];

    /** Silinen tablolar (yabancı anahtar denetimi silme süresince kapalıdır; sıra önemli değildir). */
    public const DELETE = [
        // Ortak
        'cache', 'cache_locks', 'sessions', 'auth_tokens', 'support_access_grants', 'email_logs', 'error_events',
        'app_notifications', 'attachments',
        // B2B: bayiler
        'dealers', 'dealer_users', 'dealer_addresses', 'dealer_applications', 'dealer_relationships', 'dealer_groups',
        // B2B: katalog ve stok
        'products', 'product_images', 'variant_groups', 'categories', 'brands', 'stock_movements', 'warehouse_stock',
        'stock_counts', 'stock_count_lines', 'import_jobs', 'import_errors', 'product_requests',
        // B2B: fiyat ve kampanya
        'price_lists', 'price_list_items', 'dealer_product_prices', 'discount_rules', 'promotions', 'promotion_images',
        'promotion_redemptions',
        // B2B: sipariş, iade, cari
        'carts', 'cart_items', 'orders', 'order_items', 'order_payments', 'order_status_history', 'order_returns',
        'order_return_items', 'order_return_requests', 'order_return_request_items', 'current_accounts',
        'current_account_movements', 'collections', 'invoices', 'invoice_lines',
        // B2B: iletişim ve vitrin
        'conversations', 'conversation_reads', 'messages', 'announcements', 'home_banners', 'showcases',
        'showcase_products', 'saved_reports',
        // Mağaza: katalog ve stok
        'store_products', 'store_product_variants', 'store_product_images', 'store_categories', 'store_brands',
        'store_stock_movements', 'store_stock_holds', 'store_stock_alerts', 'store_collections', 'store_collection_product',
        'store_blog_post_product',
        // Mağaza: sipariş, ödeme, fatura, iade
        'store_orders', 'store_order_items', 'store_order_events', 'store_order_consents', 'store_payments',
        'store_payment_codes', 'store_invoices', 'store_e_invoices', 'store_e_invoice_lines', 'store_credit_notes',
        'store_refunds', 'store_returns', 'store_return_items', 'store_return_photos', 'store_discounts',
        'store_discount_codes', 'store_discount_redemptions', 'store_print_jobs', 'store_print_files',
        // Mağaza: müşteriler ve etkileşim
        'store_customers', 'store_customer_addresses', 'store_carts', 'store_cart_items', 'store_favorites',
        'store_reviews', 'store_review_photos', 'store_product_questions', 'store_contact_messages',
        'store_contact_replies', 'store_newsletter_subscribers', 'store_newsletter_campaigns',
        'store_newsletter_deliveries', 'store_cod_blocklist', 'store_visitor_days',
        'store_saved_reports',
    ];

    /** Silinen kayıtların dosyaları (local disk). İşletme logosu, mağaza logosu, sayfa, blog ve banner görselleri kalır. */
    public const STORE_DIRECTORIES = ['store/products', 'store/brands', 'store/categories', 'store/invoices', 'store/print',
        'store/reviews', 'store/returns', 'store/discounts', 'store/collections', 'store/newsletter'];

    /** businesses/{id}/ altındaki klasörler. */
    public const BUSINESS_DIRECTORIES = ['products', 'brands', 'applications', 'messages', 'promotions', 'storefront', 'announcements', 'invoices'];

    public function handle(AuditLogger $audit, BackupService $backups): int
    {
        $tables = collect(Schema::getTableListing(DB::getDatabaseName(), false));
        $unknown = $tables->diff([...self::KEEP, ...self::DELETE])->values();
        if ($unknown->isNotEmpty()) {
            $this->error('Sınıflanmamış tablo var, hiçbir şey silinmedi: '.$unknown->implode(', '));

            return self::FAILURE;
        }

        $counts = collect(self::DELETE)->filter(fn ($t) => $tables->contains($t))->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()]);
        $this->table(['Silinecek tablo', 'Kayıt'], $counts->filter()->map(fn ($n, $t) => [$t, $n])->values()->all());
        $this->line('Toplam silinecek kayıt: '.$counts->sum());
        $this->line('Korunanlar: '.collect(self::KEEP)->map(fn ($t) => $t.' ('.($tables->contains($t) ? DB::table($t)->count() : '-').')')->implode(', '));

        if ($this->option('dry-run')) {
            $this->info('Deneme: hiçbir şey silinmedi.');

            return self::SUCCESS;
        }
        if (! $this->option('force') && ! confirm('Bu kayıtlar ve dosyaları kalıcı olarak silinsin mi? (Önce yedek alınır)', default: false)) {
            return self::FAILURE;
        }

        if (! $this->option('no-backup')) {
            $this->info('Yedek alındı: '.$backups->create());
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            DB::transaction(function () use ($counts) {
                foreach ($counts->keys() as $table) {
                    DB::table($table)->delete();
                }
            });
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $disk = Storage::disk('local');
        foreach (self::STORE_DIRECTORIES as $dir) {
            $disk->deleteDirectory($dir);
        }
        foreach ($disk->directories('businesses') as $business) {
            foreach (self::BUSINESS_DIRECTORIES as $dir) {
                $disk->deleteDirectory($business.'/'.$dir);
            }
        }

        Artisan::call('cache:clear');
        $audit->log('system.data_reset', null, [], ['deleted' => $counts->filter()->all()]);
        $this->info('Silindi: '.$counts->sum().' kayıt. Ayarlar, yöneticiler ve yasal metinler korundu.');

        return self::SUCCESS;
    }
}
