<?php

namespace Tests\Feature\Pricing;

use App\Models\Category;
use App\Models\DealerProductPrice;
use App\Models\DiscountRule;
use App\Models\Promotion;
use App\Models\PromotionImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesOrders;
use Tests\TestCase;

/** docs/03-is-kurallari.md §4.4 — bayi Kampanyalar sayfası, kampanya görselleri, indirimdeki ve kampanyalı ürünler. */
class PromotionShowcaseTest extends TestCase
{
    use CreatesOrders, RefreshDatabase;

    private function promotion($business, array $attributes): Promotion
    {
        return $this->inTenant($business, fn () => Promotion::create($attributes + ['name' => 'Kampanya', 'type' => 'percent', 'value' => '10', 'show_to_dealers' => true]));
    }

    public function test_business_uploads_cover_and_detail_images_safely(): void
    {
        Storage::fake('local');
        $business = $this->makeBusiness();
        $this->actingAs($business->user, 'business');
        $base = ['name' => 'Okul', 'kind' => 'campaign', 'type' => 'percent', 'value' => '10', 'target' => 'all', 'is_active' => '1',
            'description' => "Birinci satır\nİkinci satır"];

        // Görsel olmayan dosya alanında hata verir, hiçbir şey kaydedilmez.
        $this->post(route('business.promotions.store'), $base + ['cover' => UploadedFile::fake()->create('virus.jpg', 10, 'image/jpeg')])
            ->assertSessionHasErrors('cover');
        $this->assertSame(0, $this->inTenant($business, fn () => Promotion::count()));
        $this->assertSame([], Storage::disk('local')->allFiles());

        $this->post(route('business.promotions.store'), $base + [
            'cover' => UploadedFile::fake()->image('kapak.jpg', 1200, 675),
            'images' => [UploadedFile::fake()->image('a.jpg', 800, 800), UploadedFile::fake()->image('b.png', 800, 800)],
        ])->assertSessionHasNoErrors();
        $promotion = $this->inTenant($business, fn () => Promotion::with('images')->firstOrFail());
        $this->assertSame("Birinci satır\nİkinci satır", $promotion->description);
        $this->assertSame([true, false, false], $promotion->images->pluck('is_cover')->all());
        foreach ($promotion->images as $image) {
            Storage::disk('local')->assertExists($image->path);
            $this->assertStringEndsWith('.webp', $image->path);
        }
        $this->get(route('business.promotions.image', ['promotionImage' => $promotion->images[0], 'w' => 320]))->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->get(route('business.promotions.edit', $promotion))->assertOk()->assertSee(route('business.promotions.image', ['promotionImage' => $promotion->images[1], 'w' => 320]), false);

        // Kapak değişir, bir detay kaldırılır, sıra değişir; eski dosyalar silinir.
        [$oldCover, $first, $second] = $promotion->images->all();
        $this->put(route('business.promotions.update', $promotion), $base + [
            'cover' => UploadedFile::fake()->image('yeni.jpg', 1200, 675),
            'remove_images' => [$first->id],
            'image_sort' => [$second->id => 0],
        ])->assertSessionHasNoErrors();
        $images = $this->inTenant($business, fn () => $promotion->images()->get());
        $this->assertCount(2, $images);
        $this->assertNotSame($oldCover->path, $images[0]->path);
        $this->assertSame($second->id, $images[1]->id);
        Storage::disk('local')->assertMissing($oldCover->path);
        Storage::disk('local')->assertMissing($first->path);

        // En fazla 8 detay fotoğrafı.
        $this->put(route('business.promotions.update', $promotion), $base + [
            'images' => array_map(fn ($i) => UploadedFile::fake()->image("f{$i}.jpg", 100, 100), range(1, 8)),
        ])->assertSessionHasErrors('images');

        // Başka işletme görseli göremez.
        $this->flushSession();
        $this->actingAs($this->makeBusiness()->user, 'business')
            ->get(route('business.promotions.image', ['promotionImage' => $images[0]]))->assertNotFound();
        $this->flushSession();

        // Silinince dosyaları da silinir.
        $this->actingAs($business->user, 'business')->delete(route('business.promotions.destroy', $promotion))->assertRedirect();
        $this->assertSame(0, $this->inTenant($business, fn () => PromotionImage::count()));
        foreach ($images as $image) {
            Storage::disk('local')->assertMissing($image->path);
        }
    }

