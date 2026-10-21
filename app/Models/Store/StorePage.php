<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/** Mağazanın sabit sayfası (Hakkımızda, İletişim, Teslimat koşulları…). İçerik sade Markdown'dır; HTML çalıştırılmaz. */
class StorePage extends Model
{
    protected $attributes = ['is_active' => true, 'show_in_footer' => true, 'sort' => 0];

    protected $fillable = ['title', 'slug', 'body', 'seo_title', 'seo_description', 'is_active', 'show_in_footer', 'sort'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'show_in_footer' => 'boolean'];
    }

    /** Güvenli HTML: ham HTML ve tehlikeli bağlantılar atılır. */
    public function html(): HtmlString
    {
        return new HtmlString(Str::markdown((string) $this->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]));
    }
}
