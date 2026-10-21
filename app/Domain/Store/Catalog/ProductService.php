<?php

namespace App\Domain\Store\Catalog;

use App\Domain\Audit\AuditLogger;
use App\Domain\Files\ImageUploader;
use App\Domain\Support\Decimal;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreProductImage;
use App\Models\Store\StoreProductVariant;
use App\Models\Store\StoreStockMovement;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Mağaza ürünleri, varyantları ve görselleri (docs/13-magaza-mimarisi.md §7–8).
 *  - Fiyatlar KDV dahildir; üstü çizili fiyat satış fiyatından büyük olmalıdır.
 *  - Seçenek adı (ör. "Renk") yoksa ürünün tek varyantı olur; varsa her varyantın farklı bir seçenek değeri vardır.
 *  - Yayındaki ürünün en az bir etkin varyantı olmalıdır.
 * Her değişiklik işlem geçmişine yazılır.
 */
class ProductService
{
    public const MAX_IMAGES = 10;

    public const MAX_VARIANTS = 100;

    public function __construct(private AuditLogger $audit, private ImageUploader $images) {}

    /**
     * Ürün ve ilk varyantı birlikte oluşturulur.
     *
     * @throws ValidationException
     */
    public function create(array $productInput, array $variantInput): StoreProduct
    {
        $product = $this->validateProduct($productInput);
        $variant = $this->validateVariant($variantInput, null, $product['variant_name'] ?? null);

        return DB::transaction(function () use ($product, $variant) {
            $model = StoreProduct::create($product + ['slug' => $product['slug'] ?? Slugs::unique(StoreProduct::class, $product['name'])]);
            if ($model->is_published) {
                $model->forceFill(['published_at' => now()])->save();
            }
            $model->variants()->create($variant);

            $this->audit->log('store.product_created', $model, [], ['name' => $model->name, 'sku' => $variant['sku'], 'price' => $variant['price']]);

            return $model;
        });
    }

    /** @throws ValidationException|DomainException */
    public function update(StoreProduct $product, array $input): void
    {
        $data = $this->validateProduct($input, $product);
        $data['slug'] ??= $product->slug;

        if (($data['variant_name'] ?? null) === null && $product->variants()->count() > 1) {
            throw new DomainException('Birden fazla varyantı olan ürünün seçenek adı boş bırakılamaz.');
        }
        // Tek seçenekli ürüne seçenek adı verilirken mevcut varyantın değeri de girilir (ör. Renk → Mavi).
        $firstOption = null;
        if (($data['variant_name'] ?? null) !== null && $product->variants()->whereNull('option_value')->exists()) {
            $firstOption = trim((string) ($input['first_option_value'] ?? ''));
            if ($firstOption === '' || mb_strlen($firstOption) > 80 || $product->variants()->count() !== 1) {
                throw ValidationException::withMessages(['first_option_value' => "Mevcut ürünün {$data['variant_name']} değerini girin (ör. Mavi)."]);
            }
        }
        if ($data['is_published'] && ! $product->variants()->where('is_active', true)->exists()) {
            throw new DomainException('Etkin varyantı olmayan ürün yayına alınamaz.');
        }

        DB::transaction(function () use ($product, $data, $firstOption) {
            if ($firstOption !== null) {
                $product->variants()->update(['option_value' => $firstOption]);
            }
            // Seçenek adı kaldırılan tek varyantlı ürünün seçenek değeri de temizlenir.
            if (($data['variant_name'] ?? null) === null && $product->variant_name !== null) {
                $product->variants()->update(['option_value' => null]);
            }
            $old = $product->only(array_keys($data));
            $product->fill($data);
            if ($product->is_published && $product->published_at === null) {
                $product->published_at = now();
            }
            $product->save();
            $this->audit->log('store.product_updated', $product, $old, $product->only(array_keys($data)));
        });
    }

