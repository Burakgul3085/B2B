<?php

namespace Tests\Feature\Store;

use App\Domain\Store\Cart\CartService;
use App\Domain\Store\Inventory\StoreStockService;
use App\Domain\Store\Orders\CheckoutService;
use App\Models\Store\District;
use App\Models\Store\StoreBrand;
use App\Models\Store\StoreCategory;
use App\Models\Store\StoreCollection;
use App\Models\Store\StoreCustomer;
use App\Models\Store\StoreDiscount;
use App\Models\Store\StoreOrder;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** store:reset-catalog: demo sipariş, ürün, kategori, marka ve stok verisi silinir; müşteriler, vitrin ve ayarlar kalır. */
class ResetStoreCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_catalog_orders_and_stock_are_removed_and_the_rest_is_kept(): void
    {
        Storage::fake('local');
        StoreSetting::put(['is_open' => '1', 'cod_enabled' => '1', 'cod_max' => '5000.00']);
        $parent = StoreCategory::create(['name' => 'Kalemler', 'slug' => 'kalemler']);
        $child = StoreCategory::create(['name' => 'Jel', 'slug' => 'jel', 'parent_id' => $parent->id]);
        $brand = StoreBrand::create(['name' => 'Adel', 'slug' => 'adel']);
        $product = StoreProduct::create(['name' => 'Jel Kalem', 'slug' => 'jel-kalem', 'category_id' => $child->id, 'brand_id' => $brand->id, 'is_published' => true]);
        $variant = $product->variants()->create(['sku' => 'JEL-1', 'price' => '50.00', 'vat_rate' => '20']);
        $stock = app(StoreStockService::class);
        $received = $stock->receive($variant, 10);
        $stock->reverse($received);
        $stock->receive($variant, 10);
        $product->images()->create(['path' => 'store/products/1/a.webp', 'sort' => 0]);
        Storage::disk('local')->put('store/products/1/a.webp', 'x');
        Storage::disk('local')->put('store/brands/adel.webp', 'x');

        $customer = StoreCustomer::create(['first_name' => 'Ayşe', 'last_name' => 'Yılmaz', 'email' => 'ayse@ornek.com', 'phone' => '05321234567', 'password' => self::PASSWORD]);
        $collection = StoreCollection::create(['title' => 'Okula dönüş', 'slug' => 'okula-donus']);
        $collection->products()->attach($product->id);
        $scoped = StoreDiscount::create(['name' => 'Kalemde %10', 'type' => 'percent', 'value' => '10', 'product_ids' => [$product->id]]);
        $general = StoreDiscount::create(['name' => 'Sepette %5', 'type' => 'percent', 'value' => '5']);

        $carts = app(CartService::class);
        $cart = $carts->createGuest();
        $carts->add($cart, $variant->id, 1);
        $checkout = app(CheckoutService::class);
        $draft = $checkout->validate([
            'email' => 'ayse@ornek.com',
            'address' => ['first_name' => 'Ayşe', 'last_name' => 'Yılmaz', 'phone' => '05321234567', 'province_id' => 26,
                'district_id' => District::where('province_id', 26)->where('name', 'Tepebaşı')->value('id'), 'address_line' => 'Atatürk Bulvarı No 5 D 2'],
            'billing_type' => 'individual', 'billing_same' => '1', 'delivery_method' => 'local', 'payment_method' => 'cod', 'consent' => '1',
        ], null, $carts->summary($cart));
        $order = $checkout->place($draft, null, $cart, '127.0.0.1', 'test');
        DB::table('store_cod_blocklist')->insert(['phone' => '05321234567', 'order_id' => $order->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('store_cod_blocklist')->insert(['phone' => '05550000000', 'reason' => 'elle eklendi', 'created_at' => now(), 'updated_at' => now()]);

        $this->artisan('store:reset-catalog', ['--force' => true, '--no-backup' => true])->assertSuccessful()
            ->expectsOutputToContain('Kalemde %10');

        foreach (['store_orders', 'store_order_items', 'store_order_events', 'store_products', 'store_product_variants', 'store_product_images',
            'store_categories', 'store_brands', 'store_stock_movements', 'store_collection_product'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), $table);
        }
        $this->assertSame(['05550000000'], DB::table('store_cod_blocklist')->pluck('phone')->all());
        Storage::disk('local')->assertMissing('store/products/1/a.webp');
        Storage::disk('local')->assertMissing('store/brands/adel.webp');

        // Kalanlar
        $this->assertModelExists($customer);
        $this->assertModelExists($collection);
        $this->assertFalse($scoped->fresh()->is_active);
        $this->assertNull($scoped->fresh()->product_ids);
        $this->assertTrue($general->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'store.catalog_reset']);

        // Sipariş numarası kaldığı yerden devam eder
        $next = StoreOrder::query()->getConnection()->selectOne("SELECT AUTO_INCREMENT AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'store_orders'")->n;
        $this->assertGreaterThan($order->id, (int) $next);
        $this->get('http://magaza.localhost/')->assertOk();
    }
}
