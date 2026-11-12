<?php

namespace Tests\Feature;

use App\Console\Commands\ClearTestOrders;
use App\Console\Commands\ResetBusinessData;
use App\Domain\Store\Cart\CartService;
use App\Domain\Store\Inventory\StoreStockService;
use App\Domain\Store\Orders\CheckoutService;
use App\Models\Store\District;
use App\Models\Store\StoreAdmin;
use App\Models\Store\StoreBrand;
use App\Models\Store\StoreCategory;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreProductVariant;
use App\Models\Store\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

/** data:clear-orders: deneme siparişleri, faturalar ve bayiler silinir; ürün, stok miktarı ve tüm ayarlar kalır. */
class ClearTestOrdersTest extends TestCase
{
    use BuildsWorld;
    use RefreshDatabase;

    /** Dokunulan tablolar, data:reset'in silebildiği tabloların alt kümesidir (korunan hiçbir tabloya dokunulmaz). */
    public function test_touches_only_resettable_tables(): void
    {
        $this->assertSame([], array_values(array_diff(ClearTestOrders::TABLES, ResetBusinessData::DELETE)));
        foreach (['invoice_settings', 'business_settings', 'store_settings', 'products', 'store_products', 'store_product_variants',
            'warehouse_stock', 'store_customers', 'promotions', 'store_discounts', 'store_admins', 'business_users'] as $table) {
            $this->assertNotContains($table, ClearTestOrders::TABLES);
        }
    }

