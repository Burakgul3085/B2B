<?php

namespace App\Domain\Store\Storefront;

use App\Domain\Store\Content\SitePages;
use App\Domain\Store\Content\StoreFooter;
use App\Domain\Store\Reports\StoreReports;
use App\Models\Store\StoreBanner;
use App\Models\Store\StoreBrand;
use App\Models\Store\StoreCollection;
use App\Models\Store\StoreDiscount;
use App\Models\Store\StoreOrderItem;
use App\Models\Store\StorePage;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Ana sayfa bloklarının verisi. Her şey gerçek veriden gelir: "çok satanlar" son 90 günün satışlarından,
 * "indirimdekiler" üstü çizili fiyatı olan ürünlerden hesaplanır; uydurma sayı, sayaç veya puan gösterilmez.
 */
class HomeFeed
{
    public const SHOWCASE_SIZE = 8;

    public function __construct(private Catalog $catalog) {}

    /** @return array{main: Collection, side: Collection} */
    public function banners(): array
    {
        $all = StoreBanner::visible()->get();

        return ['main' => $all->where('placement', '!=', 'side')->values(), 'side' => $all->where('placement', 'side')->take(4)->values()];
    }

    /** Ana kategoriler (renkleriyle) ve her birinin ürün sayısı. */
    public function categories(): Collection
    {
        $roots = $this->catalog->menu()->take(8);
        foreach ($roots as $root) {
            $ids = $root->children->pluck('id')->push($root->id)->all();
            $root->setAttribute('product_count', $this->catalog->products()->whereIn('category_id', $ids)->count());
        }

        return $roots;
    }

    /** Ana sayfada gösterilmesi işaretlenmiş, şu an geçerli, kodlu kampanyalar. */
    public function coupons(): Collection
    {
        return StoreDiscount::where('show_on_home', true)->whereNotNull('code')->where('codes_only', false)->orderByDesc('id')->get()
            ->filter(fn (StoreDiscount $d) => $d->isLive() && (! $d->usage_limit || $d->redemptions()->where('status', '!=', 'void')->count() < $d->usage_limit))
            ->take(3)->values();
    }

    /** @return array<string, array{label: string, products: Collection}> boş sekmeler atılır */
    public function showcase(): array
    {
        $cards = fn ($q) => $this->catalog->forCards($q)->limit(self::SHOWCASE_SIZE)->get();

        $bestIds = StoreOrderItem::query()
            ->join('store_orders', 'store_orders.id', '=', 'store_order_items.order_id')
            ->whereIn('store_orders.status', StoreReports::SALE_STATUSES)
            ->where('store_orders.created_at', '>=', now()->subDays(90))
            ->whereNotNull('store_order_items.product_id')
            ->groupBy('store_order_items.product_id')
            ->orderByRaw('SUM(store_order_items.quantity) DESC')
            ->limit(self::SHOWCASE_SIZE)
            ->pluck('store_order_items.product_id')->all();
        $best = $bestIds ? $cards($this->catalog->products()->whereIn('id', $bestIds))->sortBy(fn ($p) => array_search($p->id, $bestIds, true))->values() : collect();

        $tabs = [
            'featured' => ['label' => 'Öne çıkanlar', 'products' => $cards($this->catalog->products()->where('is_featured', true)->latest('published_at'))],
            'best' => ['label' => 'Çok satanlar', 'products' => $best],
            'new' => ['label' => 'Yeni gelenler', 'products' => $cards($this->catalog->products()->latest('published_at')->orderByDesc('id'))],
            'sale' => ['label' => 'İndirimdekiler', 'products' => $cards($this->catalog->products()->whereHas('activeVariants', fn ($v) => $v->whereColumn('compare_at_price', '>', 'price')))],
        ];

        return array_filter($tabs, fn ($tab) => $tab['products']->isNotEmpty());
    }

    public function collections(): Collection
    {
        return StoreCollection::where('is_active', true)
            ->whereHas('products', fn ($q) => $q->where('is_published', true))
            ->withCount(['products' => fn ($q) => $q->where('is_published', true)])
            ->orderBy('sort')->orderBy('id')->limit(4)->get();
    }

    public function brands(): Collection
    {
        return StoreBrand::where('is_active', true)
            ->whereHas('products', fn ($q) => $q->where('is_published', true))
            ->orderByRaw('logo_path IS NULL')->orderBy('name')->limit(24)->get(['id', 'name', 'slug', 'logo_path', 'updated_at']);
    }

    /** Hakkımızda özeti: yayındaysa başlık, slogan ve rakamlar. */
    public function about(): ?array
    {
        $page = StorePage::where('key', 'about')->where('is_published', true)->first();
        if (! $page) {
            return null;
        }
        $content = array_replace(SitePages::defaults('about'), $page->content ?? []);

        // Fotoğraf: hikâyenin ilk fotoğrafı, yoksa kapak. Oranı kırpmadan yerleşim için birlikte verilir.
        $photo = ($content['story_images'][0] ?? null) ?: ($content['cover'] ?? null);
        $on = fn (string $section) => SitePages::sectionOn($content, $section);

        return [
            'headline' => $content['headline'],
            'tagline' => $content['tagline'],
            'excerpt' => $content['story'] && $on('story') ? Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) SitePages::markdown($content['story'])))), 260) : null,
            'stats' => $on('stats') ? $content['stats'] : [],
            'features' => $on('features') ? $content['features'] : [],
            'badge_value' => $content['badge_value'] ?? null,
            'badge_label' => $content['badge_label'] ?? null,
            'photo' => $photo ? ['src' => route('store.media.page', ['file' => basename($photo), 'w' => 1200]), 'ratio' => ImageVariants::ratio($photo)] : null,
            'url' => route('store.about'),
        ];
    }

    /** Avantaj şeridi: Üst ve Alt Bilgi'deki güven şeridi maddeleri (tek yerden yönetilir). */
    public function perks(): array
    {
        return StoreFooter::config()['strip'];
    }

    /** Kategoriye göre ürün sayısı vb. için tek sorguda kullanılabilecek yardımcı. */
    public static function percentLabel(StoreDiscount $discount): string
    {
        return match ($discount->type) {
            'percent' => '%'.rtrim(rtrim(number_format((float) $discount->value, 2, ',', '.'), '0'), ','),
            'fixed' => '₺'.rtrim(rtrim(number_format((float) $discount->value, 2, ',', '.'), '0'), ','),
            default => 'Kargo bedava',
        };
    }
}