    public function test_dealer_sees_campaign_detail_gallery_and_scoped_products_only_when_allowed(): void
    {
        Storage::fake('local');
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $other = $this->orderingDealer($business);
        $category = $this->inTenant($business, fn () => Category::create(['name' => 'Defterler']));
        $child = $this->inTenant($business, fn () => Category::create(['name' => 'Spiralli', 'parent_id' => $category->id]));
        $inScope = $this->makeProduct($business, ['name' => 'Kareli Defter', 'category_id' => $child->id, 'dealer_price' => '20.00']);
        $outScope = $this->makeProduct($business, ['name' => 'Silgi Beyaz', 'dealer_price' => '5.00']);

        $promotion = $this->promotion($business, ['name' => 'Defter Şenliği', 'category_ids' => [$category->id], 'description' => "Okul sezonu\nStoklarla sınırlı",
            'target' => 'dealers', 'dealer_ids' => [$rel->id]]);
        $image = $this->inTenant($business, function () use ($promotion) {
            Storage::disk('local')->put('businesses/x/promotions/k.webp', UploadedFile::fake()->image('k.webp', 400, 225)->getContent());

            return $promotion->images()->create(['path' => 'businesses/x/promotions/k.webp', 'is_cover' => true]);
        });
        $ended = $this->promotion($business, ['name' => 'Bitmiş', 'ends_at' => now()->subDay()]);

        $this->actingAs($rel->dealer->user, 'dealer');
        $this->get(route('dealer.promotions.index'))->assertOk()->assertSee('Defter Şenliği')
            ->assertSee(route('dealer.promotions.image', ['promotionImage' => $image, 'w' => 640]), false);
        $this->get(route('dealer.promotions.show', $promotion))->assertOk()
            ->assertSee('Okul sezonu')->assertSee('Bu kampanyadaki ürünler')->assertSee('Kareli Defter')->assertDontSee('Silgi Beyaz');
        $this->get(route('dealer.promotions.image', ['promotionImage' => $image, 'w' => 320]))->assertOk();
        $this->get(route('dealer.promotions.show', $ended))->assertNotFound();

        // Katalog: kampanya filtresi ve kart rozeti; ürün sayfasında kampanya notu.
        $this->get(route('dealer.catalog', ['kampanya' => $promotion->id]))->assertOk()->assertSee('Kareli Defter')->assertDontSee('Silgi Beyaz')->assertSee('Kampanya: Defter Şenliği');
        $this->get(route('dealer.catalog', ['kampanyali' => 1]))->assertOk()->assertSee('Kareli Defter')->assertDontSee('Silgi Beyaz');
        $this->get(route('dealer.catalog'))->assertOk()->assertSee('Defter Şenliği: %10 indirim', false);
        $this->get(route('dealer.catalog.show', $inScope))->assertOk()->assertSee('Defter Şenliği</b> kampanyasında', false);
        $this->get(route('dealer.catalog.show', $outScope))->assertOk()->assertDontSee('kampanyasında');
        $this->get(route('dealer.catalog', ['kampanya' => $ended->id]))->assertNotFound();

        // Hedefte olmayan bayi ne sayfayı ne görseli açabilir.
        $this->flushSession();
        $this->actingAs($other->dealer->user, 'dealer');
        $this->get(route('dealer.promotions.show', $promotion))->assertNotFound();
        $this->get(route('dealer.promotions.image', ['promotionImage' => $image]))->assertNotFound();
        $this->get(route('dealer.catalog', ['kampanya' => $promotion->id]))->assertNotFound();
    }

    public function test_discounted_products_follow_dealer_pricing_rules(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $category = $this->inTenant($business, fn () => Category::create(['name' => 'Kalemler']));
        $child = $this->inTenant($business, fn () => Category::create(['name' => 'Tükenmez', 'parent_id' => $category->id]));
        $byProduct = $this->makeProduct($business, ['name' => 'Ürün İndirimli', 'dealer_price' => '10.00']);
        $byCategory = $this->makeProduct($business, ['name' => 'Kategori İndirimli', 'category_id' => $child->id, 'dealer_price' => '10.00']);
        $special = $this->makeProduct($business, ['name' => 'Özel Fiyatlı', 'category_id' => $child->id, 'dealer_price' => '10.00']);
        $plain = $this->makeProduct($business, ['name' => 'İndirimsiz Ürün', 'dealer_price' => '10.00']);
        $otherGroupOnly = $this->makeProduct($business, ['name' => 'Başka Bayiye', 'dealer_price' => '10.00']);
        $this->inTenant($business, function () use ($rel, $byProduct, $category, $special, $otherGroupOnly) {
            DiscountRule::create(['name' => 'Ürün', 'scope' => 'product', 'product_id' => $byProduct->id, 'target' => 'all', 'percent' => '15']);
            DiscountRule::create(['name' => 'Kategori', 'scope' => 'category', 'category_id' => $category->id, 'target' => 'dealer', 'dealer_relationship_id' => $rel->id, 'percent' => '5']);
            DiscountRule::create(['name' => 'Geçmiş', 'scope' => 'product', 'product_id' => $otherGroupOnly->id, 'target' => 'all', 'percent' => '5', 'ends_on' => now()->subDays(3)->toDateString()]);
            DealerProductPrice::create(['dealer_relationship_id' => $rel->id, 'product_id' => $special->id, 'price' => '8.00']);
        });

        $this->actingAs($rel->dealer->user, 'dealer');
        $this->get(route('dealer.promotions.index'))->assertOk()->assertSee('İndirimdeki ürünler')
            ->assertSee('Ürün İndirimli')->assertSee('Kategori İndirimli')->assertSee('%15 indirim')
            ->assertDontSee('Özel Fiyatlı')->assertDontSee('İndirimsiz Ürün')->assertDontSee('Başka Bayiye');
        $this->get(route('dealer.catalog', ['indirim' => 1]))->assertOk()->assertSee('İndirimdekiler')
            ->assertSee('Ürün İndirimli')->assertSee('Kategori İndirimli')->assertDontSee('Özel Fiyatlı')->assertDontSee('İndirimsiz Ürün');

        // Üst menü sırası.
        $this->get(route('dealer.home'))->assertOk()->assertSeeInOrder(['Ana Sayfa', 'Tüm Ürünler', 'Yeni Ürünler', 'Kampanyalar', 'Markalar', 'Siparişlerim', 'Cari Hesap']);
    }
}