    /**
     * Ürün kalıcı olarak silinir (görselleriyle). Stok hareketi olan ürün silinemez: hareketler yalnızca eklenir
     * ve geçmişi korunur; böyle ürünler yayından kaldırılır.
     *
     * @throws DomainException
     */
    public function delete(StoreProduct $product): void
    {
        if (StoreStockMovement::whereIn('variant_id', $product->variants()->select('id'))->exists()) {
            throw new DomainException('Stok hareketi olan ürün silinemez. Mağazadan kaldırmak için yayından kaldırın.');
        }

        $paths = $product->images()->pluck('path');

        DB::transaction(function () use ($product) {
            $product->delete();
            $this->audit->log('store.product_deleted', $product, ['name' => $product->name, 'slug' => $product->slug]);
        });

        foreach ($paths as $path) {
            $this->images->delete($path);
        }
    }

    /** @param  list<int>  $ids */
    public function setPublished(array $ids, bool $published): int
    {
        $count = 0;
        foreach (StoreProduct::whereIn('id', $ids)->get() as $product) {
            if ($product->is_published === $published) {
                continue;
            }
            if ($published && ! $product->variants()->where('is_active', true)->exists()) {
                continue;
            }
            $product->forceFill(['is_published' => $published, 'published_at' => $product->published_at ?? ($published ? now() : null)])->save();
            $this->audit->log($published ? 'store.product_published' : 'store.product_unpublished', $product);
            $count++;
        }

        return $count;
    }

    /* ---------------- Varyantlar ---------------- */

    /** @throws ValidationException|DomainException */
    public function addVariant(StoreProduct $product, array $input): StoreProductVariant
    {
        if (! $product->hasOptions()) {
            throw new DomainException('Varyant eklemek için önce ürüne bir seçenek adı verin (ör. Renk, Boyut) ve mevcut varyantın seçenek değerini girin.');
        }
        if ($product->variants()->count() >= self::MAX_VARIANTS) {
            throw new DomainException('Bir ürünün en fazla '.self::MAX_VARIANTS.' varyantı olabilir.');
        }

        $data = $this->validateVariant($input, null, $product->variant_name, $product);
        $variant = $product->variants()->create($data + ['sort' => (int) $product->variants()->max('sort') + 1]);
        $this->audit->log('store.variant_created', $variant, [], ['product_id' => $product->id, 'sku' => $variant->sku, 'price' => $variant->price]);

        return $variant;
    }

    /** @throws ValidationException|DomainException */
    public function updateVariant(StoreProductVariant $variant, array $input): void
    {
        $product = $variant->loadMissing('product')->product;
        $data = $this->validateVariant($input, $variant, $product->variant_name, $product);

        if (! $data['is_active'] && $product->is_published
            && ! $product->variants()->whereKeyNot($variant->id)->where('is_active', true)->exists()) {
            throw new DomainException('Yayındaki ürünün son etkin varyantı pasif yapılamaz. Önce ürünü yayından kaldırın.');
        }

        $old = $variant->only(array_keys($data));
        $variant->update($data);
        $this->audit->log('store.variant_updated', $variant, $old, $variant->only(array_keys($data)));
    }

    /** @throws DomainException */
    public function deleteVariant(StoreProductVariant $variant): void
    {
        $product = $variant->loadMissing('product')->product;
        if ($product->variants()->count() <= 1) {
            throw new DomainException('Ürünün son varyantı silinemez.');
        }
        if ($variant->movements()->exists()) {
            throw new DomainException('Stok hareketi olan seçenek silinemez; "Satışta" seçeneğini kapatın.');
        }
        if ($product->is_published && $variant->is_active
            && ! $product->variants()->whereKeyNot($variant->id)->where('is_active', true)->exists()) {
            throw new DomainException('Yayındaki ürünün son etkin varyantı silinemez.');
        }

        $variant->delete();
        $this->audit->log('store.variant_deleted', $variant, ['product_id' => $product->id, 'sku' => $variant->sku]);
    }

    /* ---------------- Görseller ---------------- */

