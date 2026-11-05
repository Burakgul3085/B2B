<?php

namespace App\Http\Controllers\Business;

use App\Domain\Files\ImageResponse;
use App\Domain\Files\ImageVariants;
use App\Domain\Pricing\PromotionAdmin;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\DealerRelationship;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionImage;
use App\Models\PromotionRedemption;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** Bayilere kupon ve kampanyalar (docs/03-is-kurallari.md §4.4). */
class PromotionController extends Controller
{
    public function __construct(private PromotionAdmin $admin) {}

    public function index(): View
    {
        $promotions = Promotion::with('cover')->withCount(['redemptions as used_count' => fn ($q) => $q->whereNull('released_at')])
            ->withSum(['redemptions as used_amount' => fn ($q) => $q->whereNull('released_at')], 'amount')
            ->orderByDesc('is_active')->orderByDesc('id')->get();

        return view('business.promotions.index', ['promotions' => $promotions]);
    }

    public function create(): View
    {
        return view('business.promotions.form', $this->formData(new Promotion));
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            $promotion = $this->admin->save($request->all());
        } catch (DomainException $e) {
            return back()->withErrors(['promotion' => $e->getMessage()])->withInput();
        }

        return redirect()->route('business.promotions.edit', $promotion)->with('status', ($promotion->isCoupon() ? 'Kupon' : 'Kampanya').' kaydedildi.');
    }

    public function edit(Promotion $promotion): View
    {
        return view('business.promotions.form', $this->formData($promotion) + [
            'redemptions' => PromotionRedemption::where('promotion_id', $promotion->id)->with(['order:id,order_no,status', 'relationship.dealer'])
                ->latest('id')->limit(100)->get(),
        ]);
    }

    public function update(Request $request, Promotion $promotion): RedirectResponse
    {
        try {
            $this->admin->save($request->all(), $promotion);
        } catch (DomainException $e) {
            return back()->withErrors(['promotion' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'Değişiklikler kaydedildi.');
    }

    public function toggle(Promotion $promotion): RedirectResponse
    {
        $this->admin->toggle($promotion);

        return back()->with('status', $promotion->is_active ? 'Etkinleştirildi.' : 'Pasife alındı.');
    }

    public function destroy(Promotion $promotion): RedirectResponse
    {
        try {
            $this->admin->delete($promotion);
        } catch (DomainException $e) {
            return back()->withErrors(['promotion' => $e->getMessage()]);
        }

        return redirect()->route('business.promotions.index')->with('status', 'Silindi.');
    }

    /** Kampanya görseli (işletme kapsamı: başka işletmenin görseli 404). */
    public function image(Request $request, PromotionImage $promotionImage, ImageVariants $variants): Response
    {
        $path = $variants->path($promotionImage->path, $request->integer('w') ?: null) ?? abort(404);

        return ImageResponse::make($request, $path, 'private, max-age=3600');
    }

    private function formData(Promotion $promotion): array
    {
        return [
            'promotion' => $promotion,
            'images' => $promotion->exists ? $promotion->images()->get() : collect(),
            'categories' => Category::paths(),
            'dealers' => DealerRelationship::with('dealer')->orderBy('dealer_code')->get()->mapWithKeys(fn ($r) => [$r->id => $r->dealer_code.' · '.$r->dealer->legal_name])->all(),
            'skus' => $promotion->product_ids ? Product::whereIn('id', $promotion->product_ids)->orderBy('sku')->pluck('sku')->implode("\n") : '',
            'redemptions' => collect(),
        ];
    }
}
