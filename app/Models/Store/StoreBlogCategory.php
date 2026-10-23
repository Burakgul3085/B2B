<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Blog kategorisi (Vitrin → Blog → Kategoriler). */
class StoreBlogCategory extends Model
{
    protected $fillable = ['name', 'slug', 'sort'];

    public function posts(): HasMany
    {
        return $this->hasMany(StoreBlogPost::class, 'category_id');
    }
}
