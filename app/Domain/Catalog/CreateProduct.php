<?php

namespace App\Domain\Catalog;

use App\Domain\Files\ImageUploader;
use App\Domain\Inventory\StockService;
use App\Domain\Storefront\ShowcaseService;
use App\Domain\Tenancy\TenantContext;
use App\Models\BusinessSetting;
use App\Models\Product;
use App\Models\Warehouse;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Ürünü tek ekranda, her şeyiyle oluşturur (docs/03-is-kurallari.md §2.3): ürün, görseller, başlangıç stoğu,
 * vitrinler ve diğer seçenekler (her biri kendi kodu ve stoğu olan ayrı ürün). Hepsi tek transaction'dadır:
 * bir adım hata verirse hiçbiri yazılmaz ve diske yazılan görseller silinir.
 */
class CreateProduct
{
    public const MAX_OPTIONS = 30;

    public function __construct(
        private ProductService $products,
        private StockService $stock,
        private ShowcaseService $showcases,
        private ImageUploader $uploader,
        private TenantContext $tenant,
    ) {}

    /**
     * @param  array  $input  ürün formu alanları (ProductService::validate)
     * @param  UploadedFile[]  $images  ilk görsel ana görsel olur
     * @param  array<int, array{option_value?: mixed, sku?: mixed, barcode?: mixed, price?: mixed, stock?: mixed, image?: mixed}>  $options
     * @param  int[]  $showcaseIds
     *
     * @throws ValidationException|DomainException
     */
    public function handle(array $input, array $images = [], ?string $openingStock = null, ?int $warehouseId = null, array $options = [], array $showcaseIds = [], ?Product $optionOf = null, ?string $sourceOptionValue = null): Product
    {
        $data = $this->products->validate($input);
        // "Yeni seçenek ekle" ekranından gelen ürün mevcut ürünün seçeneği olur; ek seçenek satırı yoktur.
        $options = $optionOf ? [] : $options;
        $options = array_values(array_filter($options, fn ($row) => is_array($row) && collect($row)->except('image')->filter(fn ($v) => filled($v))->isNotEmpty()));
        $rows = $this->validateOptions($input, $data, $options);
        $warehouse = $this->warehouse($warehouseId, filled($openingStock) || collect($rows)->contains(fn ($r) => $r['stock'] !== null));

        $stored = [];
        try {
            return DB::transaction(function () use ($data, $images, $openingStock, $warehouse, $rows, $showcaseIds, $optionOf, $sourceOptionValue, &$stored) {
                $product = $optionOf
                    ? $this->products->createOption($optionOf, $data, $sourceOptionValue)
                    : $this->products->create($data);
                foreach ($images as $file) {
                    $stored[] = $this->products->addImage($product, $file)->path;
                }
                $this->openingStock($product, $warehouse, $openingStock, 'opening_stock');
                if ($showcaseIds !== []) {
                    try {
                        $this->showcases->syncProduct($product, $showcaseIds);
                    } catch (DomainException $e) {
                        throw ValidationException::withMessages(['showcases' => $e->getMessage()]);
                    }
                }

                foreach ($rows as $i => $row) {
                    $option = $this->products->createOption($product, $row['data']);
                    if ($row['image']) {
                        $stored[] = $this->products->addImage($option, $row['image'])->path;
                    }
                    $this->openingStock($option, $warehouse, $row['stock'], "options.{$i}.stock");
                }

                return $product;
            });
        } catch (Throwable $e) {
            foreach ($stored as $path) {
                $this->uploader->delete($path);
            }
            throw $e;
        }
    }

