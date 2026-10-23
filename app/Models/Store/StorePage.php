<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;

/**
 * Mağazanın sabit sayfası (Hakkımızda, İletişim). Sayfalar kodda tanımlıdır (App\Domain\Store\Content\SitePages);
 * içerik o sayfanın alanlarına göre JSON olarak saklanır, yönetimde sayfaya özel formla düzenlenir.
 */
class StorePage extends Model
{
    protected $attributes = ['is_published' => false];

    protected $fillable = ['key', 'is_published', 'content', 'seo_title', 'seo_description'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'content' => 'array'];
    }
}
