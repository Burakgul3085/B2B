<?php

namespace Tests\Feature;

use App\Domain\Inventory\StockService;
use App\Domain\Tenancy\TenantContext;
use App\Http\Middleware\RequirePlatformTwoFactor;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\VariantGroup;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/** Her panelin tüm GET sayfaları hatasız açılır ve yalnızca kendi menüsünü gösterir. */
class PagesRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_pages_render(): void
    {
        $business = $this->makeBusiness();
        $admin = $this->makePlatformAdmin();
        $admin->forceFill(['two_factor_secret' => (new Google2FA)->generateSecretKey(32), 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($admin, 'platform')->withSession([RequirePlatformTwoFactor::SESSION_KEY => true]);

        foreach (['/platform', '/platform/isletmeler', '/platform/isletmeler/yeni', "/platform/isletmeler/{$business->id}",
            "/platform/isletmeler/{$business->id}/duzenle", '/platform/denetim-kayitlari', '/platform/hesabim'] as $url) {
            $this->get($url)->assertOk()->assertSee('B2B Platform')->assertDontSee('İşletme Ayarları');
        }
    }

    public function test_business_pages_render(): void
    {
        $business = $this->makeBusiness();
        $this->actingAs($business->user, 'business');

        foreach (['/toptanci', '/toptanci/ayarlar', '/toptanci/hesabim'] as $url) {
            $this->get($url)->assertOk()->assertSee('İşletme Ayarları')->assertDontSee('Denetim Kayıtları');
        }
    }

    public function test_catalog_and_inventory_pages_render_empty_and_filled(): void
    {
        $business = $this->makeBusiness();
        $this->actingAs($business->user, 'business');

        $pages = ['/toptanci/urunler', '/toptanci/urunler/yeni', '/toptanci/urunler/toplu-fiyat', '/toptanci/urunler/ice-aktar',
            '/toptanci/kategori-ve-markalar', '/toptanci/kategori-ve-markalar?tab=brands', '/toptanci/kategori-ve-markalar?tab=variants',
            '/toptanci/kdv-ve-birimler', '/toptanci/stok', '/toptanci/stok/hareket', '/toptanci/stok/transfer',
            '/toptanci/stok-hareketleri', '/toptanci/depolar', '/toptanci/stok-sayimlari'];

        foreach ($pages as $url) {
            $this->get($url)->assertOk();
        }

        // Dolu veriyle
        $tenant = app(TenantContext::class);
        [$product, $count] = $tenant->runAs($business->id, function () use ($business) {
            $category = Category::create(['name' => 'Kalem']);
            Category::create(['name' => 'Jel Kalem', 'parent_id' => $category->id]);
            Brand::create(['name' => 'Marka']);
            $group = VariantGroup::create(['name' => 'Jel Kalem', 'attribute_names' => ['Renk']]);
            $product = Product::create([
                'sku' => 'P1', 'name' => 'Jel Kalem Mavi', 'category_id' => $category->id, 'variant_group_id' => $group->id, 'variant_values' => ['Renk' => 'Mavi'],
                'unit_id' => Unit::where('code', 'ADET')->value('id'), 'tax_rate_id' => TaxRate::where('rate', '20.00')->value('id'),
                'list_price' => '5', 'critical_stock_level' => '10',
            ]);
            $warehouse = Warehouse::find($business->settings()->value('default_warehouse_id'));
            app(StockService::class)->adjust($product->load('unit'), $warehouse, 'increase', '4');
            $count = StockCount::create(['warehouse_id' => $warehouse->id]);
            StockCountLine::create(['stock_count_id' => $count->id, 'product_id' => $product->id, 'counted_qty' => '5']);

            return [$product, $count];
        });

        foreach ([...$pages, "/toptanci/urunler/{$product->id}", "/toptanci/urunler/{$product->id}/duzenle", "/toptanci/stok-sayimlari/{$count->id}", '/toptanci?x=1'] as $url) {
            $this->get($url)->assertOk();
        }

        $this->get('/toptanci')->assertSee('Jel Kalem Mavi'); // kritik stok listesinde
        $this->get('/toptanci/urunler')->assertSee('Mavi');
    }

    public function test_business_settings_update(): void
    {
        $business = $this->makeBusiness();
        $this->actingAs($business->user, 'business');

        $this->put('/toptanci/ayarlar', [
            'display_name' => 'Yeni Ad', 'phone' => '0212 111 11 11', 'accent_color' => '#123456',
            'stock_display' => 'exact', 'ledger_post_on' => 'completion', 'default_payment_term_days' => 45, 'price_display' => 'incl_vat',
        ])->assertSessionHasNoErrors();

        $business->refresh()->load('settings');
        $this->assertSame('Yeni Ad', $business->display_name);
        $this->assertSame('completion', $business->settings->ledger_post_on);

        // Geçersiz renk kodu (stil enjeksiyonu denemesi) reddedilir.
        $this->put('/toptanci/ayarlar', [
            'display_name' => 'Yeni Ad', 'accent_color' => 'red;background:url(x)',
            'stock_display' => 'exact', 'ledger_post_on' => 'completion', 'default_payment_term_days' => 45, 'price_display' => 'incl_vat',
        ])->assertSessionHasErrors('accent_color');
    }

    public function test_dealer_pages_render(): void
    {
        $business = $this->makeBusiness();
        $dealer = $this->makeDealer();
        $this->relate($business, $dealer);
        $this->actingAs($dealer, 'dealer');

        foreach (['/bayi', '/bayi/hesabim', '/bayi/toptanci-sec'] as $url) {
            $this->get($url)->assertOk()->assertDontSee('İşletme Ayarları');
        }
    }

    public function test_public_home_does_not_link_platform_login(): void
    {
        $this->get('/')->assertOk()->assertSee('/toptanci/login')->assertSee('/bayi/login')->assertDontSee('/platform/login');
    }
}
