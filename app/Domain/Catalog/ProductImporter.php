<?php

namespace App\Domain\Catalog;

use App\Domain\Inventory\Quantity;
use App\Domain\Inventory\StockService;
use App\Domain\Support\Decimal;
use App\Domain\Tenancy\TenantContext;
use App\Models\Brand;
use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\ImportError;
use App\Models\ImportJob;
use App\Models\Product;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\Warehouse;
use DomainException;
use Illuminate\Database\QueryException;
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
 * Excel/CSV ile toplu ürün ekleme ve güncelleme.
 *  - Ürün kodu (SKU) varsa ürün güncellenir, yoksa oluşturulur.
 *  - Her satır kendi transaction'ında işlenir; hatalı satır diğerlerini etkilemez.
 *  - Doğrulama ürün formuyla aynıdır (ProductService::validate).
 *  - "Başlangıç stoğu" yalnızca yeni oluşturulan üründe, varsayılan depoya ilk stok girişi olarak yazılır.
 */
class ProductImporter
{
    public const MAX_ROWS = 5000;

    public const MAX_BYTES = 10 * 1024 * 1024;

    /** Şablon sütunları: anahtar => başlık. */
    public const COLUMNS = [
        'sku' => 'Ürün Kodu',
        'barcode' => 'Barkod',
        'name' => 'Ürün Adı',
        'description' => 'Açıklama',
        'category' => 'Kategori',
        'brand' => 'Marka',
        'model' => 'Model',
        'unit' => 'Birim',
        'pack_quantity' => 'Koli İçi Miktar',
        'purchase_price' => 'Alış Fiyatı',
        'list_price' => 'Liste Fiyatı',
        'dealer_price' => 'Bayi Fiyatı',
        'tax_rate' => 'KDV Oranı',
        'price_includes_tax' => 'KDV Dahil',
        'min_order_qty' => 'Minimum Sipariş',
        'order_step' => 'Sipariş Adımı',
        'critical_stock_level' => 'Kritik Stok',
        'is_active' => 'Aktif',
        'visible_to_dealers' => 'Bayiye Görünür',
        'opening_stock' => 'Başlangıç Stoğu',
    ];

    private array $categoryCache = [];

    private array $brandCache = [];

    public function __construct(
        private ProductService $products,
        private StockService $stock,
        private TenantContext $tenant,
    ) {}

    public function import(UploadedFile $file, bool $createMissing, ?int $userId): ImportJob
    {
        $extension = Str::lower($file->getClientOriginalExtension());
        if (! in_array($extension, ['xlsx', 'csv'], true) || $file->getSize() > self::MAX_BYTES) {
            throw new DomainException('Yalnızca en fazla 10 MB büyüklüğünde .xlsx veya .csv dosyası yüklenebilir.');
        }

        $job = ImportJob::create([
            'type' => 'products', 'original_name' => Str::limit($file->getClientOriginalName(), 250, ''), 'created_by' => $userId,
        ]);

        try {
            $rows = $this->readRows($file->getRealPath(), $extension);
        } catch (Throwable) {
            $job->update(['status' => 'failed', 'finished_at' => now()]);
            ImportError::create(['import_job_id' => $job->id, 'row_no' => 0, 'message' => 'Dosya okunamadı. Şablonu kullanarak .xlsx veya .csv olarak kaydedin.']);

            return $job;
        }

        $header = array_shift($rows) ?? [];
        $map = $this->mapHeader($header);

        if (! isset($map['sku'])) {
            $job->update(['status' => 'failed', 'finished_at' => now()]);
            ImportError::create(['import_job_id' => $job->id, 'row_no' => 1, 'message' => '"Ürün Kodu" sütunu bulunamadı. İlk satır sütun başlıkları olmalıdır.']);

            return $job;
        }

        if (count($rows) > self::MAX_ROWS) {
            $job->update(['status' => 'failed', 'finished_at' => now()]);
            ImportError::create(['import_job_id' => $job->id, 'row_no' => 0, 'message' => 'Bir dosyada en fazla '.self::MAX_ROWS.' satır olabilir. Dosyayı bölerek yükleyin.']);

            return $job;
        }

        $warehouse = Warehouse::find(BusinessSetting::where('business_id', $this->tenant->businessId())->value('default_warehouse_id'));
        $stats = ['total' => 0, 'created' => 0, 'updated' => 0, 'failed' => 0];
        $seenSkus = [];

        foreach ($rows as $index => $cells) {
            $rowNo = $index + 2; // başlık satırı 1
            $values = [];
            foreach ($map as $key => $col) {
                $values[$key] = $cells[$col] ?? null;
            }
            if (count(array_filter($values, fn ($v) => $v !== null && trim((string) $v) !== '')) === 0) {
                continue; // boş satır
            }
            $stats['total']++;

            $sku = trim((string) ($values['sku'] ?? ''));
            if ($sku !== '' && isset($seenSkus[mb_strtolower($sku)])) {
                $this->fail($job, $rowNo, 'Ürün Kodu', "Aynı ürün kodu dosyada {$seenSkus[mb_strtolower($sku)]}. satırda da var.", $stats);

                continue;
            }
            $seenSkus[mb_strtolower($sku)] = $rowNo;

            try {
                $result = DB::transaction(fn () => $this->importRow($values, $createMissing, $warehouse));
                $stats[$result]++;
            } catch (ValidationException $e) {
                $this->resetCaches();
                foreach ($e->errors() as $field => $messages) {
                    $this->fail($job, $rowNo, self::COLUMNS[$field] ?? self::COLUMNS[str_replace('_id', '', $field)] ?? $field, $messages[0], $stats, count: false);
                }
                $stats['failed']++;
            } catch (DomainException $e) {
                $this->resetCaches();
                $this->fail($job, $rowNo, null, $e->getMessage(), $stats);
            } catch (QueryException $e) {
                // Örn. eşzamanlı eklenen aynı SKU. Ayrıntı kullanıcıya gösterilmez.
                $this->resetCaches();
                report($e);
                $this->fail($job, $rowNo, null, 'Satır kaydedilemedi (veritabanı kısıtı). Ürün kodu ve barkodun benzersiz olduğunu kontrol edin.', $stats);
            }
        }

        $job->update([
            'status' => 'completed', 'finished_at' => now(), 'total_rows' => $stats['total'],
            'created_rows' => $stats['created'], 'updated_rows' => $stats['updated'], 'failed_rows' => $stats['failed'],
        ]);

        return $job;
    }

