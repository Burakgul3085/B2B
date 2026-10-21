<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Mağaza ana sayfası banner'ı. Etkin ve yayın tarih aralığındaysa gösterilir. */
class StoreBanner extends Model
{
    protected $attributes = ['is_active' => true, 'sort' => 0, 'mobile_image_path' => null, 'starts_at' => null, 'ends_at' => null];

    protected $fillable = ['title', 'subtitle', 'button_text', 'link_url', 'sort', 'is_active', 'starts_at', 'ends_at'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->orderBy('sort')->orderBy('id');
    }
}
