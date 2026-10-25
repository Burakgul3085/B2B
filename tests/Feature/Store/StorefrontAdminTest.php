<?php

namespace Tests\Feature\Store;

use App\Models\Store\StoreAdmin;
use App\Models\Store\StoreBanner;
use App\Models\Store\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Vitrin yönetimi (M4): banner'lar, sabit sayfalar, mağaza ayarları ve yayın durumu. */
class StorefrontAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->actingAs(StoreAdmin::create(['name' => 'Vitrin Yöneticisi', 'email' => 'v@magaza.local', 'password' => self::PASSWORD]), 'store_admin');
    }

    private function image(int $w = 1600, int $h = 640): UploadedFile
    {
        $file = tempnam(sys_get_temp_dir(), 'bnr');
        $gd = imagecreatetruecolor($w, $h);
        imagefill($gd, 0, 0, imagecolorallocate($gd, 200, 60, 40));
        imagepng($gd, $file);

        return new UploadedFile($file, 'banner.png', 'image/png', null, true);
    }

    public function test_banners_are_created_validated_ordered_and_deleted(): void
    {
        $this->post(route('store_admin.banners.store'), ['title' => 'Okula Dönüş', 'link_url' => 'javascript:alert(1)', 'image' => $this->image()])
            ->assertSessionHasErrors('link_url');
        $this->post(route('store_admin.banners.store'), ['title' => 'Okula Dönüş', 'link_url' => '//kotu.site', 'image' => $this->image()])
            ->assertSessionHasErrors('link_url');
        $this->post(route('store_admin.banners.store'), ['title' => 'Okula Dönüş', 'link_url' => '/kategori/kalemler', 'image' => $this->image()])->assertSessionHasNoErrors();
        $this->post(route('store_admin.banners.store'), ['title' => 'Kış Kampanyası', 'link_url' => 'https://ornek.com/kampanya', 'image' => $this->image(),
            'mobile_image' => $this->image(1080, 1350)])->assertSessionHasNoErrors();

        // Görsel vitrindeki çerçevenin oranında olmalı (kırpılmadan oturması için); küçük görsel kabul edilmez.
        $this->post(route('store_admin.banners.store'), ['image' => $this->image(2100, 800)])->assertSessionHasErrors('image');
        $this->post(route('store_admin.banners.store'), ['image' => $this->image(800, 320)])->assertSessionHasErrors('image');
        $this->post(route('store_admin.banners.store'), ['image' => $this->image(2000, 800), 'mobile_image' => $this->image(1080, 1080)])->assertSessionHasErrors('mobile_image');
        $this->post(route('store_admin.banners.store'), ['placement' => 'side', 'image' => $this->image()])->assertSessionHasErrors('image');
        $this->assertSame(2, StoreBanner::count());

        [$first, $second] = StoreBanner::orderBy('sort')->get()->all();
        Storage::disk('local')->assertExists($second->mobile_image_path);
        // Yerleşim değişirken mevcut görsel yeni yerin oranına uymuyorsa yeni görsel istenir.
        $this->put(route('store_admin.banners.update', $first), ['title' => 'Okula Dönüş', 'placement' => 'side'])->assertSessionHasErrors('image');
        $this->put(route('store_admin.banners.update', $first), ['title' => 'Okula Dönüş', 'placement' => 'side', 'image' => $this->image(900, 600)])->assertSessionHasNoErrors();
        $this->assertSame('side', $first->fresh()->placement);

        $this->put(route('store_admin.banners.move', $second), ['direction' => 'up']);
        $this->assertSame(['Kış Kampanyası', 'Okula Dönüş'], StoreBanner::orderBy('sort')->pluck('title')->all());

        $this->put(route('store_admin.banners.update', $second), ['title' => 'Kış', 'is_active' => '0', 'remove_mobile' => '1'])->assertSessionHasNoErrors();
        $this->assertSame(['Kış', false, null], [$second->fresh()->title, $second->fresh()->is_active, $second->fresh()->mobile_image_path]);
        Storage::disk('local')->assertMissing($second->mobile_image_path);

        $this->get(route('store_admin.banners.image', $first))->assertOk();
        $this->delete(route('store_admin.banners.destroy', $first))->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($first->image_path);
        $this->assertDatabaseHas('audit_logs', ['action' => 'store.banner_created', 'actor_type' => 'store_admin']);
        $this->get(route('store_admin.banners.index'))->assertOk()->assertSee('Kış');
    }

    public function test_settings_and_opening_the_store(): void
    {
        $this->get(route('store_admin.settings.edit'))->assertOk()->assertSee('Mağaza kapalı')->assertSee('/onizleme?expires=', false);

        $this->put(route('store_admin.settings.update'), ['phone' => '0222 111 22 33', 'instagram' => 'http://insta.com/x'])->assertSessionHasErrors('instagram');
        $this->put(route('store_admin.settings.update'), ['phone' => '0222 111 22 33', 'announcement' => '500 TL üzeri kargo bedava', 'instagram' => 'https://instagram.com/yapboz'])
            ->assertSessionHasNoErrors();
        $this->assertSame('0222 111 22 33', StoreSetting::get('phone'));
        $this->assertSame('500 TL üzeri kargo bedava', StoreSetting::get('announcement'));

        $this->put(route('store_admin.settings.open'), ['open' => '1'])->assertSessionHasNoErrors();
        $this->assertTrue(StoreSetting::isOpen());
        $this->assertDatabaseHas('audit_logs', ['action' => 'store.opened', 'actor_type' => 'store_admin']);

        $this->get('http://magaza.localhost/')->assertOk()->assertSee('500 TL üzeri kargo bedava')->assertSee('0222 111 22 33');
        $this->get(route('store_admin.dashboard'))->assertOk()->assertSee('Mağaza açık');
    }
}