    /** Her seçenek satırı ana ürünün bilgileriyle doğrulanır; hatalar "options.N.alan" anahtarıyla döner. */
    private function validateOptions(array $input, array $data, array $options): array
    {
        if ($options === []) {
            return [];
        }
        if (count($options) > self::MAX_OPTIONS) {
            throw ValidationException::withMessages(['options' => 'En fazla '.self::MAX_OPTIONS.' seçenek eklenebilir.']);
        }
        if (blank($data['option_name'] ?? null) || blank($data['option_value'] ?? null)) {
            throw ValidationException::withMessages(['option_value' => 'Diğer seçenekleri eklemek için bu ürünün seçenek adını (ör. Renk) ve değerini (ör. Mavi) yazın.']);
        }

        $errors = [];
        $rows = [];
        $skus = [mb_strtolower((string) $data['sku'])];
        $values = [mb_strtolower($data['option_value'])];
        $barcodes = array_filter([$data['barcode'] ?? null]);
        foreach ($options as $i => $row) {
            $rowInput = [
                'sku' => $row['sku'] ?? null,
                'barcode' => $row['barcode'] ?? null,
                'option_value' => $row['option_value'] ?? null,
                'list_price' => filled($row['price'] ?? null) ? $row['price'] : ($input['list_price'] ?? null),
            ] + $input;
            if (blank($rowInput['option_value'])) {
                $errors["options.{$i}.option_value"] = ["{$data['option_name']} değerini yazın (ör. Kırmızı)."];
            }

            try {
                $rowData = $this->products->validate($rowInput);
            } catch (ValidationException $e) {
                foreach ($e->errors() as $key => $messages) {
                    $field = match ($key) {
                        'list_price', 'dealer_sale_price' => 'price', 'sku', 'barcode', 'option_value' => $key, default => 'sku'
                    };
                    $errors["options.{$i}.{$field}"] ??= $messages;
                }

                continue;
            }

            $sku = mb_strtolower((string) $rowData['sku']);
            $value = mb_strtolower((string) $rowData['option_value']);
            if (in_array($sku, $skus, true)) {
                $errors["options.{$i}.sku"] = ['Bu ürün kodu formda başka bir satırda da var.'];
            }
            if (in_array($value, $values, true)) {
                $errors["options.{$i}.option_value"] = ["“{$rowData['option_value']}” formda birden fazla kez yazılmış."];
            }
            if (filled($rowData['barcode'] ?? null) && in_array($rowData['barcode'], $barcodes, true)) {
                $errors["options.{$i}.barcode"] = ['Bu barkod formda başka bir satırda da var.'];
            }
            $barcodes[] = $rowData['barcode'] ?? null;
            $skus[] = $sku;
            $values[] = $value;

            $stock = $row['stock'] ?? null;
            if (filled($stock) && (! is_scalar($stock) || ! ctype_digit((string) $stock) || (int) $stock > 99999999)) {
                $errors["options.{$i}.stock"] = ['Stok 0 veya daha büyük bir tam sayı olmalıdır.'];
            }
            $image = $row['image'] ?? null;
            $rows[] = ['data' => $rowData, 'stock' => filled($stock) ? (string) $stock : null, 'image' => $image instanceof UploadedFile ? $image : null];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $rows;
    }

    private function warehouse(?int $warehouseId, bool $needed): ?Warehouse
    {
        if (! $needed) {
            return null;
        }
        $warehouse = $warehouseId
            ? Warehouse::where('is_active', true)->find($warehouseId)
            : Warehouse::find(BusinessSetting::where('business_id', $this->tenant->businessId())->value('default_warehouse_id'));

        return $warehouse ?? throw ValidationException::withMessages(['warehouse_id' => 'Stok için geçerli bir depo seçin.']);
    }

    private function openingStock(Product $product, ?Warehouse $warehouse, ?string $qty, string $field): void
    {
        if ($warehouse === null || blank($qty) || (int) $qty === 0) {
            return;
        }
        try {
            $this->stock->adjust($product->load('unit'), $warehouse, 'opening', (string) (int) $qty, 'İlk stok (ürün oluşturma)');
        } catch (DomainException $e) {
            throw ValidationException::withMessages([$field => $e->getMessage()]);
        }
    }
}
