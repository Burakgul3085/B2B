<?php

namespace Tests\Feature\Catalog;

use App\Models\Brand;
use App\Models\BusinessModule;
use App\Models\Category;
use App\Models\ImportJob;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\CreatesCatalog;
use Tests\TestCase;

class ProductImportTest extends TestCase
{
    use CreatesCatalog, RefreshDatabase;

    private function csv(array $rows, string $delimiter = ';'): UploadedFile
    {
        $lines = array_map(fn ($r) => implode($delimiter, array_map(fn ($v) => '"'.str_replace('"', '""', (string) $v).'"', $r)), $rows);

        return UploadedFile::fake()->createWithContent('urunler.csv', "\u{FEFF}".implode("\n", $lines));
    }

    public function test_import_creates_and_updates_products_and_reports_row_errors(): void
    {
        $business = $this->makeBusiness();
        $existing = $this->makeProduct($business, ['sku' => 'VAR-1', 'name' => 'Eski Ad', 'list_price' => '5.0000']);

        $file = $this->csv([
            ['Ürün Kodu', 'Ürün Adı', 'Kategori', 'Marka', 'Birim', 'Liste Fiyatı', 'KDV Oranı', 'Minimum Sipariş', 'Sipariş Adımı', 'Başlangıç Stoğu'],
            ['YENI-1', 'Kurşun Kalem', 'Kalem > Kurşun Kalem', 'Faber', 'ADET', '3,75', '20', '12', '12', '240'],
            ['VAR-1', 'Yeni Ad', '', '', '', '6,50', '', '', '', ''],
            ['HATA-1', '', '', '', 'ADET', '1', '20', '', '', ''],          // ad eksik
            ['HATA-2', 'Fiyatsız', '', '', 'ADET', 'abc', '20', '', '', ''], // geçersiz sayı
            ['YENI-1', 'Tekrar', '', '', 'ADET', '1', '20', '', '', ''],     // dosyada tekrar
            ['HATA-3', 'Kötü KDV', '', '', 'ADET', '1', '7', '', '', ''],    // tanımsız oran
        ]);

        $this->actingAs($business->user, 'business')
            ->post(route('business.products.import.store'), ['file' => $file, 'create_missing' => '1'])
            ->assertRedirect();

        $job = $this->inTenant($business, fn () => ImportJob::latest('id')->firstOrFail());
        $this->assertSame(6, $job->total_rows);
        $this->assertSame(1, $job->created_rows);
        $this->assertSame(1, $job->updated_rows);
        $this->assertSame(4, $job->failed_rows);

        $this->inTenant($business, function () use ($existing) {
            $new = Product::where('sku', 'YENI-1')->with('category.parent')->firstOrFail();
            $this->assertSame('Kalem › Kurşun Kalem', $new->category->fullName());
            $this->assertSame('Faber', Brand::find($new->brand_id)->name);
            $this->assertSame('3.7500', (string) $new->list_price);
            $this->assertSame('240.000', (string) StockMovement::where('product_id', $new->id)->where('type', 'opening')->value('quantity'));

            $existing->refresh();
            $this->assertSame('Yeni Ad', $existing->name);
            $this->assertSame('6.5000', (string) $existing->list_price);
        });

        $this->get(route('business.products.import.show', $job))->assertOk()
            ->assertSee('KDV oranı tanımlı veya aktif değil')->assertSee('dosyada 2. satırda');
    }

    public function test_missing_category_is_an_error_when_auto_create_is_off(): void
    {
        $business = $this->makeBusiness();
        $file = $this->csv([
            ['Ürün Kodu', 'Ürün Adı', 'Kategori', 'Liste Fiyatı', 'KDV Oranı'],
            ['A-1', 'Defter', 'Defterler', '10', '20'],
        ]);

        $this->actingAs($business->user, 'business')
            ->post(route('business.products.import.store'), ['file' => $file, 'create_missing' => '0']);

        $this->inTenant($business, function () {
            $this->assertSame(0, Product::count());
            $this->assertSame(0, Category::count());
        });
    }

    public function test_file_without_sku_column_fails_cleanly(): void
    {
        $business = $this->makeBusiness();
        $file = $this->csv([['Ad', 'Fiyat'], ['Kalem', '1']]);

        $this->actingAs($business->user, 'business')->post(route('business.products.import.store'), ['file' => $file]);

        $job = $this->inTenant($business, fn () => ImportJob::latest('id')->firstOrFail());
        $this->assertSame('failed', $job->status);
    }

    public function test_import_never_touches_another_business(): void
    {
        $a = $this->makeBusiness();
        $b = $this->makeBusiness();
        $foreign = $this->makeProduct($b, ['sku' => 'ORTAK-1', 'name' => 'B Ürünü']);

        $this->actingAs($a->user, 'business')->post(route('business.products.import.store'), ['file' => $this->csv([
            ['Ürün Kodu', 'Ürün Adı', 'Liste Fiyatı', 'KDV Oranı'],
            ['ORTAK-1', 'A Ürünü', '1', '20'],
        ])]);

        $this->assertSame('B Ürünü', $foreign->fresh()->name);
        $this->assertSame(1, $this->inTenant($a, fn () => Product::where('sku', 'ORTAK-1')->count()));
    }

    public function test_import_module_can_be_disabled_by_platform(): void
    {
        $business = $this->makeBusiness();
        BusinessModule::create(['business_id' => $business->id, 'module_key' => 'import', 'enabled' => false]);

        $this->actingAs($business->user, 'business')->get(route('business.products.import'))->assertForbidden();
    }

    public function test_template_downloads(): void
    {
        $business = $this->makeBusiness();

        $this->actingAs($business->user, 'business')->get(route('business.products.import.template'))
            ->assertOk()->assertDownload('urun-ice-aktarma-sablonu.xlsx');
    }
}
