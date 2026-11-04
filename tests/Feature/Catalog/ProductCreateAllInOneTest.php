<?php

namespace Tests\Feature\Catalog;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Showcase;
use App\Models\ShowcaseProduct;
use App\Models\StockMovement;
use App\Models\TaxRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesCatalog;
use Tests\TestCase;

/** B2B ürünü tek ekranda: bilgiler, görseller, başlangıç stoğu, vitrinler ve diğer seçenekler (docs/03 §2.3). */
class ProductCreateAllInOneTest extends TestCase
{
    use CreatesCatalog, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function image(string $name = 'urun.png'): UploadedFile
    {
        $file = tempnam(sys_get_temp_dir(), 'img');
        $gd = imagecreatetruecolor(400, 300);
        imagefill($gd, 0, 0, imagecolorallocate($gd, 255, 255, 255));
        imagefilledrectangle($gd, 120, 80, 280, 220, imagecolorallocate($gd, 40, 60, 120));
        imagepng($gd, $file);

        return new UploadedFile($file, $name, 'image/png', null, true);
    }

    private function payload($business, array $overrides = []): array
    {
        return $this->inTenant($business, fn () => $overrides + [
            'sku' => 'DFT-M', 'name' => 'Defter A4', 'tax_rate_id' => TaxRate::where('rate', '20.00')->value('id'),
            'list_price' => '10', 'dealer_price' => '8', 'sale_mode' => 'single', 'min_order_qty' => '1', 'critical_stock_level' => '0',
            'is_active' => '1', 'visible_to_dealers' => '1', 'option_name' => 'Renk', 'option_value' => 'Mavi',
            'images' => [$this->image('a.png'), $this->image('b.png')],
            'opening_stock' => '40',
            'options' => [
                ['option_value' => 'Kırmızı', 'sku' => 'DFT-K', 'barcode' => '', 'dealer_price' => '', 'stock' => '15', 'image' => $this->image('k.png')],
                ['option_value' => 'Yeşil', 'sku' => 'DFT-Y', 'barcode' => '', 'dealer_price' => '9', 'stock' => ''],
            ],
        ]);
    }

    public function test_everything_is_created_in_one_step(): void
    {
        $business = $this->makeBusiness();
        $showcase = $this->inTenant($business, fn () => Showcase::create(['name' => 'Önerilenler', 'type' => 'manual', 'product_limit' => 8, 'is_active' => true]));

        $this->actingAs($business->user, 'business')
            ->post(route('business.products.store'), $this->payload($business, ['showcases' => [$showcase->id]]))
            ->assertSessionHasNoErrors()->assertRedirect();

        $this->inTenant($business, function () use ($showcase) {
            $main = Product::where('sku', 'DFT-M')->firstOrFail();
            $options = Product::optionsOf($main)->orderBy('id')->get();
            $this->assertSame(['Mavi', 'Kırmızı', 'Yeşil'], $options->pluck('option_value')->all());
            $this->assertSame(['8.0000', '8.0000', '9.0000'], $options->pluck('dealer_price')->map(fn ($p) => (string) $p)->all());
            $this->assertSame(['Defter A4', 'Defter A4', 'Defter A4'], $options->pluck('name')->all());
            $this->assertSame(2, ProductImage::where('product_id', $main->id)->count());
            $this->assertSame(1, ProductImage::where('product_id', $options[1]->id)->count());
            $this->assertSame(['40.000', '15.000'], StockMovement::where('type', 'opening')->orderBy('id')->pluck('quantity')->map(fn ($q) => (string) $q)->all());
            $this->assertTrue(ShowcaseProduct::where('showcase_id', $showcase->id)->where('product_id', $main->id)->exists());
        });
    }

    public function test_a_bad_row_rolls_back_everything_and_removes_images(): void
    {
        $business = $this->makeBusiness();
        $this->makeProduct($business, ['sku' => 'VAR-OLAN']);
        $this->actingAs($business->user, 'business');

        $payload = $this->payload($business);
        $payload['options'][1]['sku'] = 'VAR-OLAN'; // sistemde var
        $this->post(route('business.products.store'), $payload)->assertSessionHasErrors('options.1.sku');

        $payload = $this->payload($business);
        $payload['options'][1]['option_value'] = 'mavi'; // formda tekrar
        $this->post(route('business.products.store'), $payload)->assertSessionHasErrors('options.1.option_value');

        $payload = $this->payload($business, ['option_value' => '']);
        $this->post(route('business.products.store'), $payload)->assertSessionHasErrors('option_value');

        $this->assertSame(1, $this->inTenant($business, fn () => Product::count()));
        $this->assertSame(0, $this->inTenant($business, fn () => StockMovement::count()));
        $this->assertSame([], Storage::disk('local')->allFiles('businesses/'.$business->id.'/products'));
    }

    public function test_simple_product_and_form_sections(): void
    {
        $business = $this->makeBusiness();
        $this->actingAs($business->user, 'business');

        $this->get(route('business.products.create'))->assertOk()
            ->assertSee('Başlangıç stoğu')->assertSee('name="images[]"', false)->assertSee('Diğer seçenekler')->assertSee('multipart/form-data', false);

        $payload = $this->payload($business, ['option_name' => '', 'option_value' => '', 'images' => [], 'opening_stock' => '', 'options' => []]);
        $this->post(route('business.products.store'), $payload)->assertSessionHasNoErrors();
        $this->assertSame(0, $this->inTenant($business, fn () => StockMovement::count()));

        // Düzenleme ekranında bu bölümler yok (ürün sayfasında yönetilir).
        $product = $this->inTenant($business, fn () => Product::firstOrFail());
        $this->get(route('business.products.edit', $product))->assertOk()->assertDontSee('Başlangıç stoğu')->assertDontSee('name="images[]"', false);
    }

    public function test_new_option_screen_takes_image_and_stock_too(): void
    {
        $business = $this->makeBusiness();
        $source = $this->makeProduct($business, ['sku' => 'K-1', 'name' => 'Jel Kalem', 'option_name' => 'Renk', 'option_value' => 'Mavi']);
        $this->actingAs($business->user, 'business');

        $payload = $this->payload($business, ['option_of' => $source->id, 'sku' => 'K-2', 'option_value' => 'Siyah', 'options' => [], 'images' => [$this->image()], 'opening_stock' => '7']);
        $this->post(route('business.products.store'), $payload)->assertSessionHasNoErrors();

        $this->inTenant($business, function () use ($source) {
            $new = Product::where('sku', 'K-2')->firstOrFail();
            $this->assertSame($source->fresh()->variant_group_id, $new->variant_group_id);
            $this->assertSame(1, ProductImage::where('product_id', $new->id)->count());
            $this->assertSame('7.000', (string) StockMovement::where('product_id', $new->id)->value('quantity'));
        });
    }
}
