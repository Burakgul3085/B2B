<?php

namespace App\Models\Store;

use App\Domain\Store\Support\PerRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Blog yazısı. Gövde Markdown'dır; yayın tarihi ileri bir an olabilir (o ana kadar görünmez). */
class StoreBlogPost extends Model
{
    public const MAX_PRODUCTS = 12;

    protected $attributes = ['is_published' => false];

    protected $fillable = ['category_id', 'title', 'slug', 'excerpt', 'body', 'is_published', 'published_at', 'seo_title', 'seo_description'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'published_at' => 'datetime'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(StoreBlogCategory::class, 'category_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(StoreProduct::class, 'store_blog_post_product', 'post_id', 'product_id')->withPivot('sort')->orderByPivot('sort');
    }

    /** Ziyaretçiye görünen yazılar. */
    public function scopeLive(Builder $query): void
    {
        $query->where('is_published', true)->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    /** Menü ve alt bilgideki "Blog" bağlantısı yalnızca yayında yazı varsa (istek başına tek sorgu). */
    public static function anyLive(): bool
    {
        return PerRequest::remember('blog.any_live', fn () => static::live()->exists());
    }

    public function isLive(): bool
    {
        return $this->is_published && $this->published_at && $this->published_at->lte(now());
    }

    /** Dakika cinsinden okuma süresi (dakikada ~200 kelime). */
    public function readingMinutes(): int
    {
        return max(1, (int) ceil(str_word_count(strip_tags((string) $this->body), 0, 'çğıöşüÇĞİÖŞÜâîû') / 200));
    }
}
