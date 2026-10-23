<?php

namespace Database\Seeders;

use App\Domain\Store\Catalog\CategoryService;
use App\Domain\Store\Catalog\ProductService;
use App\Domain\Store\Inventory\StoreStockService;
use App\Domain\Store\Storefront\CollectionAdmin;
use App\Domain\Store\Storefront\StorefrontAdmin;
use App\Models\Store\StoreBrand;
use App\Models\Store\StoreCollection;
use App\Models\Store\StoreDiscount;
use App\Models\Store\StorePage;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreProductVariant;
use App\Models\Store\StoreSetting;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Vitrin tasarımını önizlemek için örnek mağaza verisi (kategori, marka, ürün, banner, kupon, sayfalar). Görseller kodla
 * üretilir. YALNIZCA yereldeki ayrı "b2b_vitrin" veritabanında çalışır; geliştirme (b2b) ve canlı veritabanına yazmaz.
 * Kullanım: DB_DATABASE=b2b_vitrin php artisan migrate --seed --seeder=StoreShowcaseSampleSeeder
 */
class StoreShowcaseSampleSeeder extends Seeder
{
    private const CATEGORIES = [
        ['Kalemler', 'orange', 'Tükenmez, jel, kurşun ve keçeli kalemler', ['Tükenmez Kalemler', 'Jel Kalemler', 'Kurşun Kalemler', 'Keçeli Kalemler']],
        ['Defterler', 'green', 'Defter, ajanda ve bloknotlar', ['Spiralli Defterler', 'Ajandalar', 'Bloknotlar']],
        ['Okul Ürünleri', 'blue', 'Çanta, kalemlik ve okul setleri', ['Okul Çantaları', 'Kalem Kutuları', 'Beslenme Çantaları']],
        ['Sanat & Boya', 'yellow', 'Kuru boya, sulu boya ve resim malzemeleri', ['Kuru Boyalar', 'Sulu Boyalar', 'Resim Defterleri']],
        ['Ofis', 'navy', 'Dosya, zımba ve masa düzeni', ['Dosyalar', 'Zımba ve Delgeç', 'Masa Düzenleyiciler']],
        ['Kağıt Ürünleri', 'orange', 'Fotokopi kağıdı, karton ve zarf', ['Fotokopi Kağıtları', 'Renkli Kartonlar']],
    ];

    private const BRANDS = ['Faber-Castell', 'Pensan', 'Staedtler', 'Adel', 'Gıpta', 'Keskin Color', 'Bic', 'Pilot'];

    private const COLORS = ['orange' => [234, 88, 12], 'green' => [22, 163, 74], 'blue' => [2, 132, 199], 'yellow' => [202, 138, 4], 'navy' => [30, 58, 138]];

