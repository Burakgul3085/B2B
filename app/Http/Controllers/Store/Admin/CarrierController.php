<?php

namespace App\Http\Controllers\Store\Admin;

use App\Domain\Store\Shipping\CarrierService;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreCarrier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Kargo firmaları: gönderide seçilir; takip bağlantısı müşteriye gösterilir. */
class CarrierController extends Controller
{
    public function __construct(private CarrierService $carriers) {}

    public function index(): View
    {
        return view('store.admin.storefront.carriers', ['carriers' => StoreCarrier::orderBy('sort_order')->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->carriers->save(['is_active' => true] + $request->all());

        return back()->with('status', 'Kargo firması eklendi.');
    }

    public function update(Request $request, StoreCarrier $storeCarrier): RedirectResponse
    {
        $this->carriers->save($request->all(), $storeCarrier);

        return back()->with('status', 'Kargo firması güncellendi.');
    }

    public function destroy(StoreCarrier $storeCarrier): RedirectResponse
    {
        $this->carriers->delete($storeCarrier);

        return back()->with('status', 'Kargo firması silindi.');
    }
}
