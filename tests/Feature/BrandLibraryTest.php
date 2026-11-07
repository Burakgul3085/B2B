<?php

namespace Tests\Feature;

use App\Domain\Support\BrandLibrary;
use App\Models\Brand;
use App\Models\Store\StoreAdmin;
use App\Models\Store\StoreBrand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesCatalog;
use Tests\TestCase;

/**
 * Hazır marka kütüphanesi: mağaza ve B2B "Yeni marka" formunda öneri; seçilen marka logosuyla eklenir. Ad ve logo
 * sunucuda kütüphaneden alınır; listede olmayan marka elle eklenir. İki taraf kendi kaydını oluşturur.
 */
class BrandLibraryTest extends TestCase
{
    use CreatesCatalog, RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->dir = sys_get_temp_dir().'/brand-lib-'.Str::random(8);
        File::ensureDirectoryExists($this->dir.'/logos');
        $img = imagecreatetruecolor(200, 80);
        imagefill($img, 0, 0, imagecolorallocate($img, 200, 30, 30));
        imagewebp($img, $this->dir.'/logos/faber-castell.webp');
        file_put_contents($this->dir.'/brands.json', json_encode([
            ['name' => 'Faber-Castell', 'slug' => 'faber-castell', 'categories' => ['kirtasiye'], 'logo' => 'faber-castell.webp'],
            ['name' => 'Gıpta', 'slug' => 'gipta', 'categories' => ['kirtasiye'], 'logo' => null],
            ['name' => 'Kötü', 'slug' => 'kotu', 'categories' => ['kirtasiye'], 'logo' => '../../.env'],
        ]));
        $this->app->instance(BrandLibrary::class, new BrandLibrary($this->dir));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    public function test_store_admin_adds_library_brand_with_logo_and_name_from_library(): void
    {
        $this->actingAs(StoreAdmin::create(['name' => 'Y', 'email' => 'y@magaza.test', 'password' => self::PASSWORD, 'is_super' => false, 'is_active' => true]), 'store_admin');

        $this->get(route('store_admin.brands.index'))->assertOk()->assertSee('brandPicker', false)->assertSee('faber-castell');
        $this->get(route('store_admin.brands.library-logo', 'faber-castell'))->assertOk();
        $this->get(route('store_admin.brands.library-logo', 'gipta'))->assertNotFound();
        $this->get(route('store_admin.brands.library-logo', 'kotu'))->assertNotFound();
        $this->get(route('store_admin.brands.library-logo', 'yok'))->assertNotFound();

        // Tarayıcıdan farklı ad gelse de kütüphanedeki ad kullanılır.
        $this->post(route('store_admin.brands.store'), ['name' => 'Sahte Ad', 'library' => 'faber-castell'])->assertSessionHasNoErrors();
        $brand = StoreBrand::sole();
        $this->assertSame('Faber-Castell', $brand->name);
        $this->assertNotNull($brand->logo_path);
        Storage::disk('local')->assertExists($brand->logo_path);

        // İkinci kez eklenemez; listede olmayan marka elle eklenir (logosuz); geçersiz kod yok sayılır.
        $this->post(route('store_admin.brands.store'), ['name' => 'X', 'library' => 'faber-castell'])->assertSessionHasErrors('name');
        $this->post(route('store_admin.brands.store'), ['name' => 'Yerel Marka', 'library' => '../etc'])->assertSessionHasNoErrors();
        $this->post(route('store_admin.brands.store'), ['name' => 'Gıpta', 'library' => 'gipta'])->assertSessionHasNoErrors();
        $this->assertNull(StoreBrand::where('name', 'Yerel Marka')->value('logo_path'));
        $this->assertNull(StoreBrand::where('name', 'Gıpta')->value('logo_path'));
        $this->assertSame(0, Brand::withoutBusinessScope()->count(), 'B2B markalarına dokunulmaz');
    }

    public function test_business_adds_library_brand_with_logo(): void
    {
        $business = $this->makeBusiness();
        $this->actingAs($business->user, 'business');

        $this->get(route('business.brands.index'))->assertOk()->assertSee('brandPicker', false);
        $this->get(route('business.brands.library-logo', 'faber-castell'))->assertOk();

        $this->post(route('business.brands.store'), ['name' => 'Sahte', 'library' => 'faber-castell'])->assertSessionHasNoErrors();
        $brand = $this->inTenant($business, fn () => Brand::sole());
        $this->assertSame('Faber-Castell', $brand->name);
        Storage::disk('local')->assertExists($brand->logo_path);
        $this->assertStringStartsWith("businesses/{$business->id}/brands/", $brand->logo_path);

        $this->post(route('business.brands.store'), ['name' => 'Elle Marka'])->assertSessionHasNoErrors();
        $this->assertNull($this->inTenant($business, fn () => Brand::where('name', 'Elle Marka')->value('logo_path')));
        $this->assertSame(0, StoreBrand::count(), 'mağaza markalarına dokunulmaz');
    }

    public function test_library_logo_requires_panel_login(): void
    {
        $this->get(route('business.brands.library-logo', 'faber-castell'))->assertRedirect();
        $this->get(route('store_admin.brands.library-logo', 'faber-castell'))->assertRedirect();
    }

    public function test_shipped_library_is_valid(): void
    {
        $library = new BrandLibrary;
        $brands = $library->all();
        $this->assertGreaterThan(300, count($brands));
        foreach ($brands as $slug => $brand) {
            $this->assertMatchesRegularExpression('/^[a-z0-9-]{1,80}$/', $slug);
            $this->assertNotSame('', trim($brand['name']));
            $this->assertNotEmpty(array_intersect($brand['categories'], array_keys(BrandLibrary::CATEGORIES)), $brand['name']);
            if ($brand['logo']) {
                $this->assertNotNull($library->logoPath($slug), "{$brand['name']} logosu eksik");
                $this->assertContains(mime_content_type($library->logoPath($slug)), ['image/webp', 'image/png'], $brand['name']);
            }
        }
    }
}
