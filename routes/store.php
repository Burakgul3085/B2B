<?php

use App\Http\Controllers\Store\Admin;
use App\Http\Controllers\Store\Front;
use Illuminate\Support\Facades\Route;

/*
| Mağaza (B2C) — docs/13-magaza-mimarisi.md.
| Rotalar kendi alan adlarına bağlıdır ve web.php'deki B2B rotalarından ÖNCE kaydedilir; B2B rotaları mağaza
| alan adlarında açılmaz (UseAreaSession). Rota adları "store" ile başlar.
|   - Mağaza:   config('store.domain')        → store.*
|   - Yönetim:  config('store.admin_domain')  → store_admin.*
*/

/** @var Closure(string): void $authRoutes  web.php'deki ortak giriş/çıkış/parola sıfırlama rotaları */
/** @var Closure(): void $accountRoutes  web.php'deki ortak Profil ve Güvenlik rotaları */

/* ---------------- Mağaza yönetim paneli ---------------- */
Route::domain(config('store.admin_domain'))->name('store_admin.')->group(function () use ($authRoutes, $accountRoutes) {
    $authRoutes('store_admin');

    Route::middleware(['auth:store_admin', 'auth.session'])->group(function () use ($accountRoutes) {
        Route::get('iki-adimli-dogrulama/kurulum', [Admin\TwoFactorController::class, 'setup'])->name('two-factor.setup');
        Route::post('iki-adimli-dogrulama/kurulum', [Admin\TwoFactorController::class, 'confirm'])->name('two-factor.confirm');
        Route::get('iki-adimli-dogrulama', [Admin\TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
        Route::post('iki-adimli-dogrulama', [Admin\TwoFactorController::class, 'verify'])->middleware('throttle:10,1')->name('two-factor.verify');

        Route::middleware('store.2fa')->group(function () use ($accountRoutes) {
            Route::get('/', Admin\DashboardController::class)->name('dashboard');
            Route::get('islem-gecmisi', Admin\ActivityController::class)->name('activity');

            Route::delete('iki-adimli-dogrulama', [Admin\TwoFactorController::class, 'disable'])->name('two-factor.disable');
            $accountRoutes();

            // Yönetici hesapları: yalnızca süper admin
            Route::middleware('store.super')->group(function () {
                Route::get('yoneticiler', [Admin\AdminController::class, 'index'])->name('admins.index');
                Route::get('yoneticiler/yeni', [Admin\AdminController::class, 'create'])->name('admins.create');
                Route::post('yoneticiler', [Admin\AdminController::class, 'store'])->name('admins.store');
                Route::get('yoneticiler/{storeAdmin}', [Admin\AdminController::class, 'edit'])->name('admins.edit');
                Route::put('yoneticiler/{storeAdmin}', [Admin\AdminController::class, 'update'])->name('admins.update');
                Route::put('yoneticiler/{storeAdmin}/sifre', [Admin\AdminController::class, 'password'])->name('admins.password');
                Route::put('yoneticiler/{storeAdmin}/durum', [Admin\AdminController::class, 'status'])->name('admins.status');
                Route::delete('yoneticiler/{storeAdmin}/iki-adimli-dogrulama', [Admin\AdminController::class, 'resetTwoFactor'])->name('admins.two-factor');
            });
        });
    });
});

/* ---------------- Mağaza (herkese açık) ---------------- */
Route::domain(config('store.domain'))->name('store.')->group(function () {
    Route::get('/', Front\HomeController::class)->name('home');
});
