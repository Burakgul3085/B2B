<?php

namespace App\Http\Controllers\Business;

use App\Domain\Storefront\BannerMedia;
use App\Domain\Storefront\BannerService;
use App\Domain\Storefront\ShowcaseService;
use App\Http\Controllers\Concerns\ServesBannerMedia;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\DealerGroup;
use App\Models\HomeBanner;
use App\Models\Product;
use App\Models\Showcase;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Vitrin: bayi ana sayfasındaki slaytlar ve ürün vitrinleri. */
class StorefrontController extends Controller
{
    use ServesBannerMedia;

    public function __construct(private BannerService $banners, private ShowcaseService $showcases) {}

    public function index(Request $request): View
    {
        return view('business.storefront.index', [
            'banners' => HomeBanner::with('group')->orderByRaw("placement = 'hero' DESC")->orderBy('sort')->orderBy('id')->get(),
            'showcases' => Showcase::with('items.product:id,name,sku')->orderBy('sort')->orderBy('id')->get(),
            'groups' => DealerGroup::orderBy('name')->pluck('name', 'id'),
            'targets' => [
                'category' => Category::where('is_active', true)->with('parent:id,name')->orderBy('name')->get()->mapWithKeys(fn ($c) => [$c->id => $c->fullName()])->sort()->all(),
                'brand' => Brand::where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(),
                'product' => Product::notArchived()->orderBy('name')->limit(500)->get(['id', 'name', 'sku'])->mapWithKeys(fn ($p) => [$p->id => "{$p->name} ({$p->sku})"])->all(),
                'showcase' => Showcase::orderBy('sort')->pluck('name', 'id')->all(),
            ],
            'tab' => $request->query('sekme') === 'vitrinler' ? 'showcases' : 'banners',
        ]);
    }

    /* ---------------- Slaytlar ---------------- */

    public function storeBanner(Request $request): RedirectResponse|JsonResponse
    {
        $data = $this->validateBanner($request, true);

        $this->attempt(fn () => $this->banners->create($data, $this->files($request)));

        return $this->done($request, 'Slayt eklendi. Bayileriniz ana sayfalarında hemen görür.');
    }

    public function updateBanner(Request $request, HomeBanner $banner): RedirectResponse|JsonResponse
    {
        $data = $this->validateBanner($request, false);

        $this->attempt(fn () => $this->banners->update($banner, $data, $this->files($request), $request->boolean('remove_mobile'), $request->boolean('remove_poster')));

        return $this->done($request, 'Slayt güncellendi.');
    }

    public function destroyBanner(HomeBanner $banner): RedirectResponse
    {
        $this->banners->delete($banner);

        return redirect()->route('business.storefront.index')->with('status', 'Slayt silindi.');
    }

    public function moveBanner(Request $request, HomeBanner $banner): RedirectResponse
    {
        $this->banners->reorder($this->moved(HomeBanner::orderBy('sort')->orderBy('id')->pluck('id')->all(), $banner->id, $request->input('direction')));

        return redirect()->route('business.storefront.index');
    }

    public function media(Request $request, HomeBanner $banner): BinaryFileResponse
    {
        return $this->bannerMedia($request, $banner);
    }

    /* ---------------- Vitrinler ---------------- */

    public function storeShowcase(Request $request): RedirectResponse
    {
        $data = $this->validateShowcase($request);
        $this->attempt(fn () => $this->showcases->create($data), 'showcase');

        return $this->toShowcases('Vitrin oluşturuldu.');
    }

    public function updateShowcase(Request $request, Showcase $showcase): RedirectResponse
    {
        $data = $this->validateShowcase($request);
        $this->attempt(fn () => $this->showcases->update($showcase, $data), 'showcase');

        return $this->toShowcases('Vitrin güncellendi.');
    }

    public function destroyShowcase(Showcase $showcase): RedirectResponse
    {
        $this->showcases->delete($showcase);

        return $this->toShowcases('Vitrin silindi.');
    }

    public function moveShowcase(Request $request, Showcase $showcase): RedirectResponse
    {
        $this->showcases->reorder($this->moved(Showcase::orderBy('sort')->orderBy('id')->pluck('id')->all(), $showcase->id, $request->input('direction')));

        return $this->toShowcases();
    }

    public function addProduct(Request $request, Showcase $showcase): RedirectResponse
    {
        $data = $request->validate(['product_id' => ['required', 'integer']], [], ['product_id' => 'ürün']);
        $this->attempt(fn () => $this->showcases->addProduct($showcase, (int) $data['product_id']), 'product_id');

        return $this->toShowcases('Ürün vitrine eklendi.');
    }

