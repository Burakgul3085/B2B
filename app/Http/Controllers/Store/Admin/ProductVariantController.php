<?php

namespace App\Http\Controllers\Store\Admin;

use App\Domain\Store\Catalog\ProductService;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreProductVariant;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Ürün varyantları (satılabilir birimler). Hatalar, hangi varyantın formunda oluştuysa orada gösterilir. */
class ProductVariantController extends Controller
{
    private const FIELDS = ['sku', 'barcode', 'option_value', 'price', 'compare_at_price', 'vat_rate', 'desi', 'weight_grams', 'is_active'];

    public function __construct(private ProductService $products) {}

    public function store(Request $request, StoreProduct $storeProduct): RedirectResponse
    {
        try {
            $variant = $this->products->addVariant($storeProduct, $request->only(self::FIELDS) + ['is_active' => true]);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors(), 'newVariant')->withInput();
        } catch (DomainException $e) {
            return back()->withErrors(['product' => $e->getMessage()]);
        }

        return back()->with('status', "\"{$variant->option_value}\" seçeneği eklendi.");
    }

    public function update(Request $request, StoreProductVariant $storeVariant): RedirectResponse
    {
        try {
            $this->products->updateVariant($storeVariant, $request->only(self::FIELDS) + ['is_active' => $request->boolean('is_active')]);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors(), 'variant'.$storeVariant->id)->withInput();
        } catch (DomainException $e) {
            return back()->withErrors(['product' => $e->getMessage()]);
        }

        return back()->with('status', 'Varyant kaydedildi.');
    }

    public function destroy(StoreProductVariant $storeVariant): RedirectResponse
    {
        try {
            $this->products->deleteVariant($storeVariant);
        } catch (DomainException $e) {
            return back()->withErrors(['product' => $e->getMessage()]);
        }

        return back()->with('status', 'Varyant silindi.');
    }
}
