<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Store\Admins\StoreAdminService;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreAdmin;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Platform → Mağaza yöneticileri. Süper admini yalnızca platform açar ve yönetir; gerektiğinde yöneticileri de
 * yönetebilir (pasife alma, şifre, iki adımlı doğrulamayı sıfırlama).
 */
class StoreAdminController extends Controller
{
    public function __construct(private StoreAdminService $admins) {}

    public function index(): View
    {
        return view('platform.store-admins.index', [
            'admins' => StoreAdmin::orderByDesc('is_super')->orderByDesc('is_active')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('platform.store-admins.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', Password::defaults()],
            'is_super' => ['boolean'],
        ], [], ['name' => 'ad soyad', 'email' => 'e-posta', 'password' => 'şifre']);

        try {
            $admin = $this->admins->create($data, $request->boolean('is_super'), null);
        } catch (DomainException $e) {
            return back()->withErrors(['email' => $e->getMessage()])->withInput($request->except('password'));
        }

        return redirect()->route('platform.store-admins.show', $admin)
            ->with('status', "{$admin->roleLabel()} hesabı açıldı. Giriş adresi ve bilgileri kişiye iletin.");
    }

    public function show(StoreAdmin $storeAdmin): View
    {
        return view('platform.store-admins.show', ['target' => $storeAdmin]);
    }

    public function update(Request $request, StoreAdmin $storeAdmin): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255'],
        ], [], ['name' => 'ad soyad', 'email' => 'e-posta']);

        return $this->attempt(fn () => $this->admins->update($storeAdmin, $data, null), 'Bilgiler güncellendi.');
    }

    public function password(Request $request, StoreAdmin $storeAdmin): RedirectResponse
    {
        $data = $request->validate(['password' => ['required', 'string', 'confirmed', Password::defaults()]], [], ['password' => 'yeni şifre']);

        return $this->attempt(fn () => $this->admins->setPassword($storeAdmin, $data['password'], null), 'Yeni şifre belirlendi.');
    }

    public function status(Request $request, StoreAdmin $storeAdmin): RedirectResponse
    {
        $active = $request->boolean('active');

        return $this->attempt(fn () => $this->admins->setActive($storeAdmin, $active, null),
            $active ? 'Hesap yeniden kullanıma açıldı.' : 'Hesap pasife alındı.');
    }

    public function resetTwoFactor(StoreAdmin $storeAdmin): RedirectResponse
    {
        return $this->attempt(fn () => $this->admins->resetTwoFactor($storeAdmin, null),
            'İki adımlı doğrulama sıfırlandı; bir sonraki girişte yeniden kurulacak.');
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
