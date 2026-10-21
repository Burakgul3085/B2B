<?php

namespace App\Http\Controllers\Store\Admin;

use App\Domain\Store\Catalog\BrandService;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreBrand;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Mağaza markaları ve logoları. */
class BrandController extends Controller
{
    public function __construct(private BrandService $brands) {}

    public function index(): View
    {
        return view('store.admin.catalog.brands', [
            'brands' => StoreBrand::withCount('products')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $brand = $this->brands->create($request->only('name') + ['is_active' => true]);

        return back()->with('status', "\"{$brand->name}\" markası eklendi.");
    }

    public function update(Request $request, StoreBrand $storeBrand): RedirectResponse
    {
        $this->brands->update($storeBrand, $request->only('name') + ['is_active' => $request->boolean('is_active')]);

        return back()->with('status', 'Marka kaydedildi.');
    }

    public function destroy(StoreBrand $storeBrand): RedirectResponse
    {
        try {
            $this->brands->delete($storeBrand);
        } catch (DomainException $e) {
            return back()->withErrors(['brand' => $e->getMessage()]);
        }

        return back()->with('status', "\"{$storeBrand->name}\" silindi.");
    }

    public function logo(Request $request, StoreBrand $storeBrand): RedirectResponse
    {
        $request->validate(['logo' => ['required', 'file', 'max:2048']], [], ['logo' => 'logo']);

        try {
            $this->brands->setLogo($storeBrand, $request->file('logo'));
        } catch (DomainException $e) {
            return back()->withErrors(['brand' => $e->getMessage()]);
        }

        return back()->with('status', 'Logo güncellendi.');
    }

    public function removeLogo(StoreBrand $storeBrand): RedirectResponse
    {
        $this->brands->removeLogo($storeBrand);

        return back()->with('status', 'Logo kaldırıldı.');
    }
}