    /** @throws DomainException */
    public function addImage(StoreProduct $product, UploadedFile $file): StoreProductImage
    {
        if ($product->images()->count() >= self::MAX_IMAGES) {
            throw new DomainException('Bir ürüne en fazla '.self::MAX_IMAGES.' görsel eklenebilir.');
        }

        // Beyaz kenarlar kırpılır, doku net kalsın diye yüksek kalitede saklanır (B2B ürün görselleriyle aynı işlem).
        $path = $this->images->store($file, 'store/products/'.$product->id, maxEdge: 2000, quality: 90, trimWhitespace: true, sharpen: true);
        $image = $product->images()->create(['path' => $path, 'sort' => (int) $product->images()->max('sort') + 1]);
        $this->audit->log('store.product_image_added', $product, [], ['image_id' => $image->id]);

        return $image;
    }

    public function removeImage(StoreProductImage $image): void
    {
        $product = $image->loadMissing('product')->product;
        $image->delete();
        $this->images->delete($image->path);
        $this->audit->log('store.product_image_removed', $product, ['image_id' => $image->id]);
    }

    /** Görseli ilk sıraya alır (ana görsel). */
    public function makePrimary(StoreProductImage $image): void
    {
        $ordered = $image->loadMissing('product')->product->images()->get()->sortBy(fn ($i) => $i->id === $image->id ? -1 : $i->sort)->values();
        DB::transaction(function () use ($ordered) {
            foreach ($ordered as $i => $item) {
                $item->forceFill(['sort' => $i + 1])->save();
            }
        });
        $this->audit->log('store.product_image_primary', $image->product, [], ['image_id' => $image->id]);
    }

    /* ---------------- Doğrulama ---------------- */