    /** @return 'created'|'updated' */
    private function importRow(array $values, bool $createMissing, ?Warehouse $warehouse): string
    {
        $sku = trim((string) $values['sku']);
        $existing = $sku !== '' ? Product::where('sku', $sku)->first() : null;

        if ($existing?->isArchived()) {
            throw new DomainException("\"{$sku}\" kodlu ürün arşivde. Önce arşivden çıkarın.");
        }

        // Güncellemede yalnızca dosyada dolu olan sütunlar değişir.
        $input = $existing ? $existing->only(Product::EDITABLE) : [
            'is_active' => true, 'visible_to_dealers' => true, 'price_includes_tax' => false,
            'pack_quantity' => 1, 'min_order_qty' => 1, 'order_step' => 1, 'critical_stock_level' => 0,
        ];
        $input['sku'] = $sku;

        foreach (['barcode', 'name', 'description', 'model'] as $text) {
            if ($this->filled($values, $text)) {
                $input[$text] = trim((string) $values[$text]);
            }
        }

        foreach (['purchase_price' => 4, 'list_price' => 4, 'dealer_price' => 4, 'pack_quantity' => 3, 'min_order_qty' => 3, 'order_step' => 3, 'critical_stock_level' => 3] as $field => $scale) {
            if ($this->filled($values, $field)) {
                $parsed = Decimal::parse($values[$field], $scale);
                if ($parsed === null) {
                    throw ValidationException::withMessages([$field => "Geçersiz sayı: \"{$values[$field]}\" (en fazla {$scale} ondalık)."]);
                }
                $input[$field] = $parsed;
            }
        }

        foreach (['price_includes_tax', 'is_active', 'visible_to_dealers'] as $flag) {
            if ($this->filled($values, $flag)) {
                $input[$flag] = $this->bool($values[$flag], $flag);
            }
        }

        if ($this->filled($values, 'unit')) {
            $input['unit_id'] = $this->unitId((string) $values['unit']);
        } elseif (! $existing) {
            $input['unit_id'] = Unit::where('code', 'ADET')->value('id');
        }

        if ($this->filled($values, 'tax_rate')) {
            $input['tax_rate_id'] = $this->taxRateId($values['tax_rate']);
        } elseif (! $existing) {
            throw ValidationException::withMessages(['tax_rate' => 'KDV oranı zorunludur (ör. 20).']);
        }

        if ($this->filled($values, 'category')) {
            $input['category_id'] = $this->categoryId((string) $values['category'], $createMissing);
        }
        if ($this->filled($values, 'brand')) {
            $input['brand_id'] = $this->brandId((string) $values['brand'], $createMissing);
        }

        $data = $this->products->validate($input, $existing);

        if ($existing) {
            $this->products->update($existing, $data);

            return 'updated';
        }

        $product = $this->products->create($data);

        if ($this->filled($values, 'opening_stock')) {
            $qty = Quantity::parse($values['opening_stock']);
            if ($qty === null || bccomp($qty, '0', 3) < 0) {
                throw ValidationException::withMessages(['opening_stock' => 'Başlangıç stoğu sıfır veya pozitif bir sayı olmalıdır.']);
            }
            if (bccomp($qty, '0', 3) > 0) {
                if (! $warehouse) {
                    throw new DomainException('Varsayılan depo tanımlı değil; başlangıç stoğu yazılamadı.');
                }
                $this->stock->adjust($product->load('unit'), $warehouse, 'opening', $qty, 'İçe aktarma');
            }
        }

        return 'created';
    }

