<?php

namespace App\Http\Controllers\Store\Front;

use App\Domain\Store\Customers\AddressService;
use App\Http\Controllers\Controller;
use App\Models\Store\District;
use App\Models\Store\Province;
use App\Models\Store\StoreCustomerAddress;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Adres defteri. Müşteri yalnızca kendi adreslerini görür ve değiştirir (başkasınınki 404). */
class AddressController extends Controller
{
    private const FIELDS = ['title', 'first_name', 'last_name', 'phone', 'province_id', 'district_id', 'neighborhood', 'address_line', 'postal_code', 'is_default'];

    public function __construct(private AddressService $addresses) {}

    public function create(Request $request): View
    {
        $customer = $request->user('customer');

        return view('store.front.account.address-form', [
            'address' => new StoreCustomerAddress(['first_name' => $customer->first_name, 'last_name' => $customer->last_name, 'phone' => $customer->phone]),
            'provinces' => Province::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            $this->addresses->create($request->user('customer'), $request->only(self::FIELDS));
        } catch (DomainException $e) {
            return back()->withErrors(['address' => $e->getMessage()])->withInput();
        }

        return redirect()->intended(route('store.account').'#adresler')->with('status', 'Adres kaydedildi.');
    }

    public function edit(Request $request, int $address): View
    {
        return view('store.front.account.address-form', [
            'address' => $this->owned($request, $address),
            'provinces' => Province::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function update(Request $request, int $address): RedirectResponse
    {
        $this->addresses->update($this->owned($request, $address), $request->only(self::FIELDS));

        return redirect()->to(route('store.account').'#adresler')->with('status', 'Adres güncellendi.');
    }

    public function makeDefault(Request $request, int $address): RedirectResponse
    {
        $this->addresses->makeDefault($this->owned($request, $address));

        return back()->with('status', 'Varsayılan adres değiştirildi.');
    }

    public function destroy(Request $request, int $address): RedirectResponse
    {
        $this->addresses->delete($this->owned($request, $address));

        return back()->with('status', 'Adres silindi.');
    }

    /** Seçilen ilin ilçeleri (adres formundaki ilçe listesi). */
    public function districts(int $province): JsonResponse
    {
        return response()->json(District::where('province_id', $province)->orderBy('name')->get(['id', 'name']))
            ->header('Cache-Control', 'public, max-age=86400');
    }

    private function owned(Request $request, int $id): StoreCustomerAddress
    {
        return StoreCustomerAddress::where('customer_id', $request->user('customer')->id)->findOrFail($id);
    }
}
