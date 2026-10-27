<?php

namespace Tests\Feature;

use App\Domain\Inventory\StockService;
use App\Domain\Store\Inventory\StoreStockService;
use App\Domain\Tenancy\TenantContext;
use App\Http\Middleware\RequirePlatformTwoFactor;
use App\Models\Brand;
use App\Models\Category;
use App\Models\DealerApplication;
use App\Models\DealerGroup;
use App\Models\DealerProductPrice;
use App\Models\DiscountRule;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\Store\StoreAdmin;
use App\Models\Store\StoreBrand;
use App\Models\Store\StoreCategory;
use App\Models\Store\StoreProduct;
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

        $storeAdmin = StoreAdmin::create(['name' => 'Mağaza Süper', 'email' => 'super@magaza.local', 'password' => self::PASSWORD, 'is_super' => true]);

        foreach (['/platform', '/platform/isletmeler', "/platform/isletmeler/{$business->id}",
            "/platform/isletmeler/{$business->id}/duzenle", '/platform/denetim-kayitlari', '/platform/hesabim',
            '/platform/magaza-yoneticileri', '/platform/magaza-yoneticileri/yeni', "/platform/magaza-yoneticileri/{$storeAdmin->id}"] as $url) {
            $this->get($url)->assertOk()->assertSee('B2B Platform')->assertDontSee('İşletme Ayarları');
        }

        // Tek toptancı: işletme varken yeni işletme formu açılmaz (docs/13-magaza-mimarisi.md §4).
        $this->get('/platform/isletmeler/yeni')->assertRedirect('/platform/isletmeler');
    }

    public function test_platform_new_business_form_renders_when_there_is_no_business(): void
    {
        $this->actingAsPlatformAdmin();

        $this->get('/platform/isletmeler/yeni')->assertOk();
    }

    public function test_store_admin_pages_render(): void
    {
        $super = StoreAdmin::create(['name' => 'Mağaza Süper', 'email' => 'super@magaza.local', 'password' => self::PASSWORD, 'is_super' => true]);
        $admin = StoreAdmin::create(['name' => 'Ayşe Yönetici', 'email' => 'ayse@magaza.local', 'password' => self::PASSWORD]);
        $this->actingAs($super, 'store_admin');

        foreach (['store_admin.dashboard', 'store_admin.activity', 'store_admin.account', 'store_admin.admins.index', 'store_admin.admins.create'] as $name) {
            $this->get(route($name))->assertOk()->assertSee(config('store.name'))->assertDontSee('B2B Platform');
        }
        $this->get(route('store_admin.admins.edit', $admin))->assertOk()->assertSee('Ayşe Yönetici');

        // Katalog (M2): boş ve dolu hâlleri
        $this->get(route('store_admin.products.index'))->assertOk()->assertSee('Henüz ürün yok');
        $root = StoreCategory::create(['name' => 'Kalemler', 'slug' => 'kalemler']);
        $child = StoreCategory::create(['name' => 'Jel Kalem', 'slug' => 'jel-kalem', 'parent_id' => $root->id]);
        $brand = StoreBrand::create(['name' => 'Pensan', 'slug' => 'pensan']);
        $single = StoreProduct::create(['name' => 'Defter', 'slug' => 'defter', 'category_id' => $root->id]);
        $single->variants()->create(['sku' => 'D-1', 'price' => '10.00', 'vat_rate' => '20']);
        $multi = StoreProduct::create(['name' => 'Jel Kalem 0.7', 'slug' => 'jel-kalem-07', 'category_id' => $child->id, 'brand_id' => $brand->id, 'variant_name' => 'Renk', 'is_published' => true]);
        $multi->variants()->create(['sku' => 'J-M', 'option_value' => 'Mavi', 'price' => '24.90', 'compare_at_price' => '29.90', 'vat_rate' => '20']);
        $multi->variants()->create(['sku' => 'J-S', 'option_value' => 'Siyah', 'price' => '26.90', 'vat_rate' => '20']);

        $this->get(route('store_admin.products.index'))->assertOk()->assertSee('Jel Kalem 0.7')->assertSee('Kalemler › Jel Kalem');
        $this->get(route('store_admin.products.create'))->assertOk();
        $this->get(route('store_admin.products.edit', $single))->assertOk()->assertSee('Fiyat ve stok kodu');
        $this->get(route('store_admin.products.edit', $multi))->assertOk()->assertSee('Renk seçenekleri')->assertSee('Siyah');
        $this->get(route('store_admin.categories.index'))->assertOk()->assertSee('Jel Kalem');
        $this->get(route('store_admin.categories.index', ['duzenle' => $child->id]))->assertOk()->assertSee('Düzenle: Jel Kalem');
        $this->get(route('store_admin.brands.index'))->assertOk()->assertSee('Pensan');
        $this->get(route('store_admin.products.import'))->assertOk();
        $this->get(route('store_admin.activity'))->assertOk();

        // Stok (M3)
        $stock = app(StoreStockService::class);
        $stock->receive($multi->variants()->first(), 12, 'İlk giriş');
        $stock->hold('checkout:x', [$multi->variants()->first()->id => 2]);
        $this->get(route('store_admin.stock.index'))->assertOk()->assertSee('J-M')->assertSee('Stok işlemi');
        $this->get(route('store_admin.stock.movements'))->assertOk()->assertSee('İlk giriş')->assertSee('Geri al');
        $this->get(route('store_admin.products.edit', $multi))->assertOk()->assertSee('2 ayrılmış');
        $this->get(route('store_admin.dashboard'))->assertOk()->assertSee('Kritik stok');
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
        ])->assertSessionHasNoErrors()->assertRedirect();

        $business->refresh()->load('settings');
        $this->assertSame('Yeni Ad', $business->display_name);
        $this->assertSame('completion', $business->settings->ledger_post_on);

        // Geçersiz renk kodu (stil enjeksiyonu denemesi) reddedilir.
        $this->put('/toptanci/ayarlar', [
            'display_name' => 'Yeni Ad', 'accent_color' => 'red;background:url(x)',
            'stock_display' => 'exact', 'ledger_post_on' => 'completion', 'default_payment_term_days' => 45, 'price_display' => 'incl_vat',
        ])->assertSessionHasErrors('accent_color');
    }

    public function test_dealer_management_and_pricing_pages_render(): void
    {
        $business = $this->makeBusiness();
        $dealer = $this->makeDealer();
        $rel = $this->relate($business, $dealer);
        $tenant = app(TenantContext::class);

        $this->actingAs($business->user, 'business');
        foreach (['/toptanci/basvurular', '/toptanci/basvurular?status=approved', '/toptanci/bayiler', '/toptanci/bayiler/yeni',
            '/toptanci/bayi-gruplari', '/toptanci/fiyatlandirma', '/toptanci/fiyatlandirma?tab=discounts'] as $url) {
            $this->get($url)->assertOk();
        }

        [$list, $application] = $tenant->runAs($business->id, function () use ($rel) {
            $list = PriceList::create(['name' => 'Liste']);
            $group = DealerGroup::create(['name' => 'Grup', 'price_list_id' => $list->id]);
            $rel->update(['dealer_group_id' => $group->id]);
            $product = Product::create(['sku' => 'P1', 'name' => 'Kalem', 'unit_id' => Unit::value('id'), 'tax_rate_id' => TaxRate::value('id'), 'list_price' => '5']);
            PriceListItem::create(['price_list_id' => $list->id, 'product_id' => $product->id, 'price' => '4']);
            DealerProductPrice::create(['dealer_relationship_id' => $rel->id, 'product_id' => $product->id, 'price' => '3']);
            DiscountRule::create(['name' => 'Kural', 'scope' => 'product', 'product_id' => $product->id, 'target' => 'group', 'dealer_group_id' => $group->id, 'percent' => '5']);
            $rel->addresses()->create(['title' => 'Merkez', 'recipient_name' => 'A', 'phone' => '0212 000 00 00', 'city' => 'İst', 'district' => 'Fatih', 'address_line' => 'X']);
            $application = DealerApplication::create(['legal_name' => 'Başvuran', 'contact_name' => 'B', 'phone' => '1234567', 'email' => 'b@b.test',
                'city' => 'İst', 'district' => 'Fatih', 'address' => 'Y', 'consent_text_version' => 'v', 'consent_at' => now()]);

            return [$list, $application];
        });

        foreach (['/toptanci/basvurular', "/toptanci/basvurular/{$application->id}", '/toptanci/bayiler', "/toptanci/bayiler/{$rel->id}",
            "/toptanci/bayiler/{$rel->id}?tab=addresses", "/toptanci/bayiler/{$rel->id}?tab=prices", "/toptanci/bayiler/{$rel->id}?tab=preview",
            '/toptanci/bayi-gruplari', '/toptanci/fiyatlandirma', '/toptanci/fiyatlandirma?tab=discounts', "/toptanci/fiyat-listeleri/{$list->id}"] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get("/toptanci/bayiler/{$rel->id}?tab=preview")->assertSee('Bayiye özel fiyat');

        $this->flushSession();
        $this->actingAs($dealer, 'dealer');
        $this->get('/bayi/sevk-adresleri')->assertOk()->assertSee('Merkez');
        $this->get('/bayi/isletme-profili')->assertOk()->assertSee($rel->dealer_code);

        $this->get("/basvuru/{$business->slug}")->assertOk();
        $this->get("/basvuru/{$business->slug}/tesekkurler")->assertOk();
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

    public function test_home_opens_dealer_login_without_linking_other_areas(): void
    {
        $this->get('/')->assertRedirect('/bayi/login');

        $this->get('/bayi/login')->assertOk()->assertSee('/basvuru')
            ->assertDontSee('/toptanci/login')->assertDontSee('/platform/login');
        $this->get('/toptanci/login')->assertOk()->assertDontSee('/platform/login');
    }
}