    public function run(): void
    {
        abort_unless(app()->environment('local') && DB::getDatabaseName() === 'b2b_vitrin', 403, 'Örnek vitrin verisi yalnızca yereldeki b2b_vitrin veritabanına yüklenir.');
        if (StoreProduct::exists()) {
            $this->homeExtras(); // ürünler önceden yüklendiyse yalnızca ana sayfa ekleri (seçki, yan banner, kupon kartı)

            return;
        }

        StoreSetting::put([
            'is_open' => '1', 'phone' => '0222 123 45 67', 'whatsapp' => '0532 123 45 67', 'email' => 'merhaba@yapbozkirtasiye.com',
            'address' => 'Hoşnudiye Mah. İsmet İnönü 1 Cad. No: 12, Tepebaşı / Eskişehir', 'instagram' => 'https://instagram.com/yapbozkirtasiye',
            'company_title' => 'Yapboz Kırtasiye Ltd. Şti.', 'footer_about' => "1998'den bu yana Eskişehir'in güvenilir kırtasiyesi. Orijinal ürünler, uygun fiyatlar.",
            'shipping_cargo_fee' => '59.90', 'shipping_free_threshold' => '750.00', 'local_delivery_enabled' => '1', 'local_delivery_fee' => '0.00',
            'announcement' => 'Okula dönüş fırsatları başladı!',
        ]);

        $brands = collect(self::BRANDS)->mapWithKeys(fn ($name) => [$name => StoreBrand::create(['name' => $name, 'slug' => Str::slug($name), 'is_active' => true])]);
        $categories = app(CategoryService::class);
        $products = app(ProductService::class);
        $stock = app(StoreStockService::class);

        $n = 0;
        foreach (self::CATEGORIES as $i => [$name, $color, $tagline, $children]) {
            $root = $categories->create(['name' => $name, 'color' => $color, 'tagline' => $tagline]);
            $categories->setImage($root, $this->image($name, $color, 900, 900, 'tile'));
            foreach ($children as $childName) {
                $child = $categories->create(['name' => $childName, 'parent_id' => $root->id]);
                for ($k = 0; $k < 3; $k++, $n++) {
                    $brand = $brands->values()[$n % $brands->count()];
                    $title = "{$brand->name} ".rtrim($childName, 'ler').' '.['Klasik', 'Pro', 'Renkli', 'Mini', 'Eko'][$n % 5];
                    $price = 20 + ($n * 37) % 480;
                    $product = $products->create(
                        ['name' => $title, 'category_id' => $child->id, 'brand_id' => $brand->id, 'is_published' => true, 'is_featured' => $n % 4 === 0,
                            'description' => "{$title}, günlük kullanım için dayanıklı ve kaliteli.\n\n- Orijinal ürün\n- Hızlı kargo"],
                        ['sku' => 'YB-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT), 'price' => (string) $price, 'vat_rate' => '20',
                            'compare_at_price' => $n % 3 === 0 ? (string) round($price * 1.25) : null],
                    );
                    $products->addImage($product, $this->image($title, $color, 800, 800, 'product'));
                    $stock->receive($product->variants()->first(), $n % 9 === 0 ? 2 : 40);
                    $product->forceFill(['created_at' => now()->subDays(60 - $n)])->save();
                }
            }
        }

        $admin = app(StorefrontAdmin::class);
        $admin->createBanner(['title' => 'Okula dönüş başladı', 'subtitle' => 'Çanta, defter ve kalemde seçili ürünlerde %20 indirim', 'button_text' => 'Alışverişe başla', 'link_url' => '/urunler', 'is_active' => true],
            $this->image('Okula dönüş', 'blue', 2400, 900, 'banner'), null);
        $admin->createBanner(['title' => 'Sanat malzemeleri', 'subtitle' => 'Boya, fırça ve resim defterlerinde yeni sezon', 'button_text' => 'Keşfet', 'link_url' => '/urunler', 'is_active' => true],
            $this->image('Sanat', 'yellow', 2400, 900, 'banner'), null);

        StoreDiscount::create(['name' => 'Hoş geldin indirimi', 'code' => 'HOSGELDIN', 'type' => 'percent', 'value' => '10', 'first_order_only' => true, 'is_active' => true]);
        StoreDiscount::create(['name' => '1.000 TL üzeri 100 TL', 'code' => 'YAPBOZ100', 'type' => 'fixed', 'value' => '100', 'min_subtotal' => '1000', 'is_active' => true]);

        StorePage::create(['key' => 'about', 'is_published' => true, 'content' => ['headline' => 'Hakkımızda', 'stats' => [['value' => '1998', 'label' => 'Kuruluş'], ['value' => '5.000+', 'label' => 'Ürün çeşidi'], ['value' => '12', 'label' => 'İlçeye teslimat']]]]);
        StorePage::create(['key' => 'contact', 'is_published' => true, 'content' => ['form_enabled' => true]]);
        $this->homeExtras();
    }

