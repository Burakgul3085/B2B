<?php

namespace Database\Seeders;

use App\Domain\Storefront\BannerService;
use App\Domain\Storefront\ShowcaseService;
use App\Domain\Tenancy\TenantContext;
use App\Models\Business;
use App\Models\Category;
use App\Models\HomeBanner;
use App\Models\Product;
use Database\Seeders\Support\DemoBanners;
use Illuminate\Database\Seeder;

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
            if (HomeBanner::exists()) {
                $this->command?->warn('Vitrin zaten dolu; demo vitrin yüklenmedi.');

                return;
            }
            $this->banners();
            $this->showcases();
        });

        $this->command?->info('Demo vitrin yüklendi: 3 slayt, 4 ürün vitrini.');
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

        foreach ($slides as [$palette, $items, $text]) {
            $service->create($text + ['text_position' => 'left', 'is_active' => true], ['media' => $art->make($palette, $items)]);
        }
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
