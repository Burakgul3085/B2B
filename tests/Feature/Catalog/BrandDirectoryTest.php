<?php

namespace Tests\Feature\Catalog;

use App\Domain\Ordering\OrderService;
use App\Models\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesOrders;
use Tests\TestCase;

/** Bayi Markalar sayfası: yalnızca sipariş verilebilir ürünü olan markalar, Türkçe harf grupları, öne çıkanlar ve logolar. */
class BrandDirectoryTest extends TestCase
{
    use CreatesOrders, RefreshDatabase;

    public function test_dealer_sees_orderable_brands_grouped_with_counts_and_links_to_catalog(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        [$faber, $cizgi, $ilke, $gizli] = $this->inTenant($business, fn () => [
            Brand::create(['name' => 'Faber-Castell']), Brand::create(['name' => 'Çizgi']), Brand::create(['name' => 'ilke']), Brand::create(['name' => 'Gizli Marka']),
        ]);
        $this->makeProduct($business, ['name' => 'Faber Kalem', 'brand_id' => $faber->id]);
        $this->makeProduct($business, ['name' => 'Faber Silgi', 'brand_id' => $faber->id]);
        $this->makeProduct($business, ['name' => 'Faber Arşiv', 'brand_id' => $faber->id])->forceFill(['archived_at' => now()])->saveQuietly();
        $this->makeProduct($business, ['brand_id' => $cizgi->id]);
        $this->makeProduct($business, ['brand_id' => $ilke->id]);
        $this->makeProduct($business, ['brand_id' => $gizli->id, 'visible_to_dealers' => false]);
        $other = $this->makeBusiness();
        $foreign = $this->inTenant($other, fn () => Brand::create(['name' => 'Yabancı Marka']));
        $this->makeProduct($other, ['brand_id' => $foreign->id]);

        $page = $this->actingAs($rel->dealer->user, 'dealer')->get(route('dealer.brands.index'))->assertOk();

        $page->assertSee('Faber-Castell')->assertSee('Çizgi')->assertSee('ilke')
            ->assertDontSee('Gizli Marka')->assertDontSee('Yabancı Marka')
            ->assertSee('id="harf-Ç"', false)->assertSee('id="harf-İ"', false)
            ->assertSee(route('dealer.catalog', ['brand' => [$faber->id]]), false);
        $this->assertSame(2, $page->viewData('brands')->firstWhere('id', $faber->id)->product_count, 'Arşivli ürün sayılmaz.');

        $this->get(route('dealer.catalog', ['brand' => [$faber->id]]))->assertOk()->assertSee('Faber Kalem')->assertSee('Faber Silgi')->assertDontSee('Faber Arşiv');
    }

    public function test_featured_brands_follow_recent_sales(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        [$a, $b] = $this->inTenant($business, fn () => [Brand::create(['name' => 'Az Satan']), Brand::create(['name' => 'Çok Satan'])]);
        $this->makeProduct($business, ['brand_id' => $a->id]);
        $this->makeProduct($business, ['brand_id' => $a->id]);
        $hit = $this->makeProduct($business, ['brand_id' => $b->id]);
        $this->stockUp($business, $hit, '100');
        $order = $this->placeOrder($business, $rel, [$hit->id => '10']);
        $this->inTenant($business, fn () => app(OrderService::class)->approve($order, [], $this->defaultWarehouse($business)->id, [], $business->user->id));

        $featured = $this->actingAs($rel->dealer->user, 'dealer')->get(route('dealer.brands.index'))->assertOk()->viewData('featured');
        $this->assertSame(['Çok Satan', 'Az Satan'], $featured->pluck('name')->all());
    }

    public function test_business_uploads_and_removes_brand_logo_shown_to_dealers(): void
    {
        Storage::fake('local');
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        [$brand, $second] = $this->inTenant($business, fn () => [Brand::create(['name' => 'Logolu']), Brand::create(['name' => 'Logosuz'])]);
        $this->makeProduct($business, ['brand_id' => $brand->id]);
        $this->makeProduct($business, ['brand_id' => $second->id]); // öne çıkanlar en az iki markayla gösterilir

        $img = imagecreatetruecolor(400, 160);
        ob_start();
        imagepng($img);
        $png = UploadedFile::fake()->createWithContent('logo.png', (string) ob_get_clean());

        $this->actingAs($business->user, 'business')->post(route('business.brands.logo.update', $brand), ['logo' => $png])->assertSessionHasNoErrors();
        $path = $brand->fresh()->logo_path;
        Storage::disk('local')->assertExists($path);
        $this->get(route('business.catalog.structure', ['tab' => 'brands']))->assertOk()->assertSee(route('business.brands.logo', $brand), false);

        $this->flushSession();
        $this->actingAs($rel->dealer->user, 'dealer');
        $this->get(route('dealer.brands.index'))->assertSee(route('dealer.brands.logo', $brand), false);
        $this->get(route('dealer.brands.logo', $brand))->assertOk()->assertHeader('Content-Type', 'image/webp');

        $this->flushSession();
        $this->actingAs($business->user, 'business')->delete(route('business.brands.logo.destroy', $brand))->assertSessionHasNoErrors();
        $this->assertNull($brand->fresh()->logo_path);
        Storage::disk('local')->assertMissing($path);
    }
}
