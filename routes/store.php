<?php

use App\Http\Controllers\RobotsController;
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
    Route::get('robots.txt', RobotsController::class)->name('robots');
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

            // Vitrin (M4)
            Route::prefix('vitrin')->group(function () {
                Route::get('bannerlar', [Admin\BannerController::class, 'index'])->name('banners.index');
                Route::post('bannerlar', [Admin\BannerController::class, 'store'])->name('banners.store');
                Route::put('bannerlar/{storeBanner}', [Admin\BannerController::class, 'update'])->name('banners.update');
                Route::put('bannerlar/{storeBanner}/sira', [Admin\BannerController::class, 'move'])->name('banners.move');
                Route::delete('bannerlar/{storeBanner}', [Admin\BannerController::class, 'destroy'])->name('banners.destroy');
                Route::get('bannerlar/{storeBanner}/gorsel', [Admin\BannerController::class, 'image'])->name('banners.image');

                Route::get('sayfalar', [Admin\PageController::class, 'index'])->name('pages.index');
                Route::get('sayfalar/yeni', [Admin\PageController::class, 'create'])->name('pages.create');
                Route::post('sayfalar', [Admin\PageController::class, 'store'])->name('pages.store');
                Route::get('sayfalar/{storePage}', [Admin\PageController::class, 'edit'])->name('pages.edit');
                Route::put('sayfalar/{storePage}', [Admin\PageController::class, 'update'])->name('pages.update');
                Route::delete('sayfalar/{storePage}', [Admin\PageController::class, 'destroy'])->name('pages.destroy');

                Route::get('ayarlar', [Admin\SettingsController::class, 'edit'])->name('settings.edit');
                Route::put('ayarlar', [Admin\SettingsController::class, 'update'])->name('settings.update');
                Route::put('ayarlar/yayin', [Admin\SettingsController::class, 'open'])->name('settings.open');
            });

            // Siparişler (M6)
            Route::get('siparisler', [Admin\OrderController::class, 'index'])->name('orders.index');
            Route::get('siparisler/{storeOrder}', [Admin\OrderController::class, 'show'])->name('orders.show');
            Route::post('siparisler/{storeOrder}/onayla', [Admin\OrderController::class, 'approve'])->name('orders.approve');
            Route::post('siparisler/{storeOrder}/hazirla', [Admin\OrderController::class, 'prepare'])->name('orders.prepare');
            Route::post('siparisler/{storeOrder}/yola-cikar', [Admin\OrderController::class, 'ship'])->name('orders.ship');
            Route::post('siparisler/{storeOrder}/teslim', [Admin\OrderController::class, 'deliver'])->name('orders.deliver');
            Route::post('siparisler/{storeOrder}/teslim-alinmadi', [Admin\OrderController::class, 'notDelivered'])->name('orders.not-delivered');
            Route::post('siparisler/{storeOrder}/iptal', [Admin\OrderController::class, 'cancel'])->name('orders.cancel');
            Route::delete('kapida-odeme-engeli/{storeCodBlock}', [Admin\OrderController::class, 'unblock'])->name('cod-blocks.destroy');

            Route::get('ayarlar/odeme-teslimat', [Admin\PaymentSettingsController::class, 'edit'])->name('payments.edit');
            Route::put('ayarlar/odeme-teslimat', [Admin\PaymentSettingsController::class, 'update'])->name('payments.update');
            Route::delete('ayarlar/odeme-teslimat/anahtar/{key}', [Admin\PaymentSettingsController::class, 'removeSecret'])->name('payments.secret.destroy');
            Route::get('ayarlar/yasal-metinler', [Admin\LegalController::class, 'index'])->name('legal.index');
            Route::get('ayarlar/yasal-metinler/{key}', [Admin\LegalController::class, 'edit'])->name('legal.edit');
            Route::put('ayarlar/yasal-metinler/{key}', [Admin\LegalController::class, 'update'])->name('legal.update');

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
    // Kapalıyken de çalışanlar: arama motoru dosyaları ve önizleme
    Route::get('robots.txt', [Front\SeoController::class, 'robots'])->name('robots');
    Route::get('sitemap.xml', [Front\SeoController::class, 'sitemap'])->name('sitemap');
    Route::get('onizleme', [Front\PreviewController::class, 'start'])->middleware('signed')->name('preview');
    Route::get('onizleme/kapat', [Front\PreviewController::class, 'end'])->name('preview.end');
    // iyzico geri dönüşü: mağaza kapalıyken (önizlemede) de sonuçlanabilmeli
    Route::post('odeme/iyzico/donus', [Front\CheckoutController::class, 'callback'])->middleware('throttle:60,1')->name('payment.callback');

    // Mağaza kapalıyken ziyaretçi "yakında" sayfasını görür
    Route::middleware('store.open')->group(function () {
        Route::get('/', Front\HomeController::class)->name('home');
        Route::get('urunler', [Front\ListingController::class, 'index'])->name('products');
        Route::get('kategori/{slug}', [Front\ListingController::class, 'category'])->name('category');
        Route::get('marka/{slug}', [Front\ListingController::class, 'brand'])->name('brand');
        Route::get('markalar', [Front\PageController::class, 'brands'])->name('brands');
        Route::get('ara', [Front\ListingController::class, 'search'])->middleware('throttle:60,1')->name('search');
        Route::get('urun/{slug}', [Front\ProductController::class, 'show'])->name('product');
        Route::get('sayfa/{slug}', [Front\PageController::class, 'show'])->name('page');

        // Müşteri girişi ve üyelik (M5)
        Route::middleware('guest:customer')->group(function () {
            Route::get('giris', [Front\AuthController::class, 'showLogin'])->name('login');
            Route::post('giris', [Front\AuthController::class, 'login'])->middleware('throttle:20,1')->name('login.store');
            Route::get('uye-ol', [Front\AuthController::class, 'showRegister'])->name('register');
            Route::post('uye-ol', [Front\AuthController::class, 'register'])->middleware('throttle:10,60')->name('register.store');
            Route::get('parolami-unuttum', [Front\AuthController::class, 'showForgot'])->name('password.request');
            Route::post('parolami-unuttum', [Front\AuthController::class, 'sendReset'])->name('password.email');
            Route::get('parola-sifirla/{token}', [Front\AuthController::class, 'showReset'])->name('password.reset');
            Route::post('parola-sifirla', [Front\AuthController::class, 'reset'])->name('password.update');
        });

        Route::middleware(['auth:customer', 'auth.session'])->group(function () {
            Route::post('cikis', [Front\AuthController::class, 'logout'])->name('logout');
            Route::get('hesabim', [Front\AccountController::class, 'show'])->name('account');
            Route::put('hesabim', [Front\AccountController::class, 'updateProfile'])->name('account.profile');
            Route::put('hesabim/parola', [Front\AccountController::class, 'updatePassword'])->name('account.password');
            Route::put('hesabim/e-posta', [Front\AccountController::class, 'updateEmail'])->middleware('throttle:5,10')->name('account.email');

            Route::get('hesabim/adres/yeni', [Front\AddressController::class, 'create'])->name('addresses.create');
            Route::post('hesabim/adres', [Front\AddressController::class, 'store'])->name('addresses.store');
            Route::get('hesabim/adres/{address}', [Front\AddressController::class, 'edit'])->whereNumber('address')->name('addresses.edit');
            Route::put('hesabim/adres/{address}', [Front\AddressController::class, 'update'])->whereNumber('address')->name('addresses.update');
            Route::put('hesabim/adres/{address}/varsayilan', [Front\AddressController::class, 'makeDefault'])->whereNumber('address')->name('addresses.default');
            Route::delete('hesabim/adres/{address}', [Front\AddressController::class, 'destroy'])->whereNumber('address')->name('addresses.destroy');
        });
        Route::get('ilceler/{province}', [Front\AddressController::class, 'districts'])->whereNumber('province')->name('districts');

        // Ödeme ve siparişler (M6)
        Route::get('odeme', [Front\CheckoutController::class, 'show'])->name('checkout');
        Route::post('odeme', [Front\CheckoutController::class, 'store'])->middleware('throttle:20,10')->name('checkout.store');
        Route::get('odeme/dogrulama', [Front\CheckoutController::class, 'verifyForm'])->name('checkout.verify');
        Route::post('odeme/dogrulama', [Front\CheckoutController::class, 'verify'])->middleware('throttle:20,10')->name('checkout.verify.store');
        Route::post('odeme/dogrulama/yeniden', [Front\CheckoutController::class, 'resend'])->middleware('throttle:5,10')->name('checkout.verify.resend');
        Route::get('yasal/{key}', [Front\CheckoutController::class, 'legal'])->name('legal');
        Route::get('siparis/{orderNo}/{token}', [Front\OrderController::class, 'showByToken'])->middleware('throttle:60,1')->name('order');
        Route::middleware(['auth:customer', 'auth.session'])->group(function () {
            Route::get('hesabim/siparisler', [Front\OrderController::class, 'index'])->name('orders');
            Route::get('hesabim/siparisler/{orderNo}', [Front\OrderController::class, 'showOwn'])->name('orders.show');
        });

        // Sepet (M5)
        Route::get('sepet', [Front\CartController::class, 'show'])->name('cart');
        Route::post('sepet', [Front\CartController::class, 'add'])->middleware('throttle:60,1')->name('cart.add');
        Route::put('sepet/{item}', [Front\CartController::class, 'update'])->whereNumber('item')->name('cart.update');
        Route::delete('sepet/{item}', [Front\CartController::class, 'remove'])->whereNumber('item')->name('cart.remove');

        Route::get('gorsel/urun/{storeImage}', [Front\MediaController::class, 'product'])->name('media.product');
        Route::get('gorsel/banner/{storeBanner}', [Front\MediaController::class, 'banner'])->name('media.banner');
        Route::get('gorsel/marka/{storeBrand}', [Front\MediaController::class, 'brand'])->name('media.brand');
    });
});
