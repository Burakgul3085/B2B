<?php

namespace Tests\Feature\Storefront;

use App\Models\DealerGroup;
use App\Models\HomeBanner;
use App\Models\Showcase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesOrders;
use Tests\TestCase;

/** Bayi ana sayfası (vitrin): slayt yükleme, görünürlük, video, ürün vitrinleri ve giriş yönlendirmesi. */
class StorefrontTest extends TestCase
{
    use CreatesOrders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_dealer_lands_on_home_with_banners_and_showcase_products(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $product = $this->makeProduct($business, ['name' => 'Vitrin Defteri']);
        $this->stockUp($business, $product, '50');

        $this->actingAs($business->user, 'business');
        $this->get(route('business.storefront.index'))->assertOk()->assertSee('Yeni slayt');
        $this->post(route('business.storefront.banners.store'), [
            'media' => $this->png(), 'title' => 'Okul sezonu başladı', 'subtitle' => 'Size özel fiyatlar',
            'button_label' => 'İncele', 'link_type' => 'catalog', 'text_position' => 'left', 'is_active' => '1',
        ])->assertSessionHasNoErrors()->assertRedirect(route('business.storefront.index'));
        $this->post(route('business.storefront.showcases.store'), ['name' => 'Önerdiklerimiz', 'type' => 'manual', 'product_limit' => 8, 'is_active' => '1'])
            ->assertSessionHasNoErrors();
        $showcase = $this->inTenant($business, fn () => Showcase::sole());
        $this->post(route('business.storefront.showcases.products.store', $showcase), ['product_id' => $product->id])->assertSessionHasNoErrors();
        $banner = $this->inTenant($business, fn () => HomeBanner::sole());
        $this->assertSame('image/webp', $banner->media_mime);

        // Bayi giriş yapınca ana sayfaya düşer; slayt ve vitrin ürünü görünür.
        $this->flushSession();
        $this->post(route('dealer.login.store'), ['email' => $rel->dealer->user->email, 'password' => self::PASSWORD])->assertRedirect(route('dealer.home'));
        $this->get(route('dealer.home'))->assertOk()
            ->assertSee('Okul sezonu başladı')->assertSee('Önerdiklerimiz')->assertSee('Vitrin Defteri')
            ->assertSee(route('dealer.storefront.media', $banner), false);
        $this->get(route('dealer.storefront.media', $banner))->assertOk()->assertHeader('Content-Type', 'image/webp');

        // "Tümünü gör": katalog yalnızca vitrindeki ürünleri listeler.
        $this->makeProduct($business, ['name' => 'Vitrin Dışı Kalem']);
        $this->get(route('dealer.catalog', ['showcase' => $showcase->id]))->assertOk()->assertSee('Vitrin Defteri')->assertDontSee('Vitrin Dışı Kalem');
    }

    public function test_group_targeting_dates_and_inactive_banners_are_respected(): void
    {
        $business = $this->makeBusiness();
        $inGroup = $this->orderingDealer($business);
        $outside = $this->orderingDealer($business);
        $group = $this->inTenant($business, fn () => DealerGroup::create(['name' => 'Altın']));
        $inGroup->forceFill(['dealer_group_id' => $group->id])->saveQuietly();

        $this->actingAs($business->user, 'business');
        $base = ['media' => null, 'link_type' => 'none', 'text_position' => 'left', 'is_active' => '1'];
        $this->post(route('business.storefront.banners.store'), ['media' => $this->png(), 'title' => 'Altın bayilere özel', 'dealer_group_id' => $group->id] + $base)->assertSessionHasNoErrors();
        $this->post(route('business.storefront.banners.store'), ['media' => $this->png(), 'title' => 'Geçmiş kampanya', 'starts_on' => now()->subDays(10)->toDateString(), 'ends_on' => now()->subDay()->toDateString()] + $base)->assertSessionHasNoErrors();
        $this->post(route('business.storefront.banners.store'), ['media' => $this->png(), 'title' => 'Kapalı slayt', 'is_active' => '0'] + $base)->assertSessionHasNoErrors();
        $groupBanner = $this->inTenant($business, fn () => HomeBanner::where('title', 'Altın bayilere özel')->sole());

        $this->flushSession();
        $this->actingAs($inGroup->dealer->user, 'dealer')->get(route('dealer.home'))->assertOk()
            ->assertSee('Altın bayilere özel')->assertDontSee('Geçmiş kampanya')->assertDontSee('Kapalı slayt');

        $this->flushSession();
        $this->actingAs($outside->dealer->user, 'dealer');
        $this->get(route('dealer.home'))->assertOk()->assertDontSee('Altın bayilere özel');
        $this->get(route('dealer.storefront.media', $groupBanner))->assertNotFound();
    }

