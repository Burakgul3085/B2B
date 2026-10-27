<?php

namespace Tests\Feature\Store;

use App\Domain\Store\Catalog\ProductImporter;
use App\Models\Store\StoreAdmin;
use App\Models\Store\StoreBrand;
use App\Models\Store\StoreCategory;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreProductVariant;
use App\Models\Store\StoreStockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

/** Mağaza ürünlerini Excel ile ekleme/güncelleme: gruplama, kategori yolu, satır bazlı hata ve yeniden yüklemede çoğalmama. */
class StoreProductImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(StoreAdmin::create(['name' => 'Yönetici', 'email' => 'y@magaza.local', 'password' => self::PASSWORD]), 'store_admin');
    }

    /** @param  list<list<mixed>>  $rows */
    private function xlsx(array $rows, ?array $header = null): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($header ?? array_values(ProductImporter::COLUMNS)));
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        return new UploadedFile($path, 'urunler.xlsx', null, null, true);
    }

    private function rows(): array
    {
        // sku, ad, grup, seçenek adı, seçenek, barkod, kategori, marka, açıklama, fiyat, üstü çizili, kdv, desi, ağırlık, yayında
        return [
            ['DFT-A4', 'Spiralli Defter A4', '', '', '', '8690000000100', 'Defterler > Spiralli', 'Gıpta', 'Kareli', '89,90', '109,90', 20, '0,5', 450, 'Evet'],
            ['JEL-MAVI', 'Jel Kalem', 'JEL-MAVI', 'Renk', 'Mavi', '', 'Kalemler > Jel Kalem', 'Pensan', '', '24,90', '', 20, '', 15, 'Evet'],
            ['JEL-SIYAH', 'Jel Kalem', 'JEL-MAVI', 'Renk', 'Siyah', '', 'Kalemler > Jel Kalem', 'Pensan', '', '24,90', '', 20, '', 15, 'Evet'],
            ['HATALI-1', 'Fiyatsız ürün', '', '', '', '', '', '', '', '', '', 20, '', '', ''],
            ['DFT-A4', 'Tekrar', '', '', '', '', '', '', '', '1', '', 20, '', '', ''],
        ];
    }

    public function test_import_creates_products_variants_categories_and_reports_bad_rows(): void
    {
        $this->post(route('store_admin.products.import.store'), ['file' => $this->xlsx($this->rows()), 'create_missing' => '1'])
            ->assertRedirect(route('store_admin.products.import'));

        $result = session('import_result');
        $this->assertSame(['total' => 5, 'created' => 3, 'updated' => 0, 'failed' => 2], array_intersect_key($result, array_flip(['total', 'created', 'updated', 'failed'])));
        $this->assertSame([5, 6], array_column($result['errors'], 'row'));

        $this->assertSame(2, StoreProduct::count());
        $pen = StoreProductVariant::where('sku', 'JEL-SIYAH')->firstOrFail()->product()->firstOrFail();
        $this->assertSame(['Renk', 2, true], [$pen->variant_name, $pen->variants()->count(), $pen->is_published]);
        $this->assertSame('Kalemler › Jel Kalem', StoreCategory::where('name', 'Jel Kalem')->firstOrFail()->fullName());
        $this->assertSame(['Gıpta', 'Pensan'], StoreBrand::orderBy('name')->pluck('name')->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'store.products_imported', 'actor_type' => 'store_admin']);

        $this->get(route('store_admin.products.import'))->assertOk()->assertSee('Geçerli bir satış fiyatı girin')->assertSee('Aynı stok kodu dosyada 2. satırda da var.');
    }

    public function test_reimport_updates_without_duplicating_and_keeps_missing_columns(): void
    {
        $this->post(route('store_admin.products.import.store'), ['file' => $this->xlsx(array_slice($this->rows(), 0, 3)), 'create_missing' => '1']);
        $this->post(route('store_admin.products.import.store'), [
            'file' => $this->xlsx([['DFT-A4', 'Spiralli Defter A4 (Yeni)', '79,90']], ['Stok Kodu', 'Ürün Adı', 'Satış Fiyatı (KDV Dahil)']),
        ]);

        $this->assertSame(['created' => 0, 'updated' => 1, 'failed' => 0], array_intersect_key(session('import_result'), array_flip(['created', 'updated', 'failed'])));
        $variant = StoreProductVariant::where('sku', 'DFT-A4')->firstOrFail();
        $this->assertSame(['79.90', '109.90', '20.00', '8690000000100'], [$variant->price, $variant->compare_at_price, $variant->vat_rate, $variant->barcode]);
        $this->assertSame('Spiralli Defter A4 (Yeni)', $variant->product()->value('name'));
        $this->assertSame(2, StoreProduct::count());
    }

    public function test_stock_column_is_an_initial_receipt_for_new_and_a_count_for_existing_products(): void
    {
        $header = ['Stok Kodu', 'Ürün Adı', 'Satış Fiyatı (KDV Dahil)', 'KDV Oranı', 'Stok', 'Kritik Stok'];
        $this->post(route('store_admin.products.import.store'), ['file' => $this->xlsx([['STK-1', 'Silgi', '5', 20, 40, 5], ['STK-2', 'Kalemtıraş', '7', 20, '', '']], $header)]);
        $this->assertSame(0, session('import_result')['failed']);

        $silgi = StoreProductVariant::where('sku', 'STK-1')->firstOrFail();
        $this->assertSame([40, 5], [$silgi->stock_quantity, $silgi->critical_stock]);
        $this->assertSame(0, StoreProductVariant::where('sku', 'STK-2')->value('stock_quantity'));
        $this->assertSame(['receipt'], StoreStockMovement::pluck('type')->all());

        // Yeniden yükleme: stok o değere getirilir (sayım), boş hücre stoğa dokunmaz; geçersiz değer satır hatasıdır.
        $this->post(route('store_admin.products.import.store'), ['file' => $this->xlsx([['STK-1', '', '', '', 33, ''], ['STK-2', '', '', '', 'x', '']], ['Stok Kodu', 'Ürün Adı', 'Satış Fiyatı (KDV Dahil)', 'KDV Oranı', 'Stok', 'Kritik Stok'])]);
        $this->assertSame(['updated' => 1, 'failed' => 1], array_intersect_key(session('import_result'), array_flip(['updated', 'failed'])));
        $this->assertSame(33, $silgi->fresh()->stock_quantity);
        $this->assertSame([['receipt', 40], ['count', -7]], StoreStockMovement::orderBy('id')->get()->map(fn ($m) => [$m->type, $m->quantity])->all());
    }

    public function test_missing_category_is_an_error_unless_creation_is_allowed(): void
    {
        $this->post(route('store_admin.products.import.store'), ['file' => $this->xlsx([$this->rows()[0]])]);

        $this->assertSame(1, session('import_result')['failed']);
        $this->assertStringContainsString('Defterler', session('import_result')['errors'][0]['message']);
        $this->assertSame(0, StoreCategory::count());
        $this->assertSame(0, StoreProduct::count());
    }

    public function test_template_downloads_and_wrong_files_are_rejected(): void
    {
        $content = $this->get(route('store_admin.products.import.template'))->assertOk()->assertDownload('magaza-urun-sablonu.xlsx')->streamedContent();
        $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
        file_put_contents($path, $content);

        // Şablon: Ürünler (başlık + boş biçimli satırlar), Nasıl doldurulur, Örnekler.
        $sheets = [];
        $reader = new Reader;
        $reader->open($path);
        foreach ($reader->getSheetIterator() as $sheet) {
            $sheets[$sheet->getName()] = array_map(fn ($r) => $r->toArray(), iterator_to_array($sheet->getRowIterator(), false));
        }
        $reader->close();
        $this->assertSame(['Ürünler', 'Nasıl doldurulur', 'Örnekler'], array_keys($sheets));
        $this->assertSame('Stok Kodu *', $sheets['Ürünler'][0][0]);

        // Boş şablon olduğu gibi yüklenince hata vermez; örnek satırlar ilk sayfaya taşınınca ürün olarak eklenir.
        $this->post(route('store_admin.products.import.store'), ['file' => new UploadedFile($path, 'sablon.xlsx', null, null, true)])->assertSessionHasNoErrors();
        $this->assertSame(0, session('import_result')['total']);
        $examples = array_values(array_filter(array_slice($sheets['Örnekler'], 1), fn ($r) => count(array_filter($r, fn ($v) => $v !== '' && $v !== null)) > 3));
        $this->post(route('store_admin.products.import.store'), ['file' => $this->xlsx($examples, $sheets['Örnekler'][0]), 'create_missing' => '1'])->assertSessionHasNoErrors();
        $this->assertSame(['total' => 3, 'created' => 3, 'failed' => 0], array_intersect_key(session('import_result'), array_flip(['total', 'created', 'failed'])));
        $this->assertSame(2, StoreProduct::count(), 'jel kalemin iki rengi tek ürün');
        @unlink($path);

        $this->post(route('store_admin.products.import.store'), ['file' => UploadedFile::fake()->createWithContent('a.txt', 'x')])->assertSessionHasErrors('file');
        $this->post(route('store_admin.products.import.store'), ['file' => $this->xlsx([], ['Ad', 'Fiyat'])])->assertSessionHasErrors('file');
    }
}