    /** Aşama 2 ana sayfa blokları için: seçkiler, yan banner'lar, ana sayfada gösterilen kuponlar. */
    private function homeExtras(): void
    {
        if (StoreCollection::exists()) {
            return;
        }
        $admin = app(StorefrontAdmin::class);
        $admin->createBanner(['title' => 'Ofisiniz için', 'subtitle' => 'Dosya ve masa düzeni', 'link_url' => '/urunler', 'placement' => 'side', 'is_active' => true],
            $this->image('Ofis', 'green', 900, 600, 'banner'), null);
        $admin->createBanner(['title' => 'Sanat köşesi', 'subtitle' => 'Boya ve fırçalar', 'link_url' => '/urunler', 'placement' => 'side', 'is_active' => true],
            $this->image('Sanat', 'orange', 900, 600, 'banner'), null);
        StoreDiscount::query()->update(['show_on_home' => true]);
        StoreDiscount::create(['name' => 'Kargo bizden', 'code' => 'KARGOBEDAVA', 'type' => 'free_shipping', 'min_subtotal' => '400', 'is_active' => true, 'show_on_home' => true]);

        $collections = app(CollectionAdmin::class);
        $skus = StoreProductVariant::orderBy('id')->pluck('sku')->all();
        foreach ([['Okula dönüş', 'Çanta, defter ve kalemde yeni sezon', 'blue', 0], ['Ofis masası', 'Düzenli bir çalışma alanı için', 'green', 12],
            ['Sanat atölyesi', 'Boya, fırça ve resim defterleri', 'orange', 24], ['Hediyelik', 'Sevdiklerinize kırtasiye seçkisi', 'yellow', 36]] as [$title, $subtitle, $color, $from]) {
            $collections->save(null, ['title' => $title, 'subtitle' => $subtitle, 'color' => $color, 'product_skus' => implode(',', array_slice($skus, $from, 8)), 'is_active' => true],
                $this->image($title, $color, 1200, 900, 'banner'), false);
        }
    }

    /** Kodla üretilmiş yer tutucu görsel (gerçek fotoğraf yerine; yalnızca önizleme içindir). */
    private function image(string $label, string $color, int $w, int $h, string $kind): UploadedFile
    {
        [$r, $g, $b] = self::COLORS[$color];
        $img = imagecreatetruecolor($w, $h);
        $soft = imagecolorallocate($img, (int) (255 - (255 - $r) * 0.12), (int) (255 - (255 - $g) * 0.12), (int) (255 - (255 - $b) * 0.12));
        imagefill($img, 0, 0, $kind === 'banner' ? imagecolorallocate($img, 11, 22, 49) : $soft);
        $main = imagecolorallocate($img, $r, $g, $b);
        $light = imagecolorallocate($img, min(255, $r + 70), min(255, $g + 70), min(255, $b + 70));
        $white = imagecolorallocate($img, 255, 255, 255);
        $font = collect(['C:/Windows/Fonts/segoeuib.ttf', 'C:/Windows/Fonts/arialbd.ttf'])->first(fn ($f) => is_file($f));

        if ($kind === 'banner') {
            imagefilledellipse($img, (int) ($w * 0.78), (int) ($h * 0.5), (int) ($h * 1.1), (int) ($h * 1.1), $main);
            imagefilledellipse($img, (int) ($w * 0.9), (int) ($h * 0.2), (int) ($h * 0.45), (int) ($h * 0.45), $light);
        } else {
            // Ürün gibi duran basit bir biçim: kapaklı defter / kutu.
            $x1 = (int) ($w * 0.28);
            $y1 = (int) ($h * 0.18);
            $x2 = (int) ($w * 0.72);
            $y2 = (int) ($h * 0.82);
            imagefilledrectangle($img, $x1 + 14, $y1 + 14, $x2 + 14, $y2 + 14, imagecolorallocatealpha($img, 0, 0, 0, 100));
            imagefilledrectangle($img, $x1, $y1, $x2, $y2, $main);
            imagefilledrectangle($img, $x1, (int) ($h * 0.42), $x2, (int) ($h * 0.52), $light);
            for ($y = $y1 + 20; $y < $y2 - 10; $y += 34) {
                imagefilledellipse($img, $x1 + 16, $y, 12, 12, $white);
            }
            if ($font && $kind === 'product') {
                imagettftext($img, (int) ($w * 0.045), 0, $x1 + 40, (int) ($h * 0.485), $white, $font, mb_strtoupper(mb_substr($label, 0, 12)));
            }
        }
        $path = tempnam(sys_get_temp_dir(), 'vtr').'.png';
        imagepng($img, $path);

        return new UploadedFile($path, Str::slug($label).'.png', 'image/png', null, true);
    }
}
