<?php

namespace Tests\Feature;

use App\Console\Commands\ResetBusinessData;
use App\Domain\Store\Cart\CartService;
use App\Domain\Store\Inventory\StoreStockService;
use App\Domain\Store\Orders\CheckoutService;
use App\Models\Store\District;
use App\Models\Store\StoreAdmin;
use App\Models\Store\StoreBrand;
use App\Models\Store\StoreCarrier;
use App\Models\Store\StoreCategory;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

/** data:reset: mağaza ve B2B iş verisi silinir; ayarlar, yöneticiler, yasal metinler ve içerik kalır. */
class ResetBusinessDataTest extends TestCase
{
    use BuildsWorld;
    use RefreshDatabase;

    public function test_every_table_is_classified_once(): void
    {
        $tables = Schema::getTableListing(DB::getDatabaseName(), false);
        $this->assertEqualsCanonicalizing($tables, [...ResetBusinessData::KEEP, ...ResetBusinessData::DELETE]);
        $this->assertSame([], array_values(array_intersect(ResetBusinessData::KEEP, ResetBusinessData::DELETE)));
    }

    public function test_business_data_is_removed_and_settings_are_kept(): void
    {
        Storage::fake('local');
        $business = $this->makeBusiness();
        $this->buildWorld($business);
        $business->settings()->update(['transfer_enabled' => true, 'transfer_accounts' => json_encode([['bank' => 'Ziraat', 'holder' => 'Yapboz', 'iban' => 'TR000000000000000000000000']])]);
        $this->storeOrder();
        $admin = StoreAdmin::create(['name' => 'Yönetici', 'email' => 'yonetici@magaza.test', 'password' => self::PASSWORD, 'is_super' => true, 'is_active' => true]);
        $carrier = StoreCarrier::updateOrCreate(['name' => 'Aras Kargo'], ['tax_number' => '0720039666']);
        StoreSetting::put(['iyzico_mode' => 'live', 'transfer_accounts' => 'Ziraat | Yapboz | TR000000000000000000000000']);
        $disk = Storage::disk('local');
        foreach (['store/products/1/a.webp', 'store/invoices/1.pdf', 'store/print/abc/original.pdf', "businesses/{$business->id}/products/1/a.webp",
            "businesses/{$business->id}/messages/1/a.pdf", "businesses/{$business->id}/logo/logo.webp", 'store/site/logo.webp', 'store/blog/kapak.webp'] as $path) {
            $disk->put($path, 'x');
        }

        $kept = collect(ResetBusinessData::KEEP)->reject(fn ($t) => $t === 'audit_logs')->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()]);
        $before = collect(ResetBusinessData::DELETE)->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()]);
        foreach (['products', 'orders', 'dealers', 'messages', 'current_accounts', 'collections', 'store_orders', 'store_products', 'store_customers', 'store_payments'] as $table) {
            $this->assertGreaterThan(0, $before[$table], "kurulumda {$table} dolu olmalı");
        }

        // Deneme: yalnızca sayılar gösterilir, hiçbir şey silinmez.
        $this->artisan('data:reset', ['--dry-run' => true])->expectsOutputToContain('Deneme')->assertSuccessful();
        $this->assertSame($before->all(), collect(ResetBusinessData::DELETE)->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()])->all());

        $orderId = DB::table('store_orders')->max('id');
        $this->artisan('data:reset', ['--force' => true, '--no-backup' => true])->assertSuccessful();

        foreach (ResetBusinessData::DELETE as $table) {
            $this->assertSame(0, DB::table($table)->count(), $table);
        }
        foreach ($kept as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), "{$table} korunmalı");
        }
        $this->assertModelExists($admin);
        $this->assertSame('0720039666', $carrier->fresh()->tax_number);
        $this->assertSame('live', StoreSetting::get('iyzico_mode'));
        $this->assertNotNull($business->fresh()->user);
        $this->assertTrue((bool) $business->settings()->value('transfer_enabled'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'system.data_reset']);

        foreach (['store/products/1/a.webp', 'store/invoices/1.pdf', 'store/print/abc/original.pdf', "businesses/{$business->id}/products/1/a.webp", "businesses/{$business->id}/messages/1/a.pdf"] as $path) {
            $disk->assertMissing($path);
        }
        foreach (["businesses/{$business->id}/logo/logo.webp", 'store/site/logo.webp', 'store/blog/kapak.webp'] as $path) {
            $disk->assertExists($path);
        }

        // Sipariş numaraları kaldığı yerden sürer (kimlik sayacı sıfırlanmaz).
        $this->storeOrder();
        $this->assertGreaterThan($orderId, DB::table('store_orders')->max('id'));
    }

    private function storeOrder(): void
    {
        $category = StoreCategory::create(['name' => 'Kalemler', 'slug' => 'kalemler']);
        $brand = StoreBrand::create(['name' => 'Adel', 'slug' => 'adel']);
        $product = StoreProduct::create(['name' => 'Jel Kalem', 'slug' => 'jel-kalem', 'category_id' => $category->id, 'brand_id' => $brand->id, 'is_published' => true]);
        $variant = $product->variants()->create(['sku' => 'JEL-1', 'price' => '50.00', 'vat_rate' => '20']);
        app(StoreStockService::class)->receive($variant, 10);
        StoreSetting::put(['is_open' => '1', 'cod_enabled' => '1', 'cod_max' => '5000.00']);

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
        if (! DB::table('store_customers')->exists()) {
            DB::table('store_customers')->insert(['first_name' => 'Ayşe', 'last_name' => 'Yılmaz', 'email' => 'ayse@ornek.com', 'phone' => '05321234567',
                'password' => bcrypt(self::PASSWORD), 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('store_payments')->insert(['order_id' => $order->id, 'driver' => 'cod', 'status' => 'pending', 'amount' => '50.00', 'created_at' => now(), 'updated_at' => now()]);
    }
}