    public function removeProduct(Showcase $showcase, Product $product): RedirectResponse
    {
        abort_unless($showcase->items()->where('product_id', $product->id)->exists(), 404);
        $this->showcases->removeProduct($showcase, $product->id);

        return $this->toShowcases('Ürün vitrinden çıkarıldı.');
    }

    /** Ürün sayfasından: ürünün yer aldığı elle seçilen vitrinler. */
    public function productShowcases(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate(['showcases' => ['nullable', 'array'], 'showcases.*' => ['integer']]);
        $this->attempt(fn () => $this->showcases->syncProduct($product, $data['showcases'] ?? []), 'showcases');

        return back()->with('status', 'Ürünün vitrinleri güncellendi.');
    }

    /* ---------------- Yardımcılar ---------------- */

    private function validateBanner(Request $request, bool $creating): array
    {
        $media = ['file', 'max:'.(BannerMedia::MAX_VIDEO_BYTES / 1024)];
        $image = ['nullable', 'file', 'max:'.(BannerMedia::MAX_IMAGE_BYTES / 1024)];

        return $request->validate([
            'media' => [$creating ? 'required' : 'nullable', ...$media],
            'mobile_media' => $image,
            'poster' => $image,
            'placement' => ['nullable', Rule::in(array_keys(HomeBanner::PLACEMENTS))],
            'title' => ['nullable', 'string', 'max:120'],
            'subtitle' => ['nullable', 'string', 'max:240'],
            'button_label' => ['nullable', 'string', 'max:40'],
            'link_type' => ['required', Rule::in(array_keys(HomeBanner::LINK_TYPES))],
            'link_id' => ['nullable', 'integer', Rule::requiredIf(fn () => in_array($request->input('link_type'), ['category', 'brand', 'product', 'showcase'], true))],
            'text_position' => ['required', Rule::in(array_keys(HomeBanner::TEXT_POSITIONS))],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'dealer_group_id' => ['nullable', 'integer', Rule::exists('dealer_groups', 'id')->where('business_id', $request->user()->business_id)],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'media.max' => 'Dosya en fazla 50 MB olabilir.',
            'link_id.required' => 'Bağlantı için listeden bir hedef seçin.',
        ], [
            'media' => 'görsel veya video', 'mobile_media' => 'mobil görsel', 'poster' => 'kapak görseli', 'title' => 'başlık',
            'subtitle' => 'alt başlık', 'button_label' => 'düğme yazısı', 'link_type' => 'bağlantı', 'link_id' => 'bağlantı hedefi',
            'starts_on' => 'başlangıç', 'ends_on' => 'bitiş', 'dealer_group_id' => 'bayi grubu',
        ]);
    }

    private function validateShowcase(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'type' => ['required', Rule::in(array_keys(Showcase::TYPES))],
            'source_id' => ['nullable', 'integer'],
            'product_limit' => ['required', 'integer', Rule::in([4, 8, 12])],
            'is_active' => ['nullable', 'boolean'],
        ], [], ['name' => 'vitrin adı', 'type' => 'vitrin türü', 'source_id' => 'kaynak', 'product_limit' => 'ürün sayısı']);
    }

    /** @return array{media?: mixed, mobile_media?: mixed, poster?: mixed} */
    private function files(Request $request): array
    {
        return array_filter(['media' => $request->file('media'), 'mobile_media' => $request->file('mobile_media'), 'poster' => $request->file('poster')]);
    }

    /** İş kuralı hatasını forma geri gönderir. */
    private function attempt(callable $action, string $field = 'media'): void
    {
        try {
            $action();
        } catch (DomainException $e) {
            throw ValidationException::withMessages([$field => $e->getMessage()]);
        }
    }

    /** @param  int[]  $ids */
    private function moved(array $ids, int $id, ?string $direction): array
    {
        $i = array_search($id, $ids, true);
        $j = $direction === 'up' ? $i - 1 : $i + 1;
        if ($i !== false && isset($ids[$j])) {
            [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        }

        return $ids;
    }

    /** Yükleme ilerlemesi gösteren form JSON bekler; normal form yönlendirilir. */
    private function done(Request $request, string $status): RedirectResponse|JsonResponse
    {
        session()->flash('status', $status);
        $url = route('business.storefront.index');

        return $request->wantsJson() ? response()->json(['redirect' => $url]) : redirect()->to($url);
    }

    private function toShowcases(?string $status = null): RedirectResponse
    {
        $redirect = redirect()->route('business.storefront.index', ['sekme' => 'vitrinler']);

        return $status ? $redirect->with('status', $status) : $redirect;
    }
}
