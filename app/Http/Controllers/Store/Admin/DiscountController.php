<?php

namespace App\Http\Controllers\Store\Admin;

use App\Domain\Store\Discounts\DiscountAdmin;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreDiscount;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Kampanya ve kuponlar: liste (kullanım sayısı ve toplam indirimle), ekleme, düzenleme, silme. */
class DiscountController extends Controller
{
    public function __construct(private DiscountAdmin $discounts) {}

    public function index(): View
    {
        return view('store.admin.discounts.index', [
            'discounts' => StoreDiscount::query()
                ->withCount(['redemptions as used_count' => fn ($q) => $q->where('status', 'active')])
                ->withSum(['redemptions as used_amount' => fn ($q) => $q->where('status', 'active')], 'amount')
                ->orderByDesc('is_active')->latest('id')->get(),
        ]);
    }

    public function create(): View
    {
        return view('store.admin.discounts.form', ['discount' => new StoreDiscount(['type' => 'percent']), 'used' => false]);
    }

    public function store(Request $request): RedirectResponse
    {
        $discount = $this->discounts->save($request->all());

        return redirect()->route('store_admin.discounts.edit', $discount)->with('status', 'İndirim oluşturuldu.');
    }

    public function edit(StoreDiscount $storeDiscount): View
    {
        return view('store.admin.discounts.form', [
            'discount' => $storeDiscount,
            'used' => $storeDiscount->redemptions()->exists(),
            'recent' => $storeDiscount->redemptions()->with('order:id,order_no,status')->latest('id')->limit(20)->get(),
        ]);
    }

    public function update(Request $request, StoreDiscount $storeDiscount): RedirectResponse
    {
        try {
            $this->discounts->save($request->all(), $storeDiscount);
        } catch (DomainException $e) {
            return back()->withErrors(['code' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'İndirim güncellendi.');
    }

    public function destroy(StoreDiscount $storeDiscount): RedirectResponse
    {
        try {
            $this->discounts->delete($storeDiscount);
        } catch (DomainException $e) {
            return back()->withErrors(['discount' => $e->getMessage()]);
        }

        return redirect()->route('store_admin.discounts.index')->with('status', 'İndirim silindi.');
    }
}
