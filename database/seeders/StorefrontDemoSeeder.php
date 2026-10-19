<?php

namespace Database\Seeders;

use App\Domain\Storefront\BannerService;
use App\Domain\Storefront\ShowcaseService;
use App\Domain\Tenancy\TenantContext;
use App\Models\Business;
use App\Models\Category;
use App\Models\HomeBanner;
use App\Models\Product;
use App\Models\Showcase;
use Database\Seeders\Support\DemoBanners;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;

/**
 * Yapboz Kırtasiye için örnek vitrin: üç slayt ve dört ürün vitrini. Toptancı daha sonra Vitrin sayfasından değiştirir.
 * Çalıştırma: php artisan db:seed --class=StorefrontDemoSeeder (vitrin zaten doluysa dokunmaz)
 */
class StorefrontDemoSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment('local', 'testing'), 403, 'Demo verisi yalnızca local ortamda yüklenir.');

        $business = Business::where('slug', 'yapboz-kirtasiye')->first();
        if (! $business) {
            $this->command?->warn('Yapboz Kırtasiye bulunamadı; önce YapbozDemoSeeder çalıştırın.');

            return;
        }

        app(TenantContext::class)->runAs($business->id, function () {
            // Vitrin ve slaytlar ayrı ayrı denetlenir; dolu olan bölüme dokunulmaz.
            if (! Showcase::exists()) {
                $this->showcases();
            }
            if (HomeBanner::exists()) {
                $this->command?->warn('Slaytlar zaten var; demo slaytlar yüklenmedi.');

                return;
            }
            $this->banners();
            $this->strips();
        });

        $this->command?->info('Demo vitrin hazır.');
    }

    private function banners(): void
    {
        $art = new DemoBanners;
        $service = app(BannerService::class);
        $category = fn (string $name) => Category::where('name', $name)->value('id');

        $slides = [
            [
                ['#0b1640', '#1e40af', '#38bdf8'],
                [
                    ['notebook', ['main' => '#f97316', 'accent' => '#9a3412', 'label' => 'A4 · 100'], 0.63, 0.52, 0.95, -8],
                    ['notebook', ['main' => '#2563eb', 'accent' => '#1e3a8a', 'label' => 'A5 · 80'], 0.77, 0.48, 1.0, 6],
                    ['pencilbox', ['main' => '#facc15', 'accent' => '#1e3a8a', 'box' => '#0f766e', 'label' => 'HB × 12'], 0.9, 0.55, 0.85, -4],
                    ['ruler', ['main' => '#38bdf8', 'accent' => '#0c4a6e'], 0.7, 0.82, 0.75, -6],
                ],
                [
                    'title' => 'Okul sezonu başladı',
                    'subtitle' => 'Defter, kalem ve okul ürünlerinde size özel fiyatlar. Stoklar tükenmeden siparişinizi verin.',
                    'button_label' => 'Okul ürünlerini incele', 'link_type' => 'category', 'link_id' => $category('Defterler'),
                ],
            ],
            [
                ['#042f2e', '#0f766e', '#5eead4'],
                [
                    ['paper', ['main' => '#0f766e', 'accent' => '#134e4a'], 0.66, 0.5, 1.0, 0],
                    ['stapler', ['main' => '#dc2626'], 0.83, 0.62, 0.85, -10],
                    ['folder', ['main' => '#3b82f6', 'accent' => '#1d4ed8'], 0.9, 0.36, 0.75, 10],
                    ['sticky', ['set' => '#fde047,#f9a8d4,#93c5fd'], 0.76, 0.24, 0.6, 0],
                ],
                [
                    'title' => 'Ofisinizin ihtiyaçları tek siparişte',
                    'subtitle' => 'Fotokopi kağıdından zımbaya, telli dosyadan not kağıdına; ofis malzemeleri stoklarımızda.',
                    'button_label' => 'Ofis malzemeleri', 'link_type' => 'category', 'link_id' => $category('Ofis Malzemeleri'),
                ],
            ],
            [
                ['#431407', '#c2410c', '#fcd34d'],
                [
                    ['paint', ['set' => '#ef4444,#f97316,#facc15,#22c55e,#06b6d4,#3b82f6,#8b5cf6,#ec4899,#78350f,#111827,#ffffff,#64748b'], 0.7, 0.62, 0.95, -6],
                    ['pencilbox', ['main' => '#ef4444', 'accent' => '#7c2d12', 'box' => '#1e3a8a', 'label' => '12 Renk', 'pencils' => '#ef4444,#f59e0b,#22c55e,#3b82f6,#8b5cf6,#ec4899'], 0.86, 0.45, 0.95, 5],
                    ['markers', ['set' => '#ef4444,#f97316,#22c55e,#3b82f6,#a855f7'], 0.6, 0.3, 0.7, -12],
                    ['highlighter', ['main' => '#fde047', 'accent' => '#ca8a04'], 0.9, 0.8, 0.55, 0],
                ],
                [
                    'title' => 'Renkli ürünlerde yeni sezon',
                    'subtitle' => 'Kuru boya, sulu boya ve keçeli kalemlerde altın bayilerimize ek %5 indirim.',
                    'button_label' => 'Boyaları keşfet', 'link_type' => 'category', 'link_id' => $category('Boya'),
                ],
            ],
        ];

        $videos = $this->videos();
        foreach ($slides as $i => [$palette, $items, $text]) {
            // Videolar (varsa) görsellerin arasına yerleştirilir: video, görsel, görsel, video, görsel.
            if ($i === 0 && isset($videos['tanitim-yeni-sezon'])) {
                $this->video($service, $videos['tanitim-yeni-sezon'], [
                    'title' => 'Yeni sezon ürünleri raflarda',
                    'subtitle' => 'Defterden kaleme, okul ve ofis ürünlerinde geniş çeşit; hepsi size özel fiyatlarla tek panelde.',
                    'button_label' => 'Tüm ürünleri gör', 'link_type' => 'catalog',
                ]);
            }
            $service->create($text + ['placement' => 'hero', 'text_position' => 'left', 'is_active' => true], ['media' => $art->make($palette, $items, 1920, 600)]);
            if ($i === 1 && isset($videos['tanitim-urun-akisi'])) {
                $this->video($service, $videos['tanitim-urun-akisi'], [
                    'title' => 'Siparişiniz birkaç tıkla hazır',
                    'subtitle' => 'Ürünü seçin, miktarı girin, sepete ekleyin. Siparişinizin her aşamasını panelden takip edin.',
                    'button_label' => 'Siparişe başla', 'link_type' => 'catalog',
                ]);
            }
        }
    }

    /** Ara bant: vitrinlerin arasındaki ince şeritler. */
    private function strips(): void
    {
        $art = new DemoBanners;
        $service = app(BannerService::class);

        $service->create([
            'placement' => 'strip', 'title' => 'Siparişinizi 7/24 verin', 'subtitle' => 'Mesai saatini beklemeden; stok ve fiyatlar her an güncel.',
            'button_label' => 'Hemen sipariş ver', 'link_type' => 'catalog', 'text_position' => 'left', 'is_active' => true,
        ], ['media' => $art->make(['#0b1640', '#1e3a8a', '#38bdf8'], [
            ['pen', ['main' => '#2563eb'], 0.70, 0.55, 0.95, -10],
            ['notebook', ['main' => '#f97316', 'accent' => '#9a3412', 'label' => 'A4'], 0.80, 0.52, 1.05, 8],
            ['sticky', ['set' => '#fde047,#f9a8d4,#93c5fd'], 0.90, 0.50, 0.95, 0],
        ], 1920, 320)]);

        $service->create([
            'placement' => 'strip', 'title' => 'Cari hesabınız her an elinizde', 'subtitle' => 'Bakiye, hareketler ve ekstrenizi panelden görüntüleyin; telefonla sormanıza gerek yok.',
            'link_type' => 'none', 'text_position' => 'left', 'is_active' => true,
        ], ['media' => $art->make(['#042f2e', '#0f766e', '#5eead4'], [
            ['folder', ['main' => '#3b82f6', 'accent' => '#1d4ed8'], 0.74, 0.52, 1.0, -6],
            ['paper', ['main' => '#0f766e', 'accent' => '#134e4a'], 0.86, 0.52, 1.05, 4],
        ], 1920, 320)]);
    }

    /** @return array<string, array{video: string, poster: ?string}> php artisan demo:videolar çıktısı */
    private function videos(): array
    {
        $found = [];
        foreach (glob(storage_path('app/demo-media/*.webm')) ?: [] as $file) {
            $poster = substr($file, 0, -5).'.png';
            $found[pathinfo($file, PATHINFO_FILENAME)] = ['video' => $file, 'poster' => is_file($poster) ? $poster : null];
        }

        return $found;
    }

    private function video(BannerService $service, array $files, array $text): void
    {
        $copy = function (string $path, string $mime) {
            $tmp = tempnam(sys_get_temp_dir(), 'demo').'.'.pathinfo($path, PATHINFO_EXTENSION);
            copy($path, $tmp);

            return new UploadedFile($tmp, basename($path), $mime, null, true);
        };

        $service->create($text + ['placement' => 'hero', 'text_position' => 'left', 'is_active' => true], array_filter([
            'media' => $copy($files['video'], 'video/webm'),
            'poster' => $files['poster'] ? $copy($files['poster'], 'image/png') : null,
        ]));
    }

    private function showcases(): void
    {
        $service = app(ShowcaseService::class);

        $picked = $service->create(['name' => 'Önerdiğimiz ürünler', 'type' => 'manual', 'product_limit' => 8]);
        foreach (['SD-A4-100', 'TK-10-MAV', 'KK-HB-12', 'FK-A4-500', 'KC-12-SET', 'YN-76-3RN', 'ZM-10-KRM', 'KB-12-UZN'] as $sku) {
            if ($id = Product::where('sku', $sku)->value('id')) {
                $service->addProduct($picked, $id);
            }
        }

        $service->create(['name' => 'Çok satanlar', 'type' => 'best_sellers', 'product_limit' => 8]);
        $service->create(['name' => 'Yeni gelenler', 'type' => 'new', 'product_limit' => 8]);
        $service->create(['name' => 'Defterler', 'type' => 'category', 'source_id' => Category::where('name', 'Defterler')->value('id'), 'product_limit' => 8]);
    }
}
