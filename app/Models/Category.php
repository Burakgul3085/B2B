<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Kategori ağacı: istenildiği kadar alt seviye (ana › alt › alt …). MAX_DEPTH yalnızca güvenlik sınırıdır
 * (yanlışlıkla çok derin ağaç ve döngü oluşmasın diye); pratikte kullanıcı bu sınıra takılmaz.
 */
class Category extends Model
{
    use BelongsToBusiness;

    public const MAX_DEPTH = 10;

    protected $attributes = ['is_active' => true, 'sort' => 0];

    protected $fillable = ['parent_id', 'name', 'sort', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort')->orderBy('name');
    }

    /** @return list<self> kökten bu kategoriye kadar */
    public function ancestry(): array
    {
        $chain = [];
        for ($node = $this, $guard = 0; $node && $guard <= self::MAX_DEPTH; $guard++) {
            array_unshift($chain, $node);
            $node = $node->parent_id ? $node->loadMissing('parent')->parent : null;
        }

        return $chain;
    }

    /** Ana kategori: 1, onun altı: 2 … */
    public function depth(): int
    {
        return count($this->ancestry());
    }

    /** Kategorinin kendisi dahil alt ağacının yüksekliği (yalnızca kendisi: 1). */
    public function height(): int
    {
        $parents = self::pluck('parent_id', 'id');
        $height = 0;
        for ($level = [$this->id]; $level !== [] && $height <= self::MAX_DEPTH; $height++) {
            $level = $parents->filter(fn ($parent) => in_array((int) $parent, $level, true))->keys()->map(fn ($id) => (int) $id)->all();
        }

        return $height;
    }

    public function fullName(): string
    {
        return implode(' › ', array_map(fn (self $c) => $c->name, $this->ancestry()));
    }

    /**
     * Verilen kategoriler ve tüm alt kategorileri (her seviye). Tek sorgu.
     *
     * @param  int|array<int|string>  $ids
     * @return list<int>
     */
    public static function withDescendants(int|array $ids): array
    {
        $result = array_values(array_unique(array_map('intval', (array) $ids)));
        if ($result === []) {
            return [];
        }
        $byParent = self::pluck('parent_id', 'id')->filter(fn ($parent) => $parent !== null)->map(fn ($p) => (int) $p)->groupBy(fn ($p) => $p, true);
        for ($level = $result; $level !== [];) {
            $next = [];
            foreach ($level as $id) {
                foreach (array_keys(($byParent[$id] ?? collect())->all()) as $child) {
                    if (! in_array((int) $child, $result, true)) {
                        $result[] = $next[] = (int) $child;
                    }
                }
            }
            $level = $next;
        }

        return $result;
    }

    /**
     * Ağaç sırasıyla (sıra, ad) kök kategoriler; her düğümde "children" ve "depth" (kök: 0) hazır. Tek sorgu.
     *
     * @param  Collection<int, self>|null  $all  önceden yüklenmiş kategoriler (ör. ürün sayısıyla)
     * @return Collection<int, self>
     */
    public static function tree(?Collection $all = null): Collection
    {
        $all ??= self::orderBy('sort')->orderBy('name')->get();
        $byParent = $all->groupBy(fn ($c) => $c->parent_id ?? 0);
        foreach ($all as $category) {
            $category->setRelation('children', $byParent->get($category->id, collect())->values());
        }
        $roots = $byParent->get(0, collect())->values();
        $mark = function (Collection $nodes, int $depth) use (&$mark) {
            foreach ($nodes as $node) {
                $node->setAttribute('depth', $depth);
                if ($depth < self::MAX_DEPTH) {
                    $mark($node->children, $depth + 1);
                }
            }
        };
        $mark($roots, 0);

        return $roots;
    }

    /**
     * Seçim kutuları için ağaç sırasıyla id => "Ana › Alt › …". $except ve alt ağacı listelenmez (taşıma döngüsü olmasın).
     *
     * @return array<int, string>
     */
    public static function paths(?self $except = null, bool $onlyActive = false): array
    {
        $exclude = $except ? self::withDescendants($except->id) : [];
        $all = self::when($onlyActive, fn ($q) => $q->where('is_active', true))->orderBy('sort')->orderBy('name')->get(['id', 'parent_id', 'name', 'sort']);
        $out = [];
        $walk = function (Collection $nodes, string $prefix, int $depth) use (&$walk, &$out, $exclude) {
            foreach ($nodes as $node) {
                if (in_array($node->id, $exclude, true) || $depth > self::MAX_DEPTH) {
                    continue;
                }
                $out[$node->id] = $prefix.$node->name;
                $walk($node->children, $prefix.$node->name.' › ', $depth + 1);
            }
        };
        $walk(self::tree($all), '', 1);

        return $out;
    }
}
