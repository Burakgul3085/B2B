<?php

namespace App\Domain\Store\Catalog;

use App\Models\Store\StoreBrand;
use App\Models\Store\StoreCategory;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreProductVariant;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Throwable;

/**
 * Mağaza ürünlerini Excel/CSV ile ekleme ve güncelleme. Her satır bir varyanttır (stok kodu).
 *  - Stok kodu mağazada varsa o varyant ve ürünü güncellenir, yoksa oluşturulur.
 *  - "Ürün Grubu" aynı olan satırlar tek ürünün varyantlarıdır (ör. ilk varyantın stok kodunu yazın).
 *    Grup değeri mağazadaki bir stok koduysa satır o ürüne varyant olarak eklenir.
 *  - Her satır kendi transaction'ında işlenir; hatalı satır diğerlerini etkilemez. Doğrulama panel formuyla aynıdır.
 *  - Yeni ürünler varsayılan olarak yayında değildir ("Yayında" sütunu "Evet" değilse).
 */
class ProductImporter
{
    public const MAX_ROWS = 5000;

    public const MAX_BYTES = 10 * 1024 * 1024;

    public const MAX_ERRORS = 500;

    /** Şablon sütunları: anahtar => başlık. */
    public const COLUMNS = [
        'sku' => 'Stok Kodu',
        'name' => 'Ürün Adı',
        'group' => 'Ürün Grubu',
        'variant_name' => 'Seçenek Adı',
        'option_value' => 'Seçenek',
        'barcode' => 'Barkod',
        'category' => 'Kategori',
        'brand' => 'Marka',
        'description' => 'Açıklama',
        'price' => 'Satış Fiyatı (KDV Dahil)',
        'compare_at_price' => 'Üstü Çizili Fiyat',
        'vat_rate' => 'KDV Oranı',
        'desi' => 'Desi',
        'weight_grams' => 'Ağırlık (gr)',
        'is_published' => 'Yayında',
    ];

    /** @var array<string, int> grup anahtarı => ürün id (bu dosyada oluşturulan/bulunan) */
    private array $groups = [];

    public function __construct(private ProductService $products, private CategoryService $categories, private BrandService $brands) {}

    /**
     * @return array{total: int, created: int, updated: int, failed: int, errors: list<array{row: int, message: string}>}
     *
     * @throws DomainException dosya okunamazsa
     */
    public function import(UploadedFile $file, bool $createMissing): array
    {
        $extension = Str::lower($file->getClientOriginalExtension());
        if (! in_array($extension, ['xlsx', 'csv'], true) || $file->getSize() > self::MAX_BYTES) {
            throw new DomainException('Yalnızca en fazla 10 MB büyüklüğünde .xlsx veya .csv dosyası yüklenebilir.');
        }

        try {
            $rows = $this->readRows($file->getRealPath(), $extension);
        } catch (Throwable) {
            throw new DomainException('Dosya okunamadı. Şablonu kullanarak .xlsx veya .csv olarak kaydedin.');
        }

        $map = $this->mapHeader(array_shift($rows) ?? []);
        if (! isset($map['sku'], $map['name'])) {
            throw new DomainException('"Stok Kodu" ve "Ürün Adı" sütunları bulunamadı. İlk satır sütun başlıkları olmalıdır.');
        }
        if (count($rows) > self::MAX_ROWS) {
            throw new DomainException('Bir dosyada en fazla '.self::MAX_ROWS.' satır olabilir. Dosyayı bölerek yükleyin.');
        }

        $result = ['total' => 0, 'created' => 0, 'updated' => 0, 'failed' => 0, 'errors' => []];
        $seen = [];

        foreach ($rows as $index => $cells) {
            $rowNo = $index + 2;
            $values = [];
            foreach ($map as $key => $col) {
                $values[$key] = is_string($cells[$col] ?? null) ? trim($cells[$col]) : ($cells[$col] ?? null);
            }
            if (count(array_filter($values, fn ($v) => $v !== null && $v !== '')) === 0) {
                continue;
            }
            $result['total']++;

            $sku = (string) ($values['sku'] ?? '');
            $key = mb_strtolower($sku);
            if ($sku !== '' && isset($seen[$key])) {
                $this->fail($result, $rowNo, "Aynı stok kodu dosyada {$seen[$key]}. satırda da var.");

                continue;
            }
            $seen[$key] = $rowNo;

            try {
                $result[DB::transaction(fn () => $this->importRow($values, $createMissing))]++;
            } catch (ValidationException $e) {
                $this->fail($result, $rowNo, implode(' ', $e->validator->errors()->all()));
            } catch (DomainException $e) {
                $this->fail($result, $rowNo, $e->getMessage());
            }
        }

        return $result;
    }

