<?php

use App\Http\Controllers\Auth\AccountController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Business;
use App\Http\Controllers\Dealer;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Platform;
use App\Http\Controllers\PublicSite;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

/*
| Üç ayrı giriş alanı: /platform, /toptanci, /bayi.
| Her grup yalnızca kendi guard'ını kullanır; oturum çerezleri URL önekine bağlıdır
| (bkz. App\Http\Middleware\UseAreaSession).
*/

// Ana adres doğrudan bayi girişidir; toptancı (/toptanci) ve platform (/platform) girişleri yalnızca kendi bağlantılarıyla açılır.
Route::redirect('/', '/bayi/login')->name('home');

// Herkese açık: toptancıya özel bayilik başvurusu ve işletme logosu
Route::get('basvuru', [PublicSite\DealerApplicationFormController::class, 'index'])->name('public.apply');
Route::get('basvuru/{slug}', [PublicSite\DealerApplicationFormController::class, 'show'])->name('public.application');
Route::post('basvuru/{slug}', [PublicSite\DealerApplicationFormController::class, 'store'])->middleware('throttle:5,60')->name('public.application.store');
Route::get('basvuru/{slug}/tesekkurler', [PublicSite\DealerApplicationFormController::class, 'thanks'])->name('public.application.thanks');
Route::get('logo/{slug}', [PublicSite\DealerApplicationFormController::class, 'logo'])->name('public.logo');

/**
 * Her alanın ortak kimlik rotaları: giriş, çıkış, parola sıfırlama, hesap. Aktivasyon bağlantısı yoktur;
 * hesaplar açılırken şifre belirlenir.
 */
$authRoutes = function (string $name) {
    Route::middleware("guest:{$name}")->group(function () {
        Route::get('login', [LoginController::class, 'show'])->name('login');
        Route::post('login', [LoginController::class, 'store'])->middleware('throttle:20,1')->name('login.store');
        Route::get('parolami-unuttum', [PasswordResetController::class, 'request'])->name('password.request');
        Route::post('parolami-unuttum', [PasswordResetController::class, 'email'])->name('password.email');
        Route::get('parola-sifirla/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
        Route::post('parola-sifirla', [PasswordResetController::class, 'update'])->name('password.update');
    });

    Route::post('cikis', [LoginController::class, 'destroy'])->middleware("auth:{$name}")->name('logout');
};

$accountRoutes = function () {
    Route::get('hesabim', [AccountController::class, 'show'])->name('account');
    Route::put('hesabim', [AccountController::class, 'updateProfile'])->name('account.profile');
    Route::put('hesabim/parola', [AccountController::class, 'updatePassword'])->name('account.password');
    Route::post('hesabim/e-posta', [AccountController::class, 'updateEmail'])->middleware('throttle:5,10')->name('account.email');
};

/* ---------------- Platform yönetimi ---------------- */
Route::prefix('platform')->name('platform.')->middleware('platform.ip')->group(function () use ($authRoutes, $accountRoutes) {
    $authRoutes('platform');

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
            Route::put('isletmeler/{business}/yetkili', [Platform\BusinessController::class, 'updateUser'])->name('businesses.user');
            Route::put('isletmeler/{business}/e-posta', [Platform\BusinessController::class, 'updateEmail'])->name('businesses.email');
            Route::post('isletmeler/{business}/e-posta/test', [Platform\BusinessController::class, 'testEmail'])->middleware('throttle:5,10')->name('businesses.email.test');

            Route::get('basvurular', [Platform\ApplicationController::class, 'index'])->name('applications.index');
            Route::get('basvurular/{application}', [Platform\ApplicationController::class, 'show'])->whereNumber('application')->name('applications.show');
            Route::post('basvurular/{application}/onayla', [Platform\ApplicationController::class, 'approve'])->whereNumber('application')->name('applications.approve');
            Route::post('basvurular/{application}/reddet', [Platform\ApplicationController::class, 'reject'])->whereNumber('application')->name('applications.reject');
            Route::get('basvurular/{application}/belge/{attachment}', [Platform\ApplicationController::class, 'attachment'])->whereNumber(['application', 'attachment'])->name('applications.attachment');

            Route::get('bayi-hesaplari', [Platform\DealerAccountController::class, 'index'])->name('dealer-accounts.index');
            Route::get('bayi-hesaplari/yeni', [Platform\DealerAccountController::class, 'create'])->name('dealer-accounts.create');
            Route::post('bayi-hesaplari', [Platform\DealerAccountController::class, 'store'])->name('dealer-accounts.store');
            Route::get('bayi-hesaplari/{dealerUser}', [Platform\DealerAccountController::class, 'show'])->name('dealer-accounts.show');
            Route::put('bayi-hesaplari/{dealerUser}', [Platform\DealerAccountController::class, 'update'])->name('dealer-accounts.update');
            Route::put('bayi-hesaplari/{dealerUser}/durum', [Platform\DealerAccountController::class, 'updateStatus'])->name('dealer-accounts.status');

            Route::get('denetim-kayitlari', Platform\AuditLogController::class)->name('audit-logs');
            Route::get('e-posta-durumu', Platform\EmailStatusController::class)->name('email-status');

            $accountRoutes();
        });
    });
});

