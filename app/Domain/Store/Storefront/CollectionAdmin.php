<?php

namespace App\Domain\Store\Storefront;

use App\Domain\Audit\AuditLogger;
use App\Domain\Files\ImageUploader;
use App\Domain\Store\Catalog\Slugs;
use App\Models\Store\StoreCollection;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Seçkiler (Vitrin → Seçkiler): başlık, alt başlık, yapboz rengi, görsel ve stok koduyla (SKU) seçilen ürünler.
 * Ürün sırası yazılan SKU sırasıdır. Ana sayfada en fazla 4 seçki kutu olarak görünür.
 */
class CollectionAdmin
{
    public function __construct(private AuditLogger $audit, private ImageUploader $images, private ImageVariants $variants) {}

    /** @throws ValidationException|DomainException */
    public function save(?StoreCollection $collection, array $input, ?UploadedFile $image, bool $removeImage): StoreCollection
    {
        $data = Validator::make($input, [
            'title' => ['required', 'string', 'max:80'],
            'subtitle' => ['nullable', 'string', 'max:160'],
            'color' => ['nullable', Rule::in(array_keys(Palette::COLORS))],
            'product_skus' => ['nullable', 'string', 'max:5000'],
        ], [], ['title' => 'başlık', 'subtitle' => 'alt başlık', 'color' => 'renk', 'product_skus' => 'ürün kodları'])->validate();

        $productIds = ProductSkus::resolve($data['product_skus'] ?? null, StoreCollection::MAX_PRODUCTS);

        $collection = DB::transaction(function () use ($collection, $data, $input, $productIds) {
            $values = [
                'title' => trim($data['title']),
                'subtitle' => trim((string) ($data['subtitle'] ?? '')) ?: null,
                'color' => $data['color'] ?? null,
                'is_active' => filter_var($input['is_active'] ?? true, FILTER_VALIDATE_BOOL),
            ];
            if ($collection) {
                $collection->update($values);
            } else {
                $collection = StoreCollection::create($values + ['slug' => Slugs::unique(StoreCollection::class, $values['title']), 'sort' => (int) StoreCollection::max('sort') + 1]);
            }
            $collection->products()->sync(collect($productIds)->mapWithKeys(fn ($id, $i) => [$id => ['sort' => $i]])->all());
            $this->audit->log('store.collection_saved', $collection, [], ['title' => $collection->title, 'products' => count($productIds)]);

            return $collection;
        });

        if ($image || $removeImage) {
            $old = $collection->image_path;
            $collection->forceFill(['image_path' => $image ? $this->images->store($image, 'store/collections', 1400, 6 * 1024 * 1024, 86) : null])->save();
            if ($old) {
                $this->images->delete($old);
                $this->variants->forget($old);
            }
        }

        return $collection;
    }

    public function delete(StoreCollection $collection): void
    {
        DB::transaction(function () use ($collection) {
            $collection->products()->detach();
            $collection->delete();
            $this->audit->log('store.collection_deleted', $collection, ['title' => $collection->title]);
        });
        if ($collection->image_path) {
            $this->images->delete($collection->image_path);
            $this->variants->forget($collection->image_path);
        }
    }

    /** Formda gösterilecek SKU listesi (seçki sırasıyla, ürün başına ilk seçenek). */
    public static function skus(StoreCollection $collection): string
    {
        return ProductSkus::of($collection->products()->pluck('store_products.id')->all());
    }
}