    /** @return array<int, array<int, mixed>> */
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

    /** Geri alınan satırda oluşturulmuş kategori/marka ID'leri önbellekte kalmasın. */
    private function resetCaches(): void
    {
        $this->categoryCache = [];
        $this->brandCache = [];
    }

    /** Başlıkları anahtarlara eşler; Türkçe karakter ve büyük/küçük harf farkı önemsizdir. */
    private function mapHeader(array $header): array
    {
        $normalize = fn ($v) => preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii((string) $v)));
        $lookup = [];
        foreach (self::COLUMNS as $key => $title) {
            $lookup[$normalize($title)] = $key;
            $lookup[$normalize($key)] = $key;
        }
        $lookup['sku'] = 'sku';
        $lookup['stokkodu'] = 'sku';

        $map = [];
        foreach ($header as $index => $title) {
            $key = $lookup[$normalize($title)] ?? null;
            if ($key && ! isset($map[$key])) {
                $map[$key] = $index;
            }
        }

        return $map;
    }

    private function filled(array $values, string $key): bool
    {
        return isset($values[$key]) && trim((string) $values[$key]) !== '';
    }

    private function bool(mixed $value, string $field): bool
    {
        $v = Str::lower(Str::ascii(trim((string) $value)));

        return match (true) {
            in_array($v, ['1', 'evet', 'e', 'true', 'aktif', 'var'], true) => true,
            in_array($v, ['0', 'hayir', 'h', 'false', 'pasif', 'yok'], true) => false,
            default => throw ValidationException::withMessages([$field => "\"{$value}\" anlaşılamadı; Evet veya Hayır yazın."]),
        };
    }

    private function unitId(string $value): int
    {
        $value = trim($value);
        $unit = Unit::where('code', Str::upper(Str::ascii($value)))->orWhere('name', $value)->first();

        return $unit?->id ?? throw ValidationException::withMessages(['unit' => "\"{$value}\" birimi tanımlı değil. Birimler sayfasından ekleyin."]);
    }

    private function taxRateId(mixed $value): int
    {
        $rate = Decimal::parse(str_replace('%', '', (string) $value), 2);
        $tax = $rate !== null ? TaxRate::where('rate', $rate)->where('is_active', true)->first() : null;

        return $tax?->id ?? throw ValidationException::withMessages(['tax_rate' => "%{$value} KDV oranı tanımlı veya aktif değil."]);
    }

    /** "Ana Kategori > Alt Kategori" veya yalnızca "Kategori". */
    private function categoryId(string $value, bool $create): int
    {
        $parts = array_values(array_filter(array_map('trim', preg_split('/[>›]/u', $value))));
        if (count($parts) > 2) {
            throw ValidationException::withMessages(['category' => 'Kategori en fazla iki seviye olabilir (Ana > Alt).']);
        }

        $key = mb_strtolower(implode('>', $parts));
        if (isset($this->categoryCache[$key])) {
            return $this->categoryCache[$key];
        }

        $parentId = null;
        foreach ($parts as $name) {
            $category = Category::where('name', $name)
                ->where(fn ($q) => $parentId ? $q->where('parent_id', $parentId) : $q->whereNull('parent_id'))->first();

            if (! $category) {
                if (! $create) {
                    throw ValidationException::withMessages(['category' => "\"{$value}\" kategorisi bulunamadı."]);
                }
                $category = Category::create(['name' => $name, 'parent_id' => $parentId]);
            }
            $parentId = $category->id;
        }

        return $this->categoryCache[$key] = $parentId;
    }

    private function brandId(string $value, bool $create): int
    {
        $value = trim($value);
        $key = mb_strtolower($value);
        if (isset($this->brandCache[$key])) {
            return $this->brandCache[$key];
        }

        $brand = Brand::where('name', $value)->first();
        if (! $brand) {
            if (! $create) {
                throw ValidationException::withMessages(['brand' => "\"{$value}\" markası bulunamadı."]);
            }
            $brand = Brand::create(['name' => Str::limit($value, 120, '')]);
        }

        return $this->brandCache[$key] = $brand->id;
    }

    private function fail(ImportJob $job, int $rowNo, ?string $column, string $message, array &$stats, bool $count = true): void
    {
        ImportError::create(['import_job_id' => $job->id, 'row_no' => $rowNo, 'column' => $column, 'message' => Str::limit($message, 500, '')]);
        if ($count) {
            $stats['failed']++;
        }
    }
}
