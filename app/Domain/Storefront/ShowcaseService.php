<?php

namespace App\Domain\Storefront;

use App\Domain\Audit\AuditLogger;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Showcase;
use App\Models\ShowcaseProduct;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Ana sayfadaki ürün vitrinleri (sekmeler) ve elle seçilen ürünleri. */
class ShowcaseService
{
    public const MAX_SHOWCASES = 8;

    public const MAX_MANUAL_PRODUCTS = 24;

    public function __construct(private AuditLogger $audit) {}

    public function create(array $data): Showcase
    {
        if (Showcase::count() >= self::MAX_SHOWCASES) {
            throw new DomainException('En fazla '.self::MAX_SHOWCASES.' vitrin oluşturulabilir.');
        }

        $showcase = Showcase::create($this->attributes($data) + ['sort' => (int) Showcase::max('sort') + 1]);
        $this->audit->log('storefront.showcase_created', $showcase, [], ['type' => $showcase->type]);

        return $showcase;
    }

    public function update(Showcase $showcase, array $data): Showcase
    {
        $attributes = $this->attributes($data);
        DB::transaction(function () use ($showcase, $attributes) {
            // Tür elle seçimden başka bir türe dönerse seçili ürünler artık kullanılmaz.
            if ($showcase->type === 'manual' && $attributes['type'] !== 'manual') {
                $showcase->items()->delete();
            }
            $showcase->update($attributes);
        });

        return $showcase;
    }

    public function delete(Showcase $showcase): void
    {
        $showcase->delete(); // ürün bağları yabancı anahtarla birlikte silinir
        $this->audit->log('storefront.showcase_deleted', null, ['id' => $showcase->id, 'name' => $showcase->name]);
    }

    public function addProduct(Showcase $showcase, int $productId): void
    {
        if ($showcase->type !== 'manual') {
            throw new DomainException('Ürünler yalnızca "elle seçilen" vitrinlere eklenir.');
        }
        if (! Product::whereKey($productId)->exists()) {
            throw new DomainException('Ürün bulunamadı.');
        }
        if ($showcase->items()->count() >= self::MAX_MANUAL_PRODUCTS) {
            throw new DomainException('Bir vitrine en fazla '.self::MAX_MANUAL_PRODUCTS.' ürün eklenebilir.');
        }

        ShowcaseProduct::firstOrCreate(
            ['showcase_id' => $showcase->id, 'product_id' => $productId],
            ['sort' => (int) $showcase->items()->max('sort') + 1],
        );
    }

    public function removeProduct(Showcase $showcase, int $productId): void
    {
        ShowcaseProduct::where('showcase_id', $showcase->id)->where('product_id', $productId)->delete();
    }

    /** Ürün sayfasından: ürünün yer alacağı elle seçilen vitrinler. @param int[] $showcaseIds */
    public function syncProduct(Product $product, array $showcaseIds): void
    {
        $manual = Showcase::where('type', 'manual')->pluck('id');
        $wanted = $manual->intersect(array_map('intval', $showcaseIds));

        DB::transaction(function () use ($product, $manual, $wanted) {
            ShowcaseProduct::where('product_id', $product->id)->whereIn('showcase_id', $manual->diff($wanted))->delete();
            foreach ($wanted as $id) {
                $showcase = Showcase::find($id);
                if (! $showcase->items()->where('product_id', $product->id)->exists()) {
                    $this->addProduct($showcase, $product->id);
                }
            }
        });
    }

    /** @param  int[]  $ids */
    public function reorder(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            foreach (array_values($ids) as $i => $id) {
                Showcase::whereKey($id)->update(['sort' => $i + 1]);
            }
        });
    }

    private function attributes(array $data): array
    {
        $type = $data['type'];
        $sourceId = in_array($type, ['category', 'brand'], true) ? ($data['source_id'] ?? null) : null;

        if ($type === 'category' && ! Category::whereKey($sourceId)->exists()) {
            throw new DomainException('Vitrin için bir kategori seçin.');
        }
        if ($type === 'brand' && ! Brand::whereKey($sourceId)->exists()) {
            throw new DomainException('Vitrin için bir marka seçin.');
        }

        return [
            'name' => $data['name'],
            'type' => $type,
            'source_id' => $sourceId,
            'product_limit' => (int) ($data['product_limit'] ?? 8),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }
}