/* ---------------- Toptancı paneli ---------------- */
Route::prefix('toptanci')->name('business.')->group(function () use ($authRoutes, $accountRoutes) {
    $authRoutes('business');

    Route::middleware(['auth:business', 'auth.session', 'business.context'])->group(function () use ($accountRoutes) {
        Route::get('/', Business\DashboardController::class)->name('dashboard');
        Route::get('ayarlar', [Business\SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('ayarlar', [Business\SettingsController::class, 'update'])->name('settings.update');
        Route::post('ayarlar/logo', [Business\SettingsController::class, 'updateLogo'])->name('settings.logo');

        // Siparişler
        Route::get('siparisler', [Business\OrderController::class, 'index'])->name('orders.index');
        Route::get('siparisler/{order}', [Business\OrderController::class, 'show'])->name('orders.show');
        Route::post('siparisler/{order}/incele', [Business\OrderController::class, 'review'])->name('orders.review');
        Route::post('siparisler/{order}/onayla', [Business\OrderController::class, 'approve'])->name('orders.approve');
        Route::post('siparisler/{order}/reddet', [Business\OrderController::class, 'reject'])->name('orders.reject');
        Route::post('siparisler/{order}/hazirlaniyor', [Business\OrderController::class, 'prepare'])->name('orders.prepare');
        Route::post('siparisler/{order}/tamamla', [Business\OrderController::class, 'complete'])->name('orders.complete');
        Route::post('siparisler/{order}/iptal', [Business\OrderController::class, 'cancel'])->name('orders.cancel');
        Route::post('siparisler/{order}/iptal-talebi', [Business\OrderController::class, 'resolveCancel'])->name('orders.resolve-cancel');
        Route::post('siparisler/{order}/iade', [Business\OrderReturnController::class, 'store'])->name('orders.returns.store');

        // İletişim
        Route::middleware('module:messaging')->group(function () {
            Route::get('mesajlar', [Business\MessageController::class, 'index'])->name('messages.index');
            Route::get('mesajlar/yeni', [Business\MessageController::class, 'create'])->name('messages.create');
            Route::post('mesajlar', [Business\MessageController::class, 'store'])->middleware('throttle:30,1')->name('messages.store');
            Route::get('mesajlar/{conversation}', [Business\MessageController::class, 'show'])->name('messages.show');
            Route::post('mesajlar/{conversation}', [Business\MessageController::class, 'reply'])->middleware('throttle:30,1')->name('messages.reply');
            Route::get('mesajlar/{conversation}/ek/{attachment}', [Business\MessageController::class, 'attachment'])->name('messages.attachment');
        });
        // Vitrin: bayi ana sayfası slaytları ve ürün vitrinleri
        Route::get('vitrin', [Business\StorefrontController::class, 'index'])->name('storefront.index');
        Route::post('vitrin/slaytlar', [Business\StorefrontController::class, 'storeBanner'])->middleware('throttle:30,1')->name('storefront.banners.store');
        Route::put('vitrin/slaytlar/{banner}', [Business\StorefrontController::class, 'updateBanner'])->middleware('throttle:30,1')->name('storefront.banners.update');
        Route::delete('vitrin/slaytlar/{banner}', [Business\StorefrontController::class, 'destroyBanner'])->name('storefront.banners.destroy');
        Route::post('vitrin/slaytlar/{banner}/tasi', [Business\StorefrontController::class, 'moveBanner'])->name('storefront.banners.move');
        Route::get('vitrin/slaytlar/{banner}/medya', [Business\StorefrontController::class, 'media'])->name('storefront.banners.media');
        Route::post('vitrin/vitrinler', [Business\StorefrontController::class, 'storeShowcase'])->name('storefront.showcases.store');
        Route::put('vitrin/vitrinler/{showcase}', [Business\StorefrontController::class, 'updateShowcase'])->name('storefront.showcases.update');
        Route::delete('vitrin/vitrinler/{showcase}', [Business\StorefrontController::class, 'destroyShowcase'])->name('storefront.showcases.destroy');
        Route::post('vitrin/vitrinler/{showcase}/tasi', [Business\StorefrontController::class, 'moveShowcase'])->name('storefront.showcases.move');
        Route::post('vitrin/vitrinler/{showcase}/urunler', [Business\StorefrontController::class, 'addProduct'])->name('storefront.showcases.products.store');
        Route::delete('vitrin/vitrinler/{showcase}/urunler/{product}', [Business\StorefrontController::class, 'removeProduct'])->name('storefront.showcases.products.destroy');
        Route::put('urunler/{product}/vitrinler', [Business\StorefrontController::class, 'productShowcases'])->name('products.showcases');

        Route::get('duyurular', [Business\AnnouncementController::class, 'index'])->name('announcements.index');
        Route::post('duyurular', [Business\AnnouncementController::class, 'store'])->middleware('throttle:20,1')->name('announcements.store');
        Route::get('duyurular/{announcement}/ek/{attachment}', [Business\AnnouncementController::class, 'attachment'])->name('announcements.attachment');
        Route::get('bildirimler', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('bildirimler/{notification}', [NotificationController::class, 'open'])->name('notifications.open');
        Route::post('bildirimler/okundu', [NotificationController::class, 'readAll'])->name('notifications.read-all');
        Route::get('e-posta', [Business\EmailSettingsController::class, 'show'])->name('email.settings');
        Route::put('e-posta', [Business\EmailSettingsController::class, 'update'])->name('email.update');
        Route::post('e-posta/test', [Business\EmailSettingsController::class, 'test'])->name('email.test');
        Route::put('e-posta/sablon', [Business\EmailSettingsController::class, 'updateTemplate'])->name('email.template');
        Route::get('e-posta/kayitlar', [Business\EmailSettingsController::class, 'logs'])->name('email.logs');
        Route::post('e-posta/kayitlar/{log}/tekrar', [Business\EmailSettingsController::class, 'retry'])->name('email.retry');

        // Raporlama merkezi
        Route::middleware('module:reports')->group(function () {
            Route::get('raporlar', [ReportController::class, 'index'])->name('reports.index');
            Route::get('raporlar/{key}', [ReportController::class, 'show'])->name('reports.show');
            Route::get('raporlar/{key}/indir/{format}', [ReportController::class, 'export'])->middleware('throttle:20,1')->name('reports.export');
        });

        // Cari hesaplar
        Route::get('cari-hesaplar', [Business\CurrentAccountController::class, 'index'])->name('accounts.index');
        Route::get('cari-hesaplar/{account}', [Business\CurrentAccountController::class, 'show'])->name('accounts.show');
        Route::post('cari-hesaplar/{account}/tahsilat', [Business\CurrentAccountController::class, 'storeCollection'])->name('accounts.collections.store');
        Route::post('cari-hesaplar/{account}/acilis', [Business\CurrentAccountController::class, 'storeOpening'])->name('accounts.opening');
        Route::post('cari-hesaplar/{account}/duzeltme', [Business\CurrentAccountController::class, 'storeAdjustment'])->name('accounts.adjust');
        Route::get('tahsilatlar', [Business\CollectionController::class, 'index'])->name('collections.index');
        Route::post('tahsilatlar/{collection}/iptal', [Business\CollectionController::class, 'cancel'])->name('collections.cancel');

        Route::middleware('module:product_requests')->group(function () {
            Route::get('urun-talepleri', [Business\ProductRequestController::class, 'index'])->name('product-requests.index');
            Route::put('urun-talepleri/{productRequest}', [Business\ProductRequestController::class, 'respond'])->name('product-requests.respond');
        });

        // Bayilik başvuruları
        Route::get('basvurular', [Business\DealerApplicationController::class, 'index'])->name('applications.index');
        Route::get('basvurular/{application}', [Business\DealerApplicationController::class, 'show'])->name('applications.show');
        Route::post('basvurular/{application}/incele', [Business\DealerApplicationController::class, 'review'])->name('applications.review');
        Route::post('basvurular/{application}/bilgi-iste', [Business\DealerApplicationController::class, 'requestInfo'])->name('applications.request-info');
        Route::post('basvurular/{application}/onayla', [Business\DealerApplicationController::class, 'approve'])->name('applications.approve');
        Route::post('basvurular/{application}/reddet', [Business\DealerApplicationController::class, 'reject'])->name('applications.reject');
        Route::post('basvurular/{application}/iptal', [Business\DealerApplicationController::class, 'cancel'])->name('applications.cancel');
        Route::get('basvurular/{application}/belge/{attachment}', [Business\DealerApplicationController::class, 'attachment'])->name('applications.attachment');

        // Bayiler
        Route::get('bayiler', [Business\DealerController::class, 'index'])->name('dealers.index');
        Route::get('bayiler/yeni', [Business\DealerController::class, 'create'])->name('dealers.create');
        Route::post('bayiler', [Business\DealerController::class, 'store'])->name('dealers.store');
        Route::get('bayiler/{dealer}', [Business\DealerController::class, 'show'])->name('dealers.show');
        Route::put('bayiler/{dealer}', [Business\DealerController::class, 'update'])->name('dealers.update');
        Route::put('bayiler/{dealer}/durum', [Business\DealerController::class, 'updateStatus'])->name('dealers.status');
        Route::put('bayiler/{dealer}/giris-eposta', [Business\DealerController::class, 'correctEmail'])->middleware('throttle:10,1')->name('dealers.email');
        Route::put('bayiler/{dealer}/sifre', [Business\DealerController::class, 'setPassword'])->middleware('throttle:10,1')->name('dealers.password');
        Route::post('bayiler/{dealer}/adresler', [Business\DealerController::class, 'storeAddress'])->name('dealers.addresses.store');
        Route::put('bayiler/{dealer}/adresler/{address}', [Business\DealerController::class, 'updateAddress'])->name('dealers.addresses.update');
        Route::delete('bayiler/{dealer}/adresler/{address}', [Business\DealerController::class, 'archiveAddress'])->name('dealers.addresses.destroy');
        Route::post('bayiler/{dealer}/fiyatlar', [Business\DealerController::class, 'storePrice'])->name('dealers.prices.store');
        Route::delete('bayiler/{dealer}/fiyatlar/{price}', [Business\DealerController::class, 'destroyPrice'])->name('dealers.prices.destroy');
        Route::get('bayi-gruplari', [Business\DealerGroupController::class, 'index'])->name('dealer-groups.index');
        Route::post('bayi-gruplari', [Business\DealerGroupController::class, 'store'])->name('dealer-groups.store');
        Route::put('bayi-gruplari/{group}', [Business\DealerGroupController::class, 'update'])->name('dealer-groups.update');
        Route::delete('bayi-gruplari/{group}', [Business\DealerGroupController::class, 'destroy'])->name('dealer-groups.destroy');

        // Fiyatlandırma
        Route::get('fiyatlandirma', [Business\PricingController::class, 'index'])->name('pricing');
        Route::post('fiyat-listeleri', [Business\PricingController::class, 'storeList'])->name('price-lists.store');
        Route::get('fiyat-listeleri/{list}', [Business\PricingController::class, 'showList'])->name('price-lists.show');
        Route::put('fiyat-listeleri/{list}', [Business\PricingController::class, 'updateList'])->name('price-lists.update');
        Route::post('fiyat-listeleri/{list}/urunler', [Business\PricingController::class, 'storeItem'])->name('price-lists.items.store');
        Route::delete('fiyat-listeleri/{list}/urunler/{item}', [Business\PricingController::class, 'destroyItem'])->name('price-lists.items.destroy');
        Route::post('fiyat-listeleri/{list}/doldur', [Business\PricingController::class, 'fillList'])->name('price-lists.fill');
        Route::post('indirimler', [Business\PricingController::class, 'storeRule'])->name('discounts.store');
        Route::put('indirimler/{rule}/durum', [Business\PricingController::class, 'toggleRule'])->name('discounts.toggle');

        // Katalog: ürünler
        Route::get('urunler', [Business\ProductController::class, 'index'])->name('products.index');
        Route::get('urunler/yeni', [Business\ProductController::class, 'create'])->name('products.create');
        Route::post('urunler', [Business\ProductController::class, 'store'])->name('products.store');
        Route::post('urunler/toplu', [Business\ProductController::class, 'bulk'])->name('products.bulk');
        Route::get('urunler/toplu-fiyat', [Business\ProductController::class, 'priceForm'])->name('products.price');
        Route::post('urunler/toplu-fiyat', [Business\ProductController::class, 'priceUpdate'])->name('products.price.update');
        Route::middleware('module:import')->group(function () {
            Route::get('urunler/ice-aktar', [Business\ProductImportController::class, 'index'])->name('products.import');
            Route::post('urunler/ice-aktar', [Business\ProductImportController::class, 'store'])->middleware('throttle:10,1')->name('products.import.store');
            Route::get('urunler/ice-aktar/sablon', [Business\ProductImportController::class, 'template'])->name('products.import.template');
            Route::get('urunler/ice-aktar/{job}', [Business\ProductImportController::class, 'show'])->name('products.import.show');
        });
        Route::get('urunler/{product}', [Business\ProductController::class, 'show'])->name('products.show');
        Route::get('urunler/{product}/duzenle', [Business\ProductController::class, 'edit'])->name('products.edit');
        Route::put('urunler/{product}', [Business\ProductController::class, 'update'])->name('products.update');
        Route::post('urunler/{product}/kopyala', [Business\ProductController::class, 'duplicate'])->name('products.duplicate');
        Route::post('urunler/{product}/arsivle', [Business\ProductController::class, 'archive'])->name('products.archive');
        Route::post('urunler/{product}/arsivden-cikar', [Business\ProductController::class, 'restore'])->name('products.restore');
        Route::post('urunler/{product}/gorseller', [Business\ProductImageController::class, 'store'])->name('products.images.store');
        Route::put('urunler/{product}/gorseller/{image}/ana', [Business\ProductImageController::class, 'primary'])->name('products.images.primary');
        Route::delete('urunler/{product}/gorseller/{image}', [Business\ProductImageController::class, 'destroy'])->name('products.images.destroy');
        Route::get('gorsel/{image}', [Business\ProductImageController::class, 'show'])->name('images.show');

        // Katalog: kategori, marka, varyant grupları
        Route::get('kategori-ve-markalar', [Business\CatalogStructureController::class, 'index'])->name('catalog.structure');
        Route::post('kategoriler', [Business\CatalogStructureController::class, 'storeCategory'])->name('categories.store');
        Route::put('kategoriler/{category}', [Business\CatalogStructureController::class, 'updateCategory'])->name('categories.update');
        Route::delete('kategoriler/{category}', [Business\CatalogStructureController::class, 'destroyCategory'])->name('categories.destroy');
        Route::post('markalar', [Business\CatalogStructureController::class, 'storeBrand'])->name('brands.store');
        Route::put('markalar/{brand}', [Business\CatalogStructureController::class, 'updateBrand'])->name('brands.update');
        Route::delete('markalar/{brand}', [Business\CatalogStructureController::class, 'destroyBrand'])->name('brands.destroy');
        Route::post('varyant-gruplari', [Business\CatalogStructureController::class, 'storeVariantGroup'])->name('variant-groups.store');
        Route::put('varyant-gruplari/{variantGroup}', [Business\CatalogStructureController::class, 'updateVariantGroup'])->name('variant-groups.update');

        // Ayarlar: KDV ve birimler
        Route::get('kdv-ve-birimler', [Business\CatalogSettingsController::class, 'index'])->name('catalog.settings');
        Route::post('kdv-oranlari', [Business\CatalogSettingsController::class, 'storeTaxRate'])->name('tax-rates.store');
        Route::put('kdv-oranlari/{taxRate}/durum', [Business\CatalogSettingsController::class, 'toggleTaxRate'])->name('tax-rates.toggle');
        Route::post('birimler', [Business\CatalogSettingsController::class, 'storeUnit'])->name('units.store');
        Route::put('birimler/{unit}', [Business\CatalogSettingsController::class, 'updateUnit'])->name('units.update');

        // Stok ve depo
        Route::get('stok', [Business\StockController::class, 'index'])->name('stock.index');
        Route::get('stok/hareket', [Business\StockController::class, 'create'])->name('stock.create');
        Route::post('stok/hareket', [Business\StockController::class, 'store'])->name('stock.store');
        Route::get('stok/transfer', [Business\StockController::class, 'transferForm'])->name('stock.transfer');
        Route::post('stok/transfer', [Business\StockController::class, 'transfer'])->name('stock.transfer.store');
        Route::get('stok/urun-ara', [Business\StockController::class, 'lookup'])->name('stock.lookup');
        Route::get('stok-hareketleri', [Business\StockMovementController::class, 'index'])->name('stock-movements.index');
        Route::get('depolar', [Business\WarehouseController::class, 'index'])->name('warehouses.index');
        Route::post('depolar', [Business\WarehouseController::class, 'store'])->name('warehouses.store');
        Route::put('depolar/{warehouse}', [Business\WarehouseController::class, 'update'])->name('warehouses.update');
        Route::put('depolar/{warehouse}/varsayilan', [Business\WarehouseController::class, 'makeDefault'])->name('warehouses.default');
        Route::get('stok-sayimlari', [Business\StockCountController::class, 'index'])->name('stock-counts.index');
        Route::post('stok-sayimlari', [Business\StockCountController::class, 'store'])->name('stock-counts.store');
        Route::get('stok-sayimlari/{count}', [Business\StockCountController::class, 'show'])->name('stock-counts.show');
        Route::post('stok-sayimlari/{count}/satir', [Business\StockCountController::class, 'addLine'])->name('stock-counts.lines.store');
        Route::delete('stok-sayimlari/{count}/satir/{line}', [Business\StockCountController::class, 'removeLine'])->name('stock-counts.lines.destroy');
        Route::post('stok-sayimlari/{count}/uygula', [Business\StockCountController::class, 'apply'])->name('stock-counts.apply');
        Route::post('stok-sayimlari/{count}/iptal', [Business\StockCountController::class, 'cancel'])->name('stock-counts.cancel');

        $accountRoutes();
    });
});

/* ---------------- Bayi paneli ---------------- */
Route::prefix('bayi')->name('dealer.')->group(function () use ($authRoutes, $accountRoutes) {
    $authRoutes('dealer');

    Route::middleware(['auth:dealer', 'auth.session', 'dealer.context'])->group(function () use ($accountRoutes) {
        Route::get('toptanci-sec', [Dealer\SelectBusinessController::class, 'show'])->name('select-business');
        Route::post('toptanci-sec', [Dealer\SelectBusinessController::class, 'store'])->name('select-business.store');

        Route::get('/', Dealer\HomeController::class)->name('home');
        Route::get('vitrin/medya/{banner}', [Dealer\HomeController::class, 'media'])->name('storefront.media');
        Route::get('hesap-ozeti', Dealer\DashboardController::class)->name('dashboard');

        Route::get('katalog', [Dealer\CatalogController::class, 'index'])->name('catalog');
        Route::get('katalog/{product}', [Dealer\CatalogController::class, 'show'])->name('catalog.show');
        Route::get('gorsel/{image}', [Dealer\CatalogController::class, 'image'])->name('images.show');
        Route::get('sepet', [Dealer\CartController::class, 'index'])->name('cart');
        Route::post('sepet', [Dealer\CartController::class, 'add'])->name('cart.add');
        Route::put('sepet/{item}', [Dealer\CartController::class, 'update'])->name('cart.update');
        Route::delete('sepet/{item}', [Dealer\CartController::class, 'remove'])->name('cart.remove');
        Route::post('sepet/siparis', [Dealer\CartController::class, 'submit'])->middleware('throttle:20,1')->name('cart.submit');
        Route::get('cari-hesabim', Dealer\AccountStatementController::class)->name('account.statement');
        Route::middleware('module:reports')->group(function () {
            Route::get('raporlar', [ReportController::class, 'index'])->name('reports.index');
            Route::get('raporlar/{key}', [ReportController::class, 'show'])->name('reports.show');
            Route::get('raporlar/{key}/indir/{format}', [ReportController::class, 'export'])->middleware('throttle:20,1')->name('reports.export');
        });
        Route::middleware('module:messaging')->group(function () {
            Route::get('mesajlar', [Dealer\MessageController::class, 'index'])->name('messages.index');
            Route::get('mesajlar/yeni', [Dealer\MessageController::class, 'create'])->name('messages.create');
            Route::post('mesajlar', [Dealer\MessageController::class, 'store'])->middleware('throttle:30,1')->name('messages.store');
            Route::get('mesajlar/{conversation}', [Dealer\MessageController::class, 'show'])->name('messages.show');
            Route::post('mesajlar/{conversation}', [Dealer\MessageController::class, 'reply'])->middleware('throttle:30,1')->name('messages.reply');
            Route::get('mesajlar/{conversation}/ek/{attachment}', [Dealer\MessageController::class, 'attachment'])->name('messages.attachment');
        });
        Route::get('bildirimler', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('bildirimler/{notification}', [NotificationController::class, 'open'])->name('notifications.open');
        Route::post('bildirimler/okundu', [NotificationController::class, 'readAll'])->name('notifications.read-all');
        Route::get('duyurular', [Dealer\AnnouncementController::class, 'index'])->name('announcements.index');
        Route::get('duyurular/{announcement}/ek/{attachment}', [Dealer\AnnouncementController::class, 'attachment'])->name('announcements.attachment');
        Route::get('siparislerim', [Dealer\OrderController::class, 'index'])->name('orders.index');
        Route::get('siparislerim/{order}', [Dealer\OrderController::class, 'show'])->name('orders.show');
        Route::post('siparislerim/{order}/iptal', [Dealer\OrderController::class, 'cancel'])->name('orders.cancel');
        Route::middleware('module:product_requests')->group(function () {
            Route::get('taleplerim', [Dealer\ProductRequestController::class, 'index'])->name('requests.index');
            Route::post('taleplerim', [Dealer\ProductRequestController::class, 'store'])->name('requests.store');
        });

        Route::get('sevk-adresleri', [Dealer\AddressController::class, 'index'])->name('addresses.index');
        Route::post('sevk-adresleri', [Dealer\AddressController::class, 'store'])->name('addresses.store');
        Route::put('sevk-adresleri/{address}', [Dealer\AddressController::class, 'update'])->name('addresses.update');
        Route::put('sevk-adresleri/{address}/varsayilan', [Dealer\AddressController::class, 'makeDefault'])->name('addresses.default');
        Route::delete('sevk-adresleri/{address}', [Dealer\AddressController::class, 'destroy'])->name('addresses.destroy');
        Route::get('isletme-profili', [Dealer\ProfileController::class, 'show'])->name('profile');
        Route::put('isletme-profili', [Dealer\ProfileController::class, 'update'])->name('profile.update');

        $accountRoutes();
    });
});
