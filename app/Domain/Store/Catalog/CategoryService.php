<?php

namespace App\Domain\Store\Catalog;

use App\Domain\Audit\AuditLogger;
use App\Models\Store\StoreCategory;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Mağaza kategorileri: en fazla üç seviye; ürünü veya alt kategorisi olan kategori silinemez. */
class CategoryService
{
    public function __construct(private AuditLogger $audit) {}

    /** @throws ValidationException|DomainException */
    public function create(array $input): StoreCategory
    {
        $data = $this->validate($input);
        $this->assertDepth($data['parent_id'] ?? null, 1);

        return DB::transaction(function () use ($data) {
            $category = StoreCategory::create($data + [
                'slug' => $data['slug'] ?? Slugs::unique(StoreCategory::class, $data['name']),
                'sort' => (int) StoreCategory::where('parent_id', $data['parent_id'] ?? null)->max('sort') + 1,
            ]);
            $this->audit->log('store.category_created', $category, [], ['name' => $category->name, 'parent_id' => $category->parent_id]);

            return $category;
        });
    }

    /** @throws ValidationException|DomainException */
    public function update(StoreCategory $category, array $input): void
    {
        $data = $this->validate($input, $category);
        $parentId = $data['parent_id'] ?? null;

        if ($parentId !== null && in_array((int) $parentId, $category->descendantIds(), true)) {
            throw new DomainException('Bir kategori kendisinin veya alt kategorisinin altına taşınamaz.');
        }
        $this->assertDepth($parentId, $category->height());

        $data['slug'] ??= $category->slug;
        $old = $category->only(array_keys($data));
        $category->update($data);
        $this->audit->log('store.category_updated', $category, $old, $category->only(array_keys($data)));
    }

    /** @throws DomainException */
    public function delete(StoreCategory $category): void
    {
        if ($category->children()->exists()) {
            throw new DomainException('Alt kategorisi olan kategori silinemez. Önce alt kategorileri taşıyın veya silin.');
        }
        if ($category->products()->exists()) {
            throw new DomainException('Bu kategoride ürün var. Ürünleri başka kategoriye taşıyın ya da kategoriyi pasif yapın.');
        }

        $category->delete();
        $this->audit->log('store.category_deleted', $category, ['name' => $category->name]);
    }

    /** Sıralamada bir yukarı/aşağı (aynı üst kategori içinde). */
    public function move(StoreCategory $category, string $direction): void
    {
        $siblings = StoreCategory::where('parent_id', $category->parent_id)->orderBy('sort')->orderBy('name')->get()->values();
        $index = $siblings->search(fn ($c) => $c->id === $category->id);
        $swap = $direction === 'up' ? $index - 1 : $index + 1;
        if ($index === false || ! isset($siblings[$swap])) {
            return;
        }

        $ordered = $siblings->all();
        [$ordered[$index], $ordered[$swap]] = [$ordered[$swap], $ordered[$index]];
        DB::transaction(function () use ($ordered) {
            foreach ($ordered as $i => $item) {
                $item->forceFill(['sort' => $i + 1])->save();
            }
        });
    }

    /**
     * Ağacı düz listeye çevirir (seçim kutuları için): id => "Ana › Alt".
     *
     * @return array<int, string>
     */
    public static function options(?StoreCategory $except = null, bool $onlyParents = false): array
    {
        $exclude = $except ? $except->descendantIds() : [];
        $out = [];
        $walk = function (Collection $nodes, string $prefix, int $depth) use (&$walk, &$out, $exclude, $onlyParents) {
            foreach ($nodes as $node) {
                if (in_array($node->id, $exclude, true)) {
                    continue;
                }
                if (! $onlyParents || $depth < StoreCategory::MAX_DEPTH) {
                    $out[$node->id] = $prefix.$node->name;
                }
                $walk($node->children, $prefix.$node->name.' › ', $depth + 1);
            }
        };
        $walk(self::tree(), '', 1);

        return $out;
    }

    /** @return Collection<int, StoreCategory> kök kategoriler, alt kategorileri yüklü */
    public static function tree(): Collection
    {
        $all = StoreCategory::withCount('products')->orderBy('sort')->orderBy('name')->get();
        $byParent = $all->groupBy(fn ($c) => $c->parent_id ?? 0);
        foreach ($all as $category) {
            $category->setRelation('children', $byParent->get($category->id, collect())->values());
        }

        return $byParent->get(0, collect())->values();
    }

    private function validate(array $input, ?StoreCategory $category = null): array
    {
        $input['slug'] = Slugs::normalize($input['slug'] ?? null);

        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:120'],
            'parent_id' => ['nullable', 'integer', 'exists:store_categories,id'],
            'slug' => ['nullable', 'string', 'max:160', 'unique:store_categories,slug'.($category ? ','.$category->id : '')],
            'description' => ['nullable', 'string', 'max:5000'],
            'seo_title' => ['nullable', 'string', 'max:160'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'is_active' => ['boolean'],
        ], [], [
            'name' => 'kategori adı', 'parent_id' => 'üst kategori', 'slug' => 'adres (kısa ad)',
            'description' => 'açıklama', 'seo_title' => 'SEO başlığı', 'seo_description' => 'SEO açıklaması',
        ])->validate();

        $data['name'] = trim($data['name']);
        $data['is_active'] = (bool) ($input['is_active'] ?? true);
        if (($data['slug'] ?? null) === null) {
            unset($data['slug']);
        }

        return $data;
    }

    /** @throws DomainException */
    private function assertDepth(?int $parentId, int $subtreeHeight): void
    {
        $parentDepth = $parentId ? StoreCategory::findOrFail($parentId)->depth() : 0;
        if ($parentDepth + $subtreeHeight > StoreCategory::MAX_DEPTH) {
            throw new DomainException('Kategoriler en fazla '.StoreCategory::MAX_DEPTH.' seviye olabilir.');
        }
    }
}
