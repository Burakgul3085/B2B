<?php

namespace App\Http\Controllers\Store\Front;

use App\Domain\Store\Customers\CustomerService;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreCustomer;
use App\Models\Store\StoreOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** Hesabım: bilgiler, e-posta, parola ve adres özeti. Siparişler M6'da eklenir. */
class AccountController extends Controller
{
    public function __construct(private CustomerService $customers) {}

    public function show(Request $request): View
    {
        $customer = $this->customer($request);

        return view('store.front.account.show', [
            'customer' => $customer,
            'addresses' => $customer->addresses()->with(['province', 'district'])->get(),
            'orders' => StoreOrder::where('customer_id', $customer->id)->latest('id')->limit(3)->get(),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $this->customers->updateProfile($this->customer($request), $request->only(['first_name', 'last_name', 'phone', 'marketing']));

        return back()->with('status', 'Bilgileriniz güncellendi.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $customer = $this->customer($request);
        $this->customers->changePassword($customer, (string) $request->input('current_password'), (string) $request->input('password'), (string) $request->input('password_confirmation'));

        // Diğer cihazlardaki oturumlar sonlanır; bu oturum yenilenir.
        Auth::guard('customer')->logoutOtherDevices((string) $request->input('password'));
        $request->session()->regenerate();

        return back()->with('status', 'Parolanız değiştirildi.');
    }

    public function updateEmail(Request $request): RedirectResponse
    {
        $email = $this->customers->changeEmail($this->customer($request), (string) $request->input('email_password'), (string) $request->input('email'));

        return back()->with('status', "E-posta adresiniz {$email} olarak güncellendi.");
    }

    private function customer(Request $request): StoreCustomer
    {
        return $request->user('customer');
    }
}