    /** @throws ValidationException */
    public function validateProduct(array $input, ?StoreProduct $product = null): array
    {
        $input['slug'] = Slugs::normalize($input['slug'] ?? null);
        foreach (['variant_name', 'category_id', 'brand_id'] as $key) {
            if (($input[$key] ?? null) === '') {
                $input[$key] = null;
            }
        }

        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:220', Rule::unique('store_products', 'slug')->ignore($product?->id)],
            'category_id' => ['nullable', 'integer', 'exists:store_categories,id'],
            'brand_id' => ['nullable', 'integer', 'exists:store_brands,id'],
            'variant_name' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:20000'],
            'seo_title' => ['nullable', 'string', 'max:160'],
            'seo_description' => ['nullable', 'string', 'max:320'],
        ], [], [
            'name' => 'ürün adı', 'slug' => 'adres (kısa ad)', 'category_id' => 'kategori', 'brand_id' => 'marka',
            'variant_name' => 'seçenek adı', 'description' => 'açıklama', 'seo_title' => 'SEO başlığı', 'seo_description' => 'SEO açıklaması',
        ])->validate();

        $data['name'] = trim($data['name']);
        $data['variant_name'] = isset($data['variant_name']) ? (trim($data['variant_name']) ?: null) : null;
        $data['is_published'] = filter_var($input['is_published'] ?? false, FILTER_VALIDATE_BOOL);
        $data['is_featured'] = filter_var($input['is_featured'] ?? false, FILTER_VALIDATE_BOOL);
        if ($data['slug'] === null) {
            unset($data['slug']);
        }

        return $data;
    }

    /** @throws ValidationException */
    public function validateVariant(array $input, ?StoreProductVariant $variant, ?string $variantName, ?StoreProduct $product = null): array
    {
        $errors = [];
        $sku = trim((string) ($input['sku'] ?? ''));
        $barcode = trim((string) ($input['barcode'] ?? '')) ?: null;
        $option = trim((string) ($input['option_value'] ?? '')) ?: null;

        if ($sku === '') {
            $errors['sku'] = 'Stok kodu (SKU) zorunludur.';
        } elseif (mb_strlen($sku) > 64 || ! preg_match('/^[\pL\pN._\-\/ ]+$/u', $sku)) {
            $errors['sku'] = 'Stok kodu en fazla 64 karakter olabilir; harf, rakam, boşluk ve . _ - / içerebilir.';
        } elseif (StoreProductVariant::where('sku', $sku)->when($variant, fn ($q) => $q->whereKeyNot($variant->id))->exists()) {
            $errors['sku'] = 'Bu stok kodu başka bir üründe kullanılıyor.';
        }

        if ($barcode !== null) {
            if (mb_strlen($barcode) > 64 || ! preg_match('/^[A-Za-z0-9\-]+$/', $barcode)) {
                $errors['barcode'] = 'Barkod en fazla 64 karakter olabilir; harf, rakam ve - içerebilir.';
            } elseif (StoreProductVariant::where('barcode', $barcode)->when($variant, fn ($q) => $q->whereKeyNot($variant->id))->exists()) {
                $errors['barcode'] = 'Bu barkod başka bir üründe kullanılıyor.';
            }
        }

        if ($variantName !== null) {
            if ($option === null) {
                $errors['option_value'] = "{$variantName} değeri zorunludur (ör. Mavi).";
            } elseif (mb_strlen($option) > 80) {
                $errors['option_value'] = 'Seçenek değeri en fazla 80 karakter olabilir.';
            } elseif ($product && $product->variants()->whereRaw('LOWER(option_value) = ?', [mb_strtolower($option)])
                ->when($variant, fn ($q) => $q->whereKeyNot($variant->id))->exists()) {
                $errors['option_value'] = "Bu üründe \"{$option}\" seçeneği zaten var.";
            }
        } else {
            $option = null;
        }

        $price = Decimal::parse($input['price'] ?? null, 2);
        if ($price === null || bccomp($price, '0', 2) <= 0) {
            $errors['price'] = 'Geçerli bir satış fiyatı girin (KDV dahil, ör. 49,90).';
        }

        $blank = fn (string $key) => trim((string) ($input[$key] ?? '')) === '';

        $compare = null;
        if (! $blank('compare_at_price')) {
            $compare = Decimal::parse($input['compare_at_price'], 2);
            if ($compare === null) {
                $errors['compare_at_price'] = 'Geçerli bir üstü çizili fiyat girin.';
            } elseif ($price !== null && bccomp($compare, $price, 2) <= 0) {
                $errors['compare_at_price'] = 'Üstü çizili fiyat satış fiyatından yüksek olmalıdır.';
            }
        }

        $vat = trim(str_replace('%', '', (string) ($input['vat_rate'] ?? '')));
        if (! in_array($vat, StoreProductVariant::VAT_RATES, true)) {
            $errors['vat_rate'] = 'KDV oranı %0, %1, %10 veya %20 olmalıdır.';
        }

        $desi = null;
        if (! $blank('desi')) {
            $desi = Decimal::parse($input['desi'], 2);
            if ($desi === null || bccomp($desi, '0', 2) < 0) {
                $errors['desi'] = 'Desi 0 veya daha büyük bir sayı olmalıdır.';
            }
        }

        $weight = null;
        if (! $blank('weight_grams')) {
            $weight = filter_var(trim((string) $input['weight_grams']), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1000000]]);
            if ($weight === false) {
                $errors['weight_grams'] = 'Ağırlık gram cinsinden tam sayı olmalıdır.';
            }
        }

        $critical = null;
        if (! $blank('critical_stock')) {
            $critical = filter_var(trim((string) $input['critical_stock']), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1000000]]);
            if ($critical === false) {
                $errors['critical_stock'] = 'Kritik stok 0 veya daha büyük bir tam sayı olmalıdır.';
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'sku' => $sku,
            'barcode' => $barcode,
            'option_value' => $option,
            'price' => $price,
            'compare_at_price' => $compare,
            'vat_rate' => $vat,
            'desi' => $desi,
            'weight_grams' => $weight,
            'critical_stock' => $critical,
            'is_active' => filter_var($input['is_active'] ?? true, FILTER_VALIDATE_BOOL),
        ];
    }
}
