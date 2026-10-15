<?php

use App\Http\Controllers\Auth\AccountController;
use App\Http\Controllers\Auth\ActivationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Business;
use App\Http\Controllers\Dealer;
use App\Http\Controllers\Platform;
use Illuminate\Support\Facades\Route;

/*
| Üç ayrı giriş alanı: /platform, /toptanci, /bayi.
| Her grup yalnızca kendi guard'ını kullanır; oturum çerezleri URL önekine bağlıdır
| (bkz. App\Http\Middleware\UseAreaSession).
*/

Route::view('/', 'welcome')->name('home');

/**
 * Her alanın ortak kimlik rotaları: giriş, çıkış, parola sıfırlama, (gerekirse) aktivasyon, hesap.
 */
$authRoutes = function (string $name, bool $activation) {
    Route::middleware("guest:{$name}")->group(function () use ($activation) {
        Route::get('login', [LoginController::class, 'show'])->name('login');
        Route::post('login', [LoginController::class, 'store'])->middleware('throttle:20,1')->name('login.store');
        Route::get('parolami-unuttum', [PasswordResetController::class, 'request'])->name('password.request');
        Route::post('parolami-unuttum', [PasswordResetController::class, 'email'])->name('password.email');
        Route::get('parola-sifirla/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
        Route::post('parola-sifirla', [PasswordResetController::class, 'update'])->name('password.update');

        if ($activation) {
            Route::get('aktivasyon/{token}', [ActivationController::class, 'show'])->name('activation.show');
            Route::post('aktivasyon', [ActivationController::class, 'store'])->name('activation.store');
        }
    });

    Route::post('cikis', [LoginController::class, 'destroy'])->middleware("auth:{$name}")->name('logout');
};

$accountRoutes = function () {
    Route::get('hesabim', [AccountController::class, 'show'])->name('account');
    Route::put('hesabim', [AccountController::class, 'updateProfile'])->name('account.profile');
    Route::put('hesabim/parola', [AccountController::class, 'updatePassword'])->name('account.password');
};

/* ---------------- Platform yönetimi ---------------- */
Route::prefix('platform')->name('platform.')->middleware('platform.ip')->group(function () use ($authRoutes, $accountRoutes) {
    $authRoutes('platform', false);

    Route::middleware(['auth:platform', 'auth.session'])->group(function () use ($accountRoutes) {
        Route::get('iki-adimli-dogrulama/kurulum', [Platform\TwoFactorController::class, 'setup'])->name('two-factor.setup');
        Route::post('iki-adimli-dogrulama/kurulum', [Platform\TwoFactorController::class, 'confirm'])->name('two-factor.confirm');
        Route::get('iki-adimli-dogrulama', [Platform\TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
        Route::post('iki-adimli-dogrulama', [Platform\TwoFactorController::class, 'verify'])->middleware('throttle:10,1')->name('two-factor.verify');

        Route::middleware('platform.2fa')->group(function () use ($accountRoutes) {
            Route::get('/', Platform\DashboardController::class)->name('dashboard');

            Route::get('isletmeler', [Platform\BusinessController::class, 'index'])->name('businesses.index');
            Route::get('isletmeler/yeni', [Platform\BusinessController::class, 'create'])->name('businesses.create');
            Route::post('isletmeler', [Platform\BusinessController::class, 'store'])->name('businesses.store');
            Route::get('isletmeler/{business}', [Platform\BusinessController::class, 'show'])->name('businesses.show');
            Route::get('isletmeler/{business}/duzenle', [Platform\BusinessController::class, 'edit'])->name('businesses.edit');
            Route::put('isletmeler/{business}', [Platform\BusinessController::class, 'update'])->name('businesses.update');
            Route::put('isletmeler/{business}/durum', [Platform\BusinessController::class, 'updateStatus'])->name('businesses.status');
            Route::post('isletmeler/{business}/lisans', [Platform\BusinessController::class, 'storeLicense'])->name('businesses.licenses.store');
            Route::put('isletmeler/{business}/moduller', [Platform\BusinessController::class, 'updateModules'])->name('businesses.modules');
            Route::post('isletmeler/{business}/aktivasyon', [Platform\BusinessController::class, 'resendActivation'])->name('businesses.activation');

            Route::get('denetim-kayitlari', Platform\AuditLogController::class)->name('audit-logs');

            $accountRoutes();
        });
    });
});

/* ---------------- Toptancı paneli ---------------- */
Route::prefix('toptanci')->name('business.')->group(function () use ($authRoutes, $accountRoutes) {
    $authRoutes('business', true);

    Route::middleware(['auth:business', 'auth.session', 'business.context'])->group(function () use ($accountRoutes) {
        Route::get('/', Business\DashboardController::class)->name('dashboard');
        Route::get('ayarlar', [Business\SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('ayarlar', [Business\SettingsController::class, 'update'])->name('settings.update');

        $accountRoutes();
    });
});

/* ---------------- Bayi paneli ---------------- */
Route::prefix('bayi')->name('dealer.')->group(function () use ($authRoutes, $accountRoutes) {
    $authRoutes('dealer', true);

    Route::middleware(['auth:dealer', 'auth.session', 'dealer.context'])->group(function () use ($accountRoutes) {
        Route::get('toptanci-sec', [Dealer\SelectBusinessController::class, 'show'])->name('select-business');
        Route::post('toptanci-sec', [Dealer\SelectBusinessController::class, 'store'])->name('select-business.store');

        Route::get('/', Dealer\DashboardController::class)->name('dashboard');

        $accountRoutes();
    });
});
