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

            // Katalog (M2)
            Route::prefix('katalog')->group(function () {
                Route::get('urunler', [Admin\ProductController::class, 'index'])->name('products.index');
                Route::get('urunler/yeni', [Admin\ProductController::class, 'create'])->name('products.create');
                Route::post('urunler', [Admin\ProductController::class, 'store'])->name('products.store');
                Route::post('urunler/toplu', [Admin\ProductController::class, 'bulk'])->name('products.bulk');
                Route::get('urunler/ice-aktar', [Admin\ProductImportController::class, 'show'])->name('products.import');
                Route::post('urunler/ice-aktar', [Admin\ProductImportController::class, 'store'])->middleware('throttle:10,1')->name('products.import.store');
                Route::get('urunler/ice-aktar/sablon', [Admin\ProductImportController::class, 'template'])->name('products.import.template');
                Route::get('urunler/{storeProduct}', [Admin\ProductController::class, 'edit'])->name('products.edit');
                Route::put('urunler/{storeProduct}', [Admin\ProductController::class, 'update'])->name('products.update');
                Route::delete('urunler/{storeProduct}', [Admin\ProductController::class, 'destroy'])->name('products.destroy');

                Route::post('urunler/{storeProduct}/varyantlar', [Admin\ProductVariantController::class, 'store'])->name('variants.store');
                Route::put('varyantlar/{storeVariant}', [Admin\ProductVariantController::class, 'update'])->name('variants.update');
                Route::delete('varyantlar/{storeVariant}', [Admin\ProductVariantController::class, 'destroy'])->name('variants.destroy');

                Route::post('urunler/{storeProduct}/gorseller', [Admin\ProductImageController::class, 'store'])->name('images.store');
                Route::put('gorseller/{storeImage}/ana', [Admin\ProductImageController::class, 'primary'])->name('images.primary');
                Route::delete('gorseller/{storeImage}', [Admin\ProductImageController::class, 'destroy'])->name('images.destroy');
                Route::get('gorseller/{storeImage}', [Admin\ProductImageController::class, 'show'])->name('images.show');

                Route::get('kategoriler', [Admin\CategoryController::class, 'index'])->name('categories.index');
                Route::post('kategoriler', [Admin\CategoryController::class, 'store'])->name('categories.store');
                Route::put('kategoriler/{storeCategory}', [Admin\CategoryController::class, 'update'])->name('categories.update');
                Route::put('kategoriler/{storeCategory}/sira', [Admin\CategoryController::class, 'move'])->name('categories.move');
                Route::delete('kategoriler/{storeCategory}', [Admin\CategoryController::class, 'destroy'])->name('categories.destroy');

                Route::get('markalar', [Admin\BrandController::class, 'index'])->name('brands.index');
                Route::post('markalar', [Admin\BrandController::class, 'store'])->name('brands.store');
                Route::put('markalar/{storeBrand}', [Admin\BrandController::class, 'update'])->name('brands.update');
                Route::delete('markalar/{storeBrand}', [Admin\BrandController::class, 'destroy'])->name('brands.destroy');
                Route::post('markalar/{storeBrand}/logo', [Admin\BrandController::class, 'logo'])->name('brands.logo');
                Route::delete('markalar/{storeBrand}/logo', [Admin\BrandController::class, 'removeLogo'])->name('brands.logo.destroy');
                Route::get('markalar/{storeBrand}/logo', [Admin\ProductImageController::class, 'brandLogo'])->name('brands.logo.show');
            });

            // Stok (M3)
            Route::prefix('stok')->name('stock.')->group(function () {
                Route::get('/', [Admin\StockController::class, 'index'])->name('index');
                Route::get('hareketler', [Admin\StockController::class, 'movements'])->name('movements');
                Route::post('hareketler/{storeMovement}/geri-al', [Admin\StockController::class, 'reverse'])->name('reverse');
                Route::post('{storeVariant}/giris', [Admin\StockController::class, 'receive'])->name('receive');
                Route::post('{storeVariant}/sayim', [Admin\StockController::class, 'count'])->name('count');
                Route::post('{storeVariant}/duzeltme', [Admin\StockController::class, 'adjust'])->name('adjust');
            });

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
