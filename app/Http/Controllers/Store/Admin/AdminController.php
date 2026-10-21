<?php

namespace App\Http\Controllers\Store\Admin;

use App\Domain\Store\Admins\StoreAdminService;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreAdmin;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Yönetici hesapları (yalnızca süper admin, bkz. store.super). Süper admin yönetici açar, bilgilerini ve şifresini
 * değiştirir, pasife alır. Kendini ve diğer süper adminleri yönetemez; onları platform yönetir.
 */
class AdminController extends Controller
{
    public function __construct(private StoreAdminService $admins) {}

    public function index(Request $request): View
    {
        return view('store.admin.admins.index', [
            'admins' => StoreAdmin::orderByDesc('is_super')->orderByDesc('is_active')->orderBy('name')->get(),
            'me' => $request->user('store_admin'),
        ]);
    }

    public function create(): View
    {
        return view('store.admin.admins.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', Password::defaults()],
        ], [], ['name' => 'ad soyad', 'email' => 'e-posta', 'password' => 'şifre']);

        try {
            $admin = $this->admins->create($data, false, $request->user('store_admin'));
        } catch (DomainException $e) {
            return back()->withErrors(['email' => $e->getMessage()])->withInput($request->except('password'));
        }

        return redirect()->route('store_admin.admins.index')
            ->with('status', "{$admin->name} yönetici olarak eklendi. Giriş bilgilerini kendisine iletin.");
    }

    public function edit(Request $request, StoreAdmin $storeAdmin): View
    {
        abort_unless($this->admins->canManage($storeAdmin, $request->user('store_admin')), 403);

        return view('store.admin.admins.edit', ['target' => $storeAdmin]);
    }

    public function update(Request $request, StoreAdmin $storeAdmin): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255'],
        ], [], ['name' => 'ad soyad', 'email' => 'e-posta']);

        return $this->attempt(fn () => $this->admins->update($storeAdmin, $data, $request->user('store_admin')), 'Bilgiler güncellendi.');
    }

    public function password(Request $request, StoreAdmin $storeAdmin): RedirectResponse
    {
        $data = $request->validate(['password' => ['required', 'string', 'confirmed', Password::defaults()]], [], ['password' => 'yeni şifre']);

        return $this->attempt(fn () => $this->admins->setPassword($storeAdmin, $data['password'], $request->user('store_admin')),
            'Yeni şifre belirlendi. Kişiye iletin; kendisi Profil ve Güvenlik\'ten değiştirebilir.');
    }

    public function status(Request $request, StoreAdmin $storeAdmin): RedirectResponse
    {
        $active = $request->boolean('active');

        return $this->attempt(fn () => $this->admins->setActive($storeAdmin, $active, $request->user('store_admin')),
            $active ? 'Hesap yeniden kullanıma açıldı.' : 'Hesap pasife alındı; açık oturumu kapandı.');
    }

    public function resetTwoFactor(Request $request, StoreAdmin $storeAdmin): RedirectResponse
    {
        return $this->attempt(fn () => $this->admins->resetTwoFactor($storeAdmin, $request->user('store_admin')),
            'İki adımlı doğrulama sıfırlandı.');
    }

    private function attempt(callable $action, string $message): RedirectResponse
    {
        try {
            $action();
        } catch (DomainException $e) {
            return back()->withErrors(['admin' => $e->getMessage()])->withInput();
        }

        return back()->with('status', $message);
    }
}
