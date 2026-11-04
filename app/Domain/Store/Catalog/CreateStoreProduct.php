<?php

namespace App\Domain\Store\Catalog;

use App\Domain\Files\ImageUploader;
use App\Domain\Store\Inventory\StoreStockService;
use App\Models\Store\StoreProduct;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Mağaza ürününü tek ekranda, her şeyiyle oluşturur (docs/13-magaza-mimarisi.md): ürün, ilk seçenek, görseller,
 * başlangıç stoğu ve diğer seçenekler. Hepsi tek transaction'dadır: bir adım hata verirse hiçbiri yazılmaz ve
 * diske yazılan görseller silinir. Fiyatlar sunucuda doğrulanır.
 */
class CreateStoreProduct
{
    public const MAX_OPTIONS = 30;

    /** Satırdan alınan alanlar; diğerleri (KDV, desi, ağırlık, kritik stok) ilk seçenekten kopyalanır. */
    private const ROW_FIELDS = ['option_value', 'sku', 'barcode', 'price'];

    public function __construct(private ProductService $products, private StoreStockService $stock, private ImageUploader $uploader) {}

    /**
     * @param  UploadedFile[]  $images  ilk görsel ana görsel olur
     * @param  array<int, array<string, mixed>>  $options  diğer seçenekler: option_value, sku, barcode, price, stock
     *
     * @throws ValidationException|DomainException
     */
    public function handle(array $productInput, array $variantInput, array $images = [], ?int $stock = null, array $options = []): StoreProduct
    {
        $product = $this->products->validateProduct($productInput);
        $first = $this->products->validateVariant($variantInput, null, $product['variant_name'] ?? null);
        $options = array_values(array_filter($options, fn ($row) => is_array($row) && collect($row)->filter(fn ($v) => filled($v))->isNotEmpty()));
        $rows = $this->validateOptions($product, $first, $variantInput, $options);

        $stored = [];
        try {
            return DB::transaction(function () use ($productInput, $variantInput, $images, $stock, $rows, &$stored) {
                $model = $this->products->create($productInput, $variantInput);
                foreach ($images as $file) {
                    $stored[] = $this->products->addImage($model, $file)->path;
                }
                $this->receive($model->variants()->first(), $stock);

                foreach ($rows as $row) {
                    $this->receive($this->products->addVariant($model->refresh(), $row['input']), $row['stock']);
                }

                return $model;
            });
        } catch (Throwable $e) {
            foreach ($stored as $path) {
                $this->uploader->delete($path);
            }
            throw $e;
        }
    }

    /** Satırlar ilk seçeneğin bilgileriyle doğrulanır; hatalar "options.N.alan" anahtarıyla döner. */
    private function validateOptions(array $product, array $first, array $variantInput, array $options): array
    {
        if ($options === []) {
            return [];
        }
        if (count($options) > self::MAX_OPTIONS) {
            throw ValidationException::withMessages(['options' => 'En fazla '.self::MAX_OPTIONS.' seçenek eklenebilir.']);
        }
        if (($product['variant_name'] ?? null) === null) {
            throw ValidationException::withMessages(['variant_name' => 'Diğer seçenekleri eklemek için seçenek adını yazın (ör. Renk).']);
        }

        $errors = [];
        $rows = [];
        $seen = ['sku' => [mb_strtolower($first['sku'])], 'barcode' => array_filter([$first['barcode'] ?? null]), 'option_value' => [mb_strtolower((string) $first['option_value'])]];
        foreach ($options as $i => $row) {
            $input = array_intersect_key($row, array_flip(self::ROW_FIELDS)) + $variantInput;
            if (blank($row['price'] ?? null)) {
                $input['price'] = $variantInput['price'] ?? null;
            } else {
                unset($input['compare_at_price']); // farklı fiyatlı seçenekte ilk seçeneğin üstü çizili fiyatı geçerli değildir
            }

            try {
                $data = $this->products->validateVariant($input, null, $product['variant_name']);
            } catch (ValidationException $e) {
                foreach ($e->errors() as $key => $messages) {
                    $errors["options.{$i}.".(in_array($key, self::ROW_FIELDS, true) ? $key : 'price')] ??= $messages;
                }

                continue;
            }

            foreach (['sku' => 'Bu stok kodu', 'barcode' => 'Bu barkod', 'option_value' => 'Bu seçenek'] as $field => $label) {
                $value = $data[$field] ?? null;
                if ($value === null) {
                    continue;
                }
                $value = $field === 'barcode' ? $value : mb_strtolower($value);
                if (in_array($value, $seen[$field], true)) {
                    $errors["options.{$i}.{$field}"] = ["{$label} formda birden fazla kez yazılmış."];
                }
                $seen[$field][] = $value;
            }

            $qty = $row['stock'] ?? null;
            if (filled($qty) && (! is_scalar($qty) || ! ctype_digit((string) $qty) || (int) $qty > StoreStockService::MAX_QUANTITY)) {
                $errors["options.{$i}.stock"] = ['Stok 0 veya daha büyük bir tam sayı olmalıdır.'];
            }
            $rows[] = ['input' => $input, 'stock' => filled($qty) ? (int) $qty : null];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $rows;
    }

    private function receive($variant, ?int $quantity): void
    {
        if ($quantity !== null && $quantity > 0) {
            $this->stock->receive($variant, $quantity, 'İlk stok (ürün oluşturma)');
        }
    }
}
