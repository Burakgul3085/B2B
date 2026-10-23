<?php

namespace App\Domain\Store\Content;

use App\Domain\Audit\AuditLogger;
use App\Domain\Files\ImageUploader;
use App\Domain\Store\Catalog\Slugs;
use App\Domain\Store\Storefront\ImageVariants;
use App\Domain\Store\Storefront\ProductSkus;
use App\Models\Store\StoreBlogCategory;
use App\Models\Store\StoreBlogPost;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Blog (Vitrin → Blog): kategoriler ve yazılar. Gövde Markdown'dır; ham HTML ve tehlikeli bağlantılar atılır.
 * Yazının altında stok koduyla seçilen ürünler gösterilir. Yayın tarihi ileri bir an olabilir.
 */
class Blog
{
    public function __construct(private AuditLogger $audit, private ImageUploader $images, private ImageVariants $variants) {}

    /** @throws ValidationException|DomainException */
    public function savePost(?StoreBlogPost $post, array $input, ?UploadedFile $cover, bool $removeCover): StoreBlogPost
    {
        $data = Validator::make($input, [
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:180'],
            'category_id' => ['nullable', 'integer', Rule::exists('store_blog_categories', 'id')],
            'excerpt' => ['nullable', 'string', 'max:300'],
            'body' => ['required', 'string', 'max:60000'],
            'published_at' => ['nullable', 'date'],
            'seo_title' => ['nullable', 'string', 'max:70'],
            'seo_description' => ['nullable', 'string', 'max:160'],
            'product_skus' => ['nullable', 'string', 'max:3000'],
        ], [], [
            'title' => 'başlık', 'slug' => 'adres', 'category_id' => 'kategori', 'excerpt' => 'özet', 'body' => 'yazı',
            'published_at' => 'yayın tarihi', 'seo_title' => 'SEO başlığı', 'seo_description' => 'SEO açıklaması', 'product_skus' => 'ürün kodları',
        ])->validate();

        $productIds = ProductSkus::resolve($data['product_skus'] ?? null, StoreBlogPost::MAX_PRODUCTS);
        $publish = filter_var($input['is_published'] ?? false, FILTER_VALIDATE_BOOL);
        // Tarih İstanbul saatiyle girilir; yayımlanırken boşsa şimdi.
        $at = filled($data['published_at'] ?? null) ? Carbon::parse($data['published_at'], 'Europe/Istanbul')->utc() : null;
        $slug = Slugs::normalize($data['slug'] ?? null);

        $post = DB::transaction(function () use ($post, $data, $publish, $at, $slug, $productIds) {
            $values = [
                'title' => trim($data['title']),
                'category_id' => $data['category_id'] ?? null,
                'excerpt' => trim((string) ($data['excerpt'] ?? '')) ?: null,
                'body' => trim($data['body']),
                'is_published' => $publish,
                'published_at' => $at ?? ($publish ? ($post?->published_at ?? now()) : $post?->published_at),
                'seo_title' => trim((string) ($data['seo_title'] ?? '')) ?: null,
                'seo_description' => trim((string) ($data['seo_description'] ?? '')) ?: null,
            ];
            $values['slug'] = Slugs::unique(StoreBlogPost::class, $slug ?: ($post?->slug ?? $values['title']), $post?->id, 180);
            if ($post) {
                $post->update($values);
            } else {
                $post = StoreBlogPost::create($values);
            }
            $post->products()->sync(collect($productIds)->mapWithKeys(fn ($id, $i) => [$id => ['sort' => $i]])->all());
            $this->audit->log('store.blog_post_saved', $post, [], ['title' => $post->title, 'published' => $post->is_published]);

            return $post;
        });

        if ($cover || $removeCover) {
            $old = $post->cover_path;
            $post->forceFill(['cover_path' => $cover ? $this->images->store($cover, 'store/blog', 1800, 8 * 1024 * 1024, 84) : null])->save();
            if ($old) {
                $this->images->delete($old);
                $this->variants->forget($old);
            }
        }

        return $post;
    }

    public function deletePost(StoreBlogPost $post): void
    {
        DB::transaction(function () use ($post) {
            $this->audit->log('store.blog_post_deleted', $post, ['title' => $post->title]);
            $post->delete();
        });
        if ($post->cover_path) {
            $this->images->delete($post->cover_path);
            $this->variants->forget($post->cover_path);
        }
    }

    /**
     * Kategori listesini kaydeder: [{id?, name}] — adı boşaltılan kategori silinir (yazıları kategorisiz kalır).
     *
     * @throws ValidationException
     */
    public function saveCategories(array $rows): void
    {
        Validator::make(['rows' => $rows], ['rows' => ['array', 'max:30'], 'rows.*.name' => ['nullable', 'string', 'max:60']], [], ['rows.*.name' => 'kategori adı'])->validate();

        DB::transaction(function () use ($rows) {
            $keep = [];
            foreach (array_values($rows) as $i => $row) {
                $name = trim((string) ($row['name'] ?? ''));
                $existing = isset($row['id']) ? StoreBlogCategory::find((int) $row['id']) : null;
                if ($name === '') {
                    continue;
                }
                if ($existing) {
                    $existing->update(['name' => $name, 'sort' => $i]);
                    $keep[] = $existing->id;
                } else {
                    $keep[] = StoreBlogCategory::create(['name' => $name, 'slug' => Slugs::unique(StoreBlogCategory::class, $name, null, 80), 'sort' => $i])->id;
                }
            }
            StoreBlogCategory::whereNotIn('id', $keep ?: [0])->delete();
            $this->audit->log('store.blog_categories_saved', null, [], ['count' => count($keep)]);
        });
    }

    public static function html(?string $markdown): HtmlString
    {
        return new HtmlString(Str::markdown((string) $markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]));
    }

    /** Liste kartı ve meta açıklama için kısa metin: özet, yoksa yazının başı. */
    public static function summary(StoreBlogPost $post, int $limit = 180): string
    {
        return $post->excerpt ?: Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) self::html($post->body)))), $limit);
    }
}