    /** @return 'created'|'updated' */
    private function importRow(array $values, bool $createMissing): string
    {
        $productInput = [
            'name' => $values['name'] ?? null,
            'variant_name' => array_key_exists('variant_name', $values) ? $values['variant_name'] : null,
            'description' => $values['description'] ?? null,
            'category_id' => $this->categoryId($values['category'] ?? null, $createMissing),
            'brand_id' => $this->brandId($values['brand'] ?? null, $createMissing),
        ];
        $variantInput = array_intersect_key($values, array_flip(['sku', 'barcode', 'option_value', 'price', 'compare_at_price', 'vat_rate', 'desi', 'weight_grams']));
        $published = $this->bool($values['is_published'] ?? null);

        // 1) Var olan varyant: güncelle. Boş bırakılan ürün hücreleri mevcut değeri korur.
        $variant = StoreProductVariant::where('sku', (string) $values['sku'])->first();
        if ($variant) {
            $product = $variant->loadMissing('product')->product;
            $current = $product->only(['name', 'variant_name', 'description', 'category_id', 'brand_id', 'slug', 'seo_title', 'seo_description', 'is_featured']);
            $given = array_filter($productInput, fn ($v) => $v !== null && $v !== '');
            $this->products->update($product, $given + $current + ['is_published' => $published ?? $product->is_published]);
            // Dosyada olmayan sütun değişmez; sütun var ama hücre boşsa isteğe bağlı alan temizlenir.
            $existing = $variant->only(['barcode', 'option_value', 'price', 'compare_at_price', 'vat_rate', 'desi', 'weight_grams']);
            $existing['vat_rate'] = rtrim(rtrim((string) $existing['vat_rate'], '0'), '.');
            $this->products->updateVariant($variant->fresh(), $variantInput + $existing + ['is_active' => $variant->is_active]);
            $this->rememberGroup($values, $product->id);

            return 'updated';
        }

        // 2) Gruptaki ürüne yeni varyant
        if ($productId = $this->groupProductId($values)) {
            $product = StoreProduct::findOrFail($productId);
            $this->products->addVariant($product, $variantInput);

            return 'created';
        }

        // 3) Yeni ürün
        $product = $this->products->create($productInput + ['is_published' => $published ?? false], $variantInput);
        $this->rememberGroup($values, $product->id);

        return 'created';
    }

    private function groupProductId(array $values): ?int
    {
        $group = mb_strtolower((string) ($values['group'] ?? ''));
        if ($group === '') {
            return null;
        }

        return $this->groups[$group]
            ??= StoreProductVariant::where('sku', (string) $values['group'])->value('product_id');
    }

    private function rememberGroup(array $values, int $productId): void
    {
        $group = mb_strtolower((string) ($values['group'] ?? ''));
        if ($group !== '') {
            $this->groups[$group] ??= $productId;
        }
    }

    /** "Kalem > Jel Kalem" yolunu kategoriye çevirir; yoksa (izin verildiyse) oluşturur. */
    private function categoryId(mixed $path, bool $createMissing): ?int
    {
        $names = array_values(array_filter(array_map('trim', preg_split('/\s*[>›]\s*/u', (string) $path)), fn ($n) => $n !== ''));
        if ($names === []) {
            return null;
        }

        $parentId = null;
        foreach ($names as $name) {
            $category = StoreCategory::where('parent_id', $parentId)->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
            if (! $category) {
                if (! $createMissing) {
                    throw new DomainException("\"{$name}\" kategorisi bulunamadı. Önce oluşturun veya \"Eksik kategori ve markaları oluştur\" seçeneğini işaretleyin.");
                }
                $category = $this->categories->create(['name' => $name, 'parent_id' => $parentId]);
            }
            $parentId = $category->id;
        }

        return $parentId;
    }

    private function brandId(mixed $name, bool $createMissing): ?int
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        $brand = StoreBrand::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
        if (! $brand) {
            if (! $createMissing) {
                throw new DomainException("\"{$name}\" markası bulunamadı. Önce oluşturun veya \"Eksik kategori ve markaları oluştur\" seçeneğini işaretleyin.");
            }
            $brand = $this->brands->create(['name' => $name]);
        }

        return $brand->id;
    }

    private function bool(mixed $value): ?bool
    {
        $value = mb_strtolower(trim((string) $value));
        if ($value === '') {
            return null;
        }

        return in_array($value, ['evet', 'e', '1', 'true', 'var', 'yes', 'x'], true);
    }

    private function fail(array &$result, int $rowNo, string $message): void
    {
        $result['failed']++;
        if (count($result['errors']) < self::MAX_ERRORS) {
            $result['errors'][] = ['row' => $rowNo, 'message' => $message];
        }
    }

    private function readRows(string $path, string $extension): array
    {
        $reader = $extension === 'csv' ? new CsvReader(new CsvOptions(FIELD_DELIMITER: $this->detectDelimiter($path))) : new XlsxReader;
        $rows = [];

        /** @var ReaderInterface $reader */
        $reader->open($path);
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = $row->toArray();
                    if (count($rows) > self::MAX_ROWS + 1) {
                        break 2;
                    }
                }
                break; // yalnızca ilk sayfa
            }
        } finally {
            $reader->close();
        }

        return $rows;
    }

    private function detectDelimiter(string $path): string
    {
        $handle = fopen($path, 'r');
        $line = (string) fgets($handle);
        fclose($handle);

        return substr_count($line, ';') > substr_count($line, ',') ? ';' : ',';
    }

    /** Başlıkları anahtarlara eşler; Türkçe karakter, boşluk ve büyük/küçük harf farkı önemsizdir. */
    private function mapHeader(array $header): array
    {
        $normalize = fn ($v) => preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii((string) $v)));
        $lookup = ['sku' => 'sku', 'urunkodu' => 'sku', 'fiyat' => 'price', 'satisfiyati' => 'price', 'kdv' => 'vat_rate'];
        foreach (self::COLUMNS as $key => $title) {
            $lookup[$normalize($title)] = $key;
            $lookup[$normalize($key)] = $key;
        }

        $map = [];
        foreach ($header as $index => $title) {
            $key = $lookup[$normalize($title)] ?? null;
            if ($key && ! isset($map[$key])) {
                $map[$key] = $index;
            }
        }

        return $map;
    }
}
