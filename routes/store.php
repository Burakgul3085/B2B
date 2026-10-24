<?php

use App\Domain\Store\Content\HelpPages;
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
        Route::post('iki-adimli-dogrulama', [Admin\TwoFactorController::class, 'verify'])->middleware('throttle:10,1,two-factor.verify')->name('two-factor.verify');

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
                Route::post('urunler/ice-aktar', [Admin\ProductImportController::class, 'store'])->middleware('throttle:10,1,products.import.store')->name('products.import.store');
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
                Route::get('kategoriler/{storeCategory}/gorsel', [Admin\CategoryController::class, 'image'])->name('categories.image');
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

                // Sabit sayfalar: her sayfanın kendi formu var; serbest sayfa oluşturma yok (App\Domain\Store\Content\SitePages).
                Route::get('hakkimizda', [Admin\SitePageController::class, 'about'])->name('site-pages.about');
                Route::put('hakkimizda', [Admin\SitePageController::class, 'updateAbout'])->name('site-pages.about.update');
                Route::get('iletisim', [Admin\SitePageController::class, 'contact'])->name('site-pages.contact');
                Route::put('iletisim', [Admin\SitePageController::class, 'updateContact'])->name('site-pages.contact.update');
                Route::get('sayfa-onizleme/{key}', [Admin\SitePageController::class, 'preview'])->whereIn('key', ['about', 'contact'])->name('site-pages.preview');
                Route::get('sayfa-gorseli/{file}', [Admin\SitePageController::class, 'image'])->name('site-pages.image');
                Route::get('alt-bilgi', [Admin\FooterController::class, 'edit'])->name('footer.edit');
                Route::get('ana-sayfa', [Admin\CollectionController::class, 'home'])->name('home-layout.edit');
                Route::put('ana-sayfa', [Admin\CollectionController::class, 'updateHome'])->name('home-layout.update');
                Route::get('seckiler', [Admin\CollectionController::class, 'index'])->name('collections.index');
                Route::get('seckiler/yeni', [Admin\CollectionController::class, 'create'])->name('collections.create');
                Route::post('seckiler', [Admin\CollectionController::class, 'store'])->name('collections.store');
                Route::get('seckiler/{storeCollection}', [Admin\CollectionController::class, 'edit'])->name('collections.edit');
                Route::put('seckiler/{storeCollection}', [Admin\CollectionController::class, 'update'])->name('collections.update');
                Route::delete('seckiler/{storeCollection}', [Admin\CollectionController::class, 'destroy'])->name('collections.destroy');
                Route::get('seckiler/{storeCollection}/gorsel', [Admin\CollectionController::class, 'image'])->name('collections.image');
                Route::get('yardim-sayfalari', [Admin\HelpPageController::class, 'index'])->name('help-pages.index');
                Route::get('yardim-sayfalari/{key}', [Admin\HelpPageController::class, 'edit'])->whereIn('key', array_keys(HelpPages::PAGES))->name('help-pages.edit');
                Route::put('yardim-sayfalari/{key}', [Admin\HelpPageController::class, 'update'])->whereIn('key', array_keys(HelpPages::PAGES))->name('help-pages.update');
                Route::get('blog', [Admin\BlogController::class, 'index'])->name('blog.index');
                Route::put('blog/kategoriler', [Admin\BlogController::class, 'categories'])->name('blog.categories');
                Route::get('blog/yeni', [Admin\BlogController::class, 'create'])->name('blog.create');
                Route::post('blog', [Admin\BlogController::class, 'store'])->name('blog.store');
                Route::get('blog/{storeBlogPost}', [Admin\BlogController::class, 'edit'])->name('blog.edit');
                Route::put('blog/{storeBlogPost}', [Admin\BlogController::class, 'update'])->name('blog.update');
                Route::delete('blog/{storeBlogPost}', [Admin\BlogController::class, 'destroy'])->name('blog.destroy');
                Route::get('blog/{storeBlogPost}/kapak', [Admin\BlogController::class, 'cover'])->name('blog.cover');
                Route::put('alt-bilgi', [Admin\FooterController::class, 'update'])->name('footer.update');

                Route::get('ayarlar', [Admin\SettingsController::class, 'edit'])->name('settings.edit');
                Route::put('ayarlar', [Admin\SettingsController::class, 'update'])->name('settings.update');
                Route::put('ayarlar/yayin', [Admin\SettingsController::class, 'open'])->name('settings.open');
                Route::post('ayarlar/deneme-eposta', [Admin\SettingsController::class, 'testMail'])->middleware('throttle:5,10,settings.test-mail')->name('settings.test-mail');
            });

            // Siparişler (M6)
            Route::get('siparisler', [Admin\OrderController::class, 'index'])->name('orders.index');
            Route::get('siparisler/{storeOrder}', [Admin\OrderController::class, 'show'])->name('orders.show');
            Route::post('siparisler/{storeOrder}/onayla', [Admin\OrderController::class, 'approve'])->name('orders.approve');
            Route::post('siparisler/{storeOrder}/havale-alindi', [Admin\OrderController::class, 'transferReceived'])->name('orders.transfer-received');
            Route::post('siparisler/{storeOrder}/hazirla', [Admin\OrderController::class, 'prepare'])->name('orders.prepare');
            Route::post('siparisler/{storeOrder}/yola-cikar', [Admin\OrderController::class, 'ship'])->name('orders.ship');
            Route::post('siparisler/{storeOrder}/teslim', [Admin\OrderController::class, 'deliver'])->name('orders.deliver');
            Route::post('siparisler/{storeOrder}/teslim-alinmadi', [Admin\OrderController::class, 'notDelivered'])->name('orders.not-delivered');
            Route::post('siparisler/{storeOrder}/iptal', [Admin\OrderController::class, 'cancel'])->name('orders.cancel');
            Route::post('siparisler/{storeOrder}/fatura', [Admin\OrderController::class, 'storeInvoice'])->name('orders.invoice.store');
            Route::post('siparisler/{storeOrder}/iade', [Admin\OrderController::class, 'storeReturn'])->name('orders.return');
            Route::post('siparisler/{storeOrder}/para-iadesi', [Admin\OrderController::class, 'refund'])->name('orders.refund');
            Route::post('siparisler/{storeOrder}/iade-faturasi', [Admin\OrderController::class, 'storeCreditNote'])->name('orders.credit-note.store');
            Route::get('siparisler/{storeOrder}/iade-faturasi/{note}', [Admin\OrderController::class, 'creditNote'])->whereNumber('note')->name('orders.credit-note');
            Route::get('siparisler/{storeOrder}/fatura', [Admin\OrderController::class, 'invoice'])->name('orders.invoice');
            Route::delete('kapida-odeme-engeli/{storeCodBlock}', [Admin\OrderController::class, 'unblock'])->name('cod-blocks.destroy');
            // İletişim formu mesajları
            Route::get('mesajlar', [Admin\ContactMessageController::class, 'index'])->name('messages.index');
            Route::get('mesajlar/{storeContactMessage}', [Admin\ContactMessageController::class, 'show'])->name('messages.show');
            Route::post('mesajlar/{storeContactMessage}/yanitlandi', [Admin\ContactMessageController::class, 'replied'])->name('messages.replied');
            Route::delete('mesajlar/{storeContactMessage}', [Admin\ContactMessageController::class, 'destroy'])->name('messages.destroy');
            Route::get('yorumlar', [Admin\ReviewController::class, 'index'])->name('reviews.index');
            Route::put('yorumlar/{storeReview}', [Admin\ReviewController::class, 'update'])->name('reviews.update');
            Route::delete('yorumlar/{storeReview}', [Admin\ReviewController::class, 'destroy'])->name('reviews.destroy');

            // Pazarlama: e-bülten aboneleri ve toplu e-posta
            Route::get('pazarlama/e-bulten', [Admin\NewsletterController::class, 'index'])->name('newsletter.index');
            Route::get('pazarlama/e-bulten/disa-aktar', [Admin\NewsletterController::class, 'export'])->name('newsletter.export');
            Route::put('pazarlama/e-bulten/ayarlar', [Admin\NewsletterController::class, 'settings'])->name('newsletter.settings');
            Route::delete('pazarlama/e-bulten/{storeNewsletterSubscriber}', [Admin\NewsletterController::class, 'destroy'])->name('newsletter.destroy');
            Route::get('pazarlama/toplu-eposta', [Admin\CampaignController::class, 'index'])->name('campaigns.index');
            Route::get('pazarlama/toplu-eposta/yeni', [Admin\CampaignController::class, 'create'])->name('campaigns.create');
            Route::post('pazarlama/toplu-eposta', [Admin\CampaignController::class, 'store'])->name('campaigns.store');
            Route::get('pazarlama/toplu-eposta/{storeNewsletterCampaign}', [Admin\CampaignController::class, 'edit'])->name('campaigns.edit');
            Route::put('pazarlama/toplu-eposta/{storeNewsletterCampaign}', [Admin\CampaignController::class, 'update'])->name('campaigns.update');
            Route::delete('pazarlama/toplu-eposta/{storeNewsletterCampaign}', [Admin\CampaignController::class, 'destroy'])->name('campaigns.destroy');
            Route::get('pazarlama/toplu-eposta/{storeNewsletterCampaign}/onizleme', [Admin\CampaignController::class, 'preview'])->name('campaigns.preview');
            Route::get('pazarlama/toplu-eposta/{storeNewsletterCampaign}/gorsel', [Admin\CampaignController::class, 'image'])->name('campaigns.image');
            Route::get('pazarlama/toplu-eposta/{storeNewsletterCampaign}/durum', [Admin\CampaignController::class, 'progress'])->name('campaigns.progress');
            Route::post('pazarlama/toplu-eposta/{storeNewsletterCampaign}/deneme', [Admin\CampaignController::class, 'test'])->middleware('throttle:10,10,campaigns.test')->name('campaigns.test');
            Route::post('pazarlama/toplu-eposta/{storeNewsletterCampaign}/gonder', [Admin\CampaignController::class, 'send'])->name('campaigns.send');
            Route::post('pazarlama/toplu-eposta/{storeNewsletterCampaign}/durdur', [Admin\CampaignController::class, 'cancel'])->name('campaigns.cancel');
            Route::get('raporlar', [Admin\ReportController::class, 'index'])->name('reports.index');
            Route::get('raporlar/siparisler.xlsx', [Admin\ReportController::class, 'export'])->middleware('throttle:20,1,store_admin.reports.export')->name('reports.export');
            Route::get('kampanyalar', [Admin\DiscountController::class, 'index'])->name('discounts.index');
            Route::get('kampanyalar/yeni', [Admin\DiscountController::class, 'create'])->name('discounts.create');
            Route::post('kampanyalar', [Admin\DiscountController::class, 'store'])->name('discounts.store');
            Route::get('kampanyalar/{storeDiscount}', [Admin\DiscountController::class, 'edit'])->name('discounts.edit');
            Route::put('kampanyalar/{storeDiscount}', [Admin\DiscountController::class, 'update'])->name('discounts.update');
            Route::delete('kampanyalar/{storeDiscount}', [Admin\DiscountController::class, 'destroy'])->name('discounts.destroy');
            Route::post('kampanyalar/{storeDiscount}/kodlar', [Admin\DiscountController::class, 'generateCodes'])->middleware('throttle:10,1,store_admin.discounts.codes')->name('discounts.codes');
            Route::get('kampanyalar/{storeDiscount}/kodlar.csv', [Admin\DiscountController::class, 'exportCodes'])->name('discounts.codes.export');
            Route::get('iadeler', [Admin\ReturnController::class, 'index'])->name('returns.index');
            Route::get('iadeler/{storeReturn}', [Admin\ReturnController::class, 'show'])->name('returns.show');
            Route::post('iadeler/{storeReturn}/onayla', [Admin\ReturnController::class, 'approve'])->name('returns.approve');
            Route::post('iadeler/{storeReturn}/reddet', [Admin\ReturnController::class, 'reject'])->name('returns.reject');
            Route::post('iadeler/{storeReturn}/teslim-al', [Admin\ReturnController::class, 'receive'])->name('returns.receive');
            Route::post('iadeler/{storeReturn}/para-iadesi', [Admin\ReturnController::class, 'refund'])->name('returns.refund');

            Route::get('ayarlar/odeme-teslimat', [Admin\PaymentSettingsController::class, 'edit'])->name('payments.edit');
            Route::put('ayarlar/odeme-teslimat', [Admin\PaymentSettingsController::class, 'update'])->name('payments.update');
            Route::delete('ayarlar/odeme-teslimat/anahtar/{key}', [Admin\PaymentSettingsController::class, 'removeSecret'])->name('payments.secret.destroy');
            Route::get('ayarlar/yayin-hazirligi', [Admin\SettingsController::class, 'launch'])->name('launch');
            Route::get('ayarlar/kargo-firmalari', [Admin\CarrierController::class, 'index'])->name('carriers.index');
            Route::post('ayarlar/kargo-firmalari', [Admin\CarrierController::class, 'store'])->name('carriers.store');
            Route::put('ayarlar/kargo-firmalari/{storeCarrier}', [Admin\CarrierController::class, 'update'])->name('carriers.update');
            Route::delete('ayarlar/kargo-firmalari/{storeCarrier}', [Admin\CarrierController::class, 'destroy'])->name('carriers.destroy');
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
    Route::post('odeme/iyzico/donus', [Front\CheckoutController::class, 'callback'])->middleware('throttle:60,1,payment.callback')->name('payment.callback');
    // iyzico anlık bildirimi (webhook): müşteri ödeme sonrası sayfayı kapatsa da sipariş hemen sonuçlanır.
    Route::post('odeme/iyzico/bildirim', [Front\CheckoutController::class, 'webhook'])->middleware('throttle:120,1,payment.webhook')->name('payment.webhook');

    // E-bülten onayı ve abonelikten çıkma: mağaza kapalıyken de çalışır (e-postadaki bağlantılar)
    Route::get('bulten/onay/{token}', [Front\NewsletterController::class, 'confirm'])->middleware('throttle:30,1,newsletter.confirm')->name('newsletter.confirm');
    Route::get('bulten/ayril/{token}', [Front\NewsletterController::class, 'showUnsubscribe'])->middleware('throttle:30,1,newsletter.leave')->name('newsletter.unsubscribe');
    Route::post('bulten/ayril/{token}', [Front\NewsletterController::class, 'unsubscribe'])->middleware('throttle:30,1,newsletter.leave.store')->name('newsletter.unsubscribe.store');
    Route::get('gorsel/bulten/{storeNewsletterCampaign}', [Front\MediaController::class, 'newsletter'])->name('media.newsletter');

    // Mağaza kapalıyken ziyaretçi "yakında" sayfasını görür
    Route::middleware('store.open')->group(function () {
        Route::post('bulten', [Front\NewsletterController::class, 'subscribe'])->middleware(['throttle:5,10,newsletter.subscribe', 'store.bot'])->name('newsletter.subscribe');
        Route::get('/', Front\HomeController::class)->name('home');
        Route::get('urunler', [Front\ListingController::class, 'index'])->name('products');
        Route::get('kategori/{slug}', [Front\ListingController::class, 'category'])->name('category');
        Route::get('marka/{slug}', [Front\ListingController::class, 'brand'])->name('brand');
        Route::get('markalar', [Front\PageController::class, 'brands'])->name('brands');
        Route::get('ara', [Front\ListingController::class, 'search'])->middleware('throttle:60,1,search')->name('search');
        Route::get('ara/oneri', [Front\ListingController::class, 'suggest'])->middleware('throttle:120,1,search.suggest')->name('search.suggest');
        Route::get('urun/{slug}', [Front\ProductController::class, 'show'])->name('product');
        Route::post('urun/{slug}/yorum', [Front\ReviewController::class, 'store'])->middleware(['auth:customer', 'auth.session', 'throttle:10,10,review.store'])->name('review.store');
        Route::post('stok-bildirimi', [Front\StockAlertController::class, 'store'])->middleware(['throttle:10,10,stock-alert.store', 'store.bot'])->name('stock-alert.store');
        Route::get('secki/{slug}', [Front\CollectionController::class, 'show'])->name('collection');
        Route::get('hakkimizda', [Front\SitePageController::class, 'about'])->name('about');
        Route::get('blog', [Front\BlogController::class, 'index'])->name('blog');
        // Yardım sayfaları (Vitrin → Yardım Sayfaları)
        foreach (HelpPages::PAGES as $helpKey => [, $helpPath]) {
            Route::get($helpPath, [Front\HelpController::class, 'show'])->defaults('help', $helpKey)->name('help.'.$helpKey);
        }
        Route::post('kargo-takip', [Front\HelpController::class, 'track'])->middleware(['throttle:10,1,help.track', 'store.bot'])->name('help.track');
        Route::get('blog/kategori/{slug}', [Front\BlogController::class, 'category'])->name('blog.category');
        Route::get('blog/{slug}', [Front\BlogController::class, 'show'])->name('blog.post');
        Route::get('iletisim', [Front\SitePageController::class, 'contact'])->name('contact');
        Route::post('iletisim', [Front\SitePageController::class, 'send'])->middleware(['throttle:5,10,contact.send', 'store.bot'])->name('contact.send');

        // Müşteri girişi ve üyelik (M5)
        Route::middleware('guest:customer')->group(function () {
            Route::get('giris', [Front\AuthController::class, 'showLogin'])->name('login');
            Route::post('giris', [Front\AuthController::class, 'login'])->middleware(['throttle:20,1,login.store', 'store.bot:0'])->name('login.store');
            Route::get('uye-ol', [Front\AuthController::class, 'showRegister'])->name('register');
            Route::post('uye-ol', [Front\AuthController::class, 'register'])->middleware(['throttle:10,60,register.store', 'store.bot'])->name('register.store');
            Route::get('parolami-unuttum', [Front\AuthController::class, 'showForgot'])->name('password.request');
            Route::post('parolami-unuttum', [Front\AuthController::class, 'sendReset'])->middleware(['throttle:5,10,password.email', 'store.bot'])->name('password.email');
            Route::get('parola-sifirla/{token}', [Front\AuthController::class, 'showReset'])->name('password.reset');
            Route::post('parola-sifirla', [Front\AuthController::class, 'reset'])->name('password.update');
        });

        Route::middleware(['auth:customer', 'auth.session'])->group(function () {
            Route::post('cikis', [Front\AuthController::class, 'logout'])->name('logout');
            Route::get('hesabim', [Front\AccountController::class, 'show'])->name('account');
            Route::put('hesabim', [Front\AccountController::class, 'updateProfile'])->name('account.profile');
            Route::put('hesabim/parola', [Front\AccountController::class, 'updatePassword'])->name('account.password');
            Route::delete('hesabim', [Front\AccountController::class, 'close'])->middleware('throttle:5,10,account.close')->name('account.close');
            Route::put('hesabim/e-posta', [Front\AccountController::class, 'updateEmail'])->middleware('throttle:5,10,account.email')->name('account.email');

            Route::get('hesabim/adres/yeni', [Front\AddressController::class, 'create'])->name('addresses.create');
            Route::post('hesabim/adres', [Front\AddressController::class, 'store'])->name('addresses.store');
            Route::get('hesabim/adres/{address}', [Front\AddressController::class, 'edit'])->whereNumber('address')->name('addresses.edit');
            Route::put('hesabim/adres/{address}', [Front\AddressController::class, 'update'])->whereNumber('address')->name('addresses.update');
            Route::put('hesabim/adres/{address}/varsayilan', [Front\AddressController::class, 'makeDefault'])->whereNumber('address')->name('addresses.default');
            Route::delete('hesabim/adres/{address}', [Front\AddressController::class, 'destroy'])->whereNumber('address')->name('addresses.destroy');

            Route::get('hesabim/favoriler', [Front\FavoriteController::class, 'index'])->name('favorites');
            Route::post('favoriler/{product}', [Front\FavoriteController::class, 'toggle'])->whereNumber('product')->middleware('throttle:60,1,favorites.toggle')->name('favorites.toggle');
        });
        Route::get('ilceler/{province}', [Front\AddressController::class, 'districts'])->whereNumber('province')->name('districts');

        // Ödeme ve siparişler (M6)
        Route::get('odeme', [Front\CheckoutController::class, 'show'])->name('checkout');
        Route::post('odeme', [Front\CheckoutController::class, 'store'])->middleware(['throttle:20,10,checkout.store', 'store.bot'])->name('checkout.store');
        Route::get('odeme/dogrulama', [Front\CheckoutController::class, 'verifyForm'])->name('checkout.verify');
        Route::post('odeme/dogrulama', [Front\CheckoutController::class, 'verify'])->middleware('throttle:20,10,checkout.verify.store')->name('checkout.verify.store');
        Route::post('odeme/dogrulama/yeniden', [Front\CheckoutController::class, 'resend'])->middleware('throttle:5,10,checkout.verify.resend')->name('checkout.verify.resend');
        Route::get('yasal/{key}', [Front\CheckoutController::class, 'legal'])->name('legal');
        Route::get('siparis/{orderNo}/{token}', [Front\OrderController::class, 'showByToken'])->middleware('throttle:60,1,order')->name('order');
        Route::get('siparis/{orderNo}/{token}/iade-faturasi/{note}', [Front\OrderController::class, 'creditNoteByToken'])->whereNumber('note')->middleware('throttle:30,1,order.credit-note')->name('order.credit-note');
        Route::get('siparis/{orderNo}/{token}/fatura', [Front\OrderController::class, 'invoiceByToken'])->middleware('throttle:30,1,order.invoice')->name('order.invoice');
        Route::post('siparis/{orderNo}/{token}/iade', [Front\ReturnController::class, 'storeByToken'])->middleware(['throttle:10,1,order.return', 'store.bot'])->name('order.return');
        Route::middleware(['auth:customer', 'auth.session'])->group(function () {
            Route::get('hesabim/siparisler', [Front\OrderController::class, 'index'])->name('orders');
            Route::get('hesabim/siparisler/{orderNo}', [Front\OrderController::class, 'showOwn'])->name('orders.show');
            Route::get('hesabim/siparisler/{orderNo}/fatura', [Front\OrderController::class, 'invoiceOwn'])->name('orders.invoice');
            Route::get('hesabim/siparisler/{orderNo}/iade-faturasi/{note}', [Front\OrderController::class, 'creditNoteOwn'])->whereNumber('note')->name('orders.credit-note');
            Route::post('hesabim/siparisler/{orderNo}/iade', [Front\ReturnController::class, 'storeOwn'])->middleware(['throttle:10,1,orders.return', 'store.bot'])->name('orders.return');
        });

        // Sepet (M5)
        Route::get('sepet', [Front\CartController::class, 'show'])->name('cart');
        Route::get('sepet/cekmece', [Front\CartController::class, 'drawer'])->name('cart.drawer');
        Route::post('sepet', [Front\CartController::class, 'add'])->middleware('throttle:60,1,cart.add')->name('cart.add');
        // Kod denemesi (kaba kuvvet) sınırlı.
        Route::post('sepet/kupon', [Front\CartController::class, 'applyCoupon'])->middleware('throttle:10,1,cart.coupon')->name('cart.coupon');
        Route::delete('sepet/kupon', [Front\CartController::class, 'removeCoupon'])->name('cart.coupon.remove');
        Route::put('sepet/{item}', [Front\CartController::class, 'update'])->whereNumber('item')->name('cart.update');
        Route::delete('sepet/{item}', [Front\CartController::class, 'remove'])->whereNumber('item')->name('cart.remove');

        Route::get('gorsel/urun/{storeImage}', [Front\MediaController::class, 'product'])->name('media.product');
        Route::get('gorsel/banner/{storeBanner}', [Front\MediaController::class, 'banner'])->name('media.banner');
        Route::get('gorsel/marka/{storeBrand}', [Front\MediaController::class, 'brand'])->name('media.brand');
        Route::get('gorsel/odeme-logolari', [Front\MediaController::class, 'paymentLogos'])->name('media.payment-logos');
        Route::get('gorsel/sayfa/{file}', [Front\MediaController::class, 'page'])->name('media.page');
        Route::get('gorsel/kategori/{storeCategory}', [Front\MediaController::class, 'category'])->name('media.category');
        Route::get('gorsel/secki/{storeCollection}', [Front\MediaController::class, 'collection'])->name('media.collection');
        Route::get('gorsel/blog/{storeBlogPost}', [Front\MediaController::class, 'blog'])->name('media.blog');
    });
});