    public function test_video_banner_is_accepted_streamed_with_ranges_and_disguised_files_are_rejected(): void
    {
        $business = $this->makeBusiness();
        $this->orderingDealer($business);
        $this->actingAs($business->user, 'business');
        $base = ['link_type' => 'none', 'text_position' => 'center', 'is_active' => '1'];

        // Yükleme ilerlemesi gösteren form JSON bekler.
        $video = UploadedFile::fake()->createWithContent('tanitim.mp4', "\x00\x00\x00\x18ftypmp42".str_repeat("\x00", 4096));
        $this->postJson(route('business.storefront.banners.store'), ['media' => $video, 'title' => 'Tanıtım filmi'] + $base)
            ->assertOk()->assertJson(['redirect' => route('business.storefront.index')]);
        $banner = $this->inTenant($business, fn () => HomeBanner::sole());
        $this->assertTrue($banner->isVideo());
        $this->assertSame('video/mp4', $banner->media_mime);

        $this->get(route('business.storefront.banners.media', $banner), ['Range' => 'bytes=0-99'])->assertStatus(206)->assertHeader('Content-Range');

        // Uzantısı video ama içeriği değil: reddedilir, kayıt oluşmaz.
        $fake = UploadedFile::fake()->createWithContent('virus.mp4', "MZ\x90\x00".str_repeat("\x00", 512));
        $this->postJson(route('business.storefront.banners.store'), ['media' => $fake] + $base)->assertStatus(422)->assertJsonValidationErrors('media');
        $this->assertSame(1, $this->inTenant($business, fn () => HomeBanner::count()));
    }

    public function test_link_target_must_belong_to_the_business_and_products_can_join_showcases_from_product_page(): void
    {
        $business = $this->makeBusiness();
        $other = $this->makeBusiness();
        $foreign = $this->makeProduct($other, ['name' => 'Yabancı']);
        $mine = $this->makeProduct($business, ['name' => 'Benim Ürünüm']);

        $this->actingAs($business->user, 'business');
        $this->post(route('business.storefront.banners.store'), [
            'media' => $this->png(), 'link_type' => 'product', 'link_id' => $foreign->id, 'text_position' => 'left', 'is_active' => '1',
        ])->assertSessionHasErrors('media');
        $this->assertSame(0, $this->inTenant($business, fn () => HomeBanner::count()));
        Storage::disk('local')->assertDirectoryEmpty('businesses/'.$business->id.'/storefront');

        $this->post(route('business.storefront.showcases.store'), ['name' => 'Seçtiklerimiz', 'type' => 'manual', 'product_limit' => 4, 'is_active' => '1']);
        $showcase = $this->inTenant($business, fn () => Showcase::sole());
        $this->get(route('business.products.show', $mine))->assertOk()->assertSee('Seçtiklerimiz');
        $this->put(route('business.products.showcases', $mine), ['showcases' => [$showcase->id]])->assertSessionHasNoErrors();
        $this->assertSame([$mine->id], $this->inTenant($business, fn () => $showcase->items()->pluck('product_id')->all()));
        $this->put(route('business.products.showcases', $mine), ['showcases' => []])->assertSessionHasNoErrors();
        $this->assertSame(0, $this->inTenant($business, fn () => $showcase->items()->count()));
    }

    private function png(int $w = 1200, int $h = 400): UploadedFile
    {
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, imagecolorallocate($img, 30, 58, 138));
        ob_start();
        imagepng($img);

        return UploadedFile::fake()->createWithContent('slayt.png', (string) ob_get_clean());
    }
}