    public function test_clears_orders_invoices_and_dealers_and_keeps_settings_products_and_stock(): void
    {
        Storage::fake('local');
        $disk = Storage::disk('local');
        $business = $this->makeBusiness();
        $world = $this->buildWorld($business);
        $ids = $world['ids'];
        $warehouse = $ids['warehouse'];
        $product = $ids['product'];
        $business->settings()->update(['transfer_enabled' => true]);
        DB::table('invoice_settings')->insert(['business_id' => $business->id, 'driver' => 'uyumsoft', 'uyumsoft_mode' => 'live', 'uyumsoft_username' => 'Kullanici_WebServis',
            'series_earsiv' => 'BYA', 'series_efatura' => 'BYF', 'delivery_vat' => '20.00', 'created_at' => now(), 'updated_at' => now()]);
        StoreSetting::put(['iyzico_mode' => 'live', 'invoice_driver' => 'uyumsoft']);
        $admin = StoreAdmin::create(['name' => 'Yönetici', 'email' => 'yonetici@magaza.test', 'password' => self::PASSWORD, 'is_super' => true, 'is_active' => true]);
        [$variant, $storeOrderId] = $this->storeOrder();
        DB::table('store_e_invoices')->insert(['order_id' => $storeOrderId, 'kind' => 'sale', 'status' => 'sent', 'provider' => 'uyumsoft', 'document_type' => 'e_arsiv',
            'local_id' => 'S-1', 'buyer_type' => 'individual', 'buyer_name' => 'Ayşe Yılmaz', 'buyer_address' => 'A', 'buyer_city' => 'Eskişehir',
            'issue_date' => now()->toDateString(), 'expected_total' => '50.00', 'total' => '50.00', 'invoice_no' => 'EB12026000000001', 'pdf_path' => 'store/invoices/1/a.pdf', 'created_at' => now(), 'updated_at' => now()]);
        $disk->put('store/invoices/1/a.pdf', 'x');
        DB::table('invoices')->update(['pdf_path' => "businesses/{$business->id}/invoices/1/a.pdf"]);
        $disk->put("businesses/{$business->id}/invoices/1/a.pdf", 'x');
        $disk->put("businesses/{$business->id}/logo/logo.webp", 'x');
        // B2B siparişi onaylanmış gibi stok düşer (henüz iptal edilmemiş).
        $stockBefore = (string) DB::table('warehouse_stock')->where('warehouse_id', $warehouse)->where('product_id', $product)->value('quantity');
        $sale = ['business_id' => $business->id, 'warehouse_id' => $warehouse, 'product_id' => $product, 'reference_type' => 'order', 'reference_id' => $ids['order'], 'created_at' => now()];
        DB::table('stock_movements')->insert($sale + ['type' => 'sale', 'quantity' => '-1.000', 'balance_after' => '99.000']);
        $otherMoves = DB::table('stock_movements')->whereNull('reference_type')->orWhere('reference_type', '<>', 'order')->count();

        $tables = [...ClearTestOrders::TABLES, 'products', 'warehouse_stock', 'store_products', 'store_product_variants', 'store_customers', 'invoice_settings',
            'business_settings', 'store_settings', 'promotions', 'announcements', 'email_logs', 'business_users', 'store_admins', 'dealer_groups'];
        $snapshot = fn () => collect($tables)->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()])->all();
        $before = $snapshot();
        foreach (['orders', 'invoices', 'dealers', 'dealer_users', 'current_accounts', 'collections', 'messages', 'store_orders', 'store_payments', 'store_e_invoices'] as $table) {
            $this->assertGreaterThan(0, $before[$table], "kurulumda {$table} dolu olmalı");
        }

        // 1) İptal edilmemiş sipariş stoğu: hiçbir şey silinmez.
        $this->artisan('data:clear-orders', ['--force' => true, '--no-backup' => true])->expectsOutputToContain('iptal edilmemiş')->assertFailed();
        $this->assertSame($before, $snapshot());

        // Siparişler iptal edilir: B2B stoğu geri girer, mağaza satışı geri alınır.
        DB::table('stock_movements')->insert($sale + ['type' => 'sale_reversal', 'quantity' => '1.000', 'balance_after' => '100.000']);
        app(StoreStockService::class)->restock($variant->id, 1, "order:{$storeOrderId}:test", 'store_order', $storeOrderId);
        $variantStock = (int) $variant->fresh()->stock_quantity;
        $before = $snapshot();

        // 2) Deneme: silme denenir ve geri alınır.
        $this->artisan('data:clear-orders', ['--dry-run' => true])->expectsOutputToContain('Deneme')->assertSuccessful();
        $this->assertSame($before, $snapshot());
        $disk->assertExists("businesses/{$business->id}/invoices/1/a.pdf");

        // 3) Gerçek silme.
        $this->artisan('data:clear-orders', ['--force' => true, '--no-backup' => true])->assertSuccessful();

        foreach (['orders', 'order_items', 'order_payments', 'invoices', 'invoice_lines', 'order_return_requests', 'dealers', 'dealer_users', 'dealer_relationships',
            'dealer_addresses', 'dealer_applications', 'current_accounts', 'current_account_movements', 'collections', 'carts', 'cart_items', 'conversations',
            'messages', 'product_requests', 'dealer_product_prices', 'store_orders', 'store_order_items', 'store_payments', 'store_e_invoices',
            'store_order_events', 'store_order_consents'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), "{$table} silinmeli");
        }
        $after = $snapshot();
        foreach (['products', 'warehouse_stock', 'store_products', 'store_product_variants', 'store_customers', 'invoice_settings', 'business_settings',
            'store_settings', 'promotions', 'announcements', 'email_logs', 'business_users', 'store_admins', 'dealer_groups', 'discount_rules'] as $table) {
            $this->assertSame($before[$table], $after[$table], "{$table} korunmalı");
        }
        // Yalnızca bayi bildirimi ve silinen kayıtların ekleri gider; toptancı bildirimi ve duyuru eki kalır.
        $this->assertDatabaseHas('app_notifications', ['id' => $ids['business_notification']]);
        $this->assertDatabaseHas('attachments', ['id' => $ids['announcement_attachment']]);
        $this->assertDatabaseMissing('attachments', ['id' => $ids['message_attachment']]);
        $this->assertSame($otherMoves, DB::table('stock_movements')->count(), 'sipariş dışı stok hareketleri kalmalı');
        $this->assertSame($stockBefore, (string) DB::table('warehouse_stock')->where('warehouse_id', $warehouse)->where('product_id', $product)->value('quantity'));
        $this->assertSame($variantStock, (int) $variant->fresh()->stock_quantity);
        $this->assertSame(0, DB::table('store_stock_movements')->where('reference_type', 'store_order')->count());
        $this->assertGreaterThan(0, DB::table('store_stock_movements')->count(), 'stok girişi kalmalı');

        $this->assertSame(['uyumsoft', 'live', 'Kullanici_WebServis', 'BYA', 'BYF'], array_values((array) DB::table('invoice_settings')
            ->first(['driver', 'uyumsoft_mode', 'uyumsoft_username', 'series_earsiv', 'series_efatura'])));
        $this->assertSame('live', StoreSetting::get('iyzico_mode'));
        $this->assertSame('uyumsoft', StoreSetting::get('invoice_driver'));
        $this->assertTrue((bool) $business->settings()->value('transfer_enabled'));
        $this->assertModelExists($admin);
        $this->assertNotNull($business->fresh()->user);
        $this->assertDatabaseHas('audit_logs', ['action' => 'system.test_orders_cleared']);

        $disk->assertMissing("businesses/{$business->id}/invoices/1/a.pdf");
        $disk->assertMissing('store/invoices/1/a.pdf');
        $disk->assertExists("businesses/{$business->id}/logo/logo.webp");
    }

    /** @return array{0: StoreProductVariant, 1: int} */
    private function storeOrder(): array
    {
        $category = StoreCategory::create(['name' => 'Kalemler', 'slug' => 'kalemler']);
        $brand = StoreBrand::create(['name' => 'Adel', 'slug' => 'adel']);
        $product = StoreProduct::create(['name' => 'Jel Kalem', 'slug' => 'jel-kalem', 'category_id' => $category->id, 'brand_id' => $brand->id, 'is_published' => true]);
        $variant = $product->variants()->create(['sku' => 'JEL-1', 'price' => '50.00', 'vat_rate' => '20']);
        app(StoreStockService::class)->receive($variant, 10);
        StoreSetting::put(['is_open' => '1', 'local_hide_cargo' => '0', 'local_min_total' => null, 'local_free_threshold' => null, 'cod_enabled' => '1', 'cod_max' => '5000.00']);

        $carts = app(CartService::class);
        $cart = $carts->createGuest();
        $carts->add($cart, $variant->id, 1);
        $checkout = app(CheckoutService::class);
        $draft = $checkout->validate([
            'email' => 'ayse@ornek.com', 'create_account' => '1', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD,
            'address' => ['first_name' => 'Ayşe', 'last_name' => 'Yılmaz', 'phone' => '05321234567', 'province_id' => 26,
                'district_id' => District::where('province_id', 26)->where('name', 'Tepebaşı')->value('id'), 'address_line' => 'Atatürk Bulvarı No 5 D 2'],
            'billing_type' => 'individual', 'billing_same' => '1', 'delivery_method' => 'local', 'payment_method' => 'cod', 'consent' => '1',
        ], null, $carts->summary($cart));
        $order = $checkout->place($draft, null, $cart, '127.0.0.1', 'test');
        DB::table('store_payments')->insert(['order_id' => $order->id, 'driver' => 'cod', 'status' => 'pending', 'amount' => '50.00', 'created_at' => now(), 'updated_at' => now()]);
        if (! DB::table('store_stock_movements')->where('reference_type', 'store_order')->exists()) {
            app(StoreStockService::class)->sell([$variant->id => 1], "order:{$order->id}", 'store_order', $order->id);
        }

        return [$variant->fresh(), $order->id];
    }
}
