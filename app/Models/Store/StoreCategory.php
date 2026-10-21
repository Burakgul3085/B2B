<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Mağaza kategorisi. En fazla üç seviye (ana › alt › alt). */
class StoreCategory extends Model
{
    public const MAX_DEPTH = 3;

    protected $attributes = ['is_active' => true, 'sort' => 0];

    protected $fillable = ['parent_id', 'name', 'slug', 'description', 'seo_title', 'seo_description', 'sort', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort')->orderBy('name');
    }

    public function products(): HasMany
    {
        return $this->hasMany(StoreProduct::class, 'category_id');
    }

    /** @return list<self> kökten bu kategoriye kadar */
    public function ancestry(): array
    {
        $chain = [];
        for ($node = $this; $node; $node = $node->parent_id ? $node->loadMissing('parent')->parent : null) {
            array_unshift($chain, $node);
        }

        return $chain;
    }

    public function depth(): int
    {
        return count($this->ancestry());
    }

    public function fullName(): string
    {
        return implode(' › ', array_map(fn (self $c) => $c->name, $this->ancestry()));
    }

    /** @return list<int> bu kategori ve tüm alt kategorilerinin kimlikleri */
    public function descendantIds(): array
    {
        $ids = [$this->id];
        for ($level = [$this->id]; $level !== [];) {
            $level = self::whereIn('parent_id', $level)->pluck('id')->all();
            array_push($ids, ...$level);
        }

        return $ids;
    }

    /** Kategorinin kendisi dahil alt ağacının yüksekliği (yalnızca kendisi: 1). */
    public function height(): int
    {
        $height = 0;
        for ($level = [$this->id]; $level !== []; $height++) {
            $level = self::whereIn('parent_id', $level)->pluck('id')->all();
        }

        return $height;
    }
}
