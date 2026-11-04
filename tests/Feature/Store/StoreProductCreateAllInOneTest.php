<?php

namespace Tests\Feature\Store;

use App\Models\Store\StoreAdmin;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreProductImage;
use App\Models\Store\StoreProductVariant;
use App\Models\Store\StoreStockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Mağaza ürünü tek ekranda: bilgiler, görseller, başlangıç stoğu ve diğer seçenekler tek seferde. */
class StoreProductCreateAllInOneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->actingAs(StoreAdmin::create(['name' => 'Katalog', 'email' => 'katalog@magaza.local', 'password' => self::PASSWORD]), 'store_admin');
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

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Jel Kalem', 'is_published' => '0', 'variant_name' => 'Renk',
            'variant' => ['sku' => 'JEL-M', 'option_value' => 'Mavi', 'price' => '24,90', 'vat_rate' => '20', 'desi' => '0,5'],
            'stock' => '12',
            'images' => [$this->image('a.png'), $this->image('b.png')],
            'options' => [
                ['option_value' => 'Siyah', 'sku' => 'JEL-S', 'price' => '', 'stock' => '5'],
                ['option_value' => 'Kırmızı', 'sku' => 'JEL-K', 'price' => '26,90', 'stock' => ''],
            ],
        ];
    }

    public function test_everything_is_created_in_one_step(): void
    {
        $this->post(route('store_admin.products.store'), $this->payload())->assertSessionHasNoErrors()->assertRedirect();

        $product = StoreProduct::with('variants')->firstOrFail();
        $this->assertSame(['Mavi', 'Siyah', 'Kırmızı'], $product->variants->pluck('option_value')->all());
        $this->assertSame(['24.90', '24.90', '26.90'], $product->variants->pluck('price')->map(fn ($p) => (string) $p)->all());
        $this->assertSame([12, 5, 0], $product->variants->pluck('stock_quantity')->all());
        $this->assertSame('0.50', (string) $product->variants->firstWhere('sku', 'JEL-S')->desi); // ilk seçenekten kopyalanır
        $this->assertSame(2, StoreProductImage::where('product_id', $product->id)->count());
        $this->assertSame(2, StoreStockMovement::count());
    }

    public function test_a_bad_row_rolls_back_everything_and_removes_images(): void
    {
        $payload = $this->payload();
        $payload['options'][1]['sku'] = 'JEL-S'; // formda tekrar

        $this->post(route('store_admin.products.store'), $payload)->assertSessionHasErrors('options.1.sku');
        $this->assertSame(0, StoreProduct::count());
        $this->assertSame(0, StoreStockMovement::count());
        $this->assertSame([], Storage::disk('local')->allFiles('store/products'));

        $payload = $this->payload(['variant_name' => '']);
        $payload['variant']['option_value'] = '';
        $this->post(route('store_admin.products.store'), $payload)->assertSessionHasErrors('variant_name');
        $this->assertSame(0, StoreProduct::count());
    }

    public function test_simple_product_still_works_and_form_shows_new_sections(): void
    {
        $this->get(route('store_admin.products.create'))->assertOk()
            ->assertSee('Başlangıç stoğu')->assertSee('name="images[]"', false)->assertSee('Diğer seçenekler');

        $this->post(route('store_admin.products.store'), [
            'name' => 'Silgi', 'is_published' => '0',
            'variant' => ['sku' => 'SLG', 'price' => '5', 'vat_rate' => '20'],
        ])->assertSessionHasNoErrors();
        $this->assertSame(0, StoreProductVariant::where('sku', 'SLG')->value('stock_quantity'));
    }
}
