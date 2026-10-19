<?php

namespace Database\Seeders;

use App\Domain\Catalog\BrandLogo;
use App\Domain\Tenancy\TenantContext;
use App\Models\Brand;
use App\Models\Business;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;

/**
 * Demo marka logoları: markanın adından üretilen sade, yazı tabanlı logolar (gerçek logoların kopyası değildir).
 * Yalnızca logosu olmayan markalara eklenir. Çalıştırma: php artisan db:seed --class=BrandLogoDemoSeeder
 */
class BrandLogoDemoSeeder extends Seeder
{
    private const COLORS = ['#1e3a8a', '#0f766e', '#b91c1c', '#7c3aed', '#c2410c', '#0369a1', '#15803d', '#be185d', '#334155'];

    public function run(): void
    {
        abort_unless(app()->environment('local', 'testing'), 403, 'Demo verisi yalnızca local ortamda yüklenir.');

        $business = Business::where('slug', 'yapboz-kirtasiye')->first();
        if (! $business) {
            return;
        }

        app(TenantContext::class)->runAs($business->id, function () {
            $logos = app(BrandLogo::class);
            foreach (Brand::whereNull('logo_path')->orderBy('id')->get() as $i => $brand) {
                $logos->replace($brand, $this->render($brand->name, self::COLORS[$i % count(self::COLORS)]));
            }
        });
    }

    private function render(string $name, string $hex): UploadedFile
    {
        $font = collect(['C:/Windows/Fonts/segoeuib.ttf', 'C:/Windows/Fonts/arialbd.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf'])->first(fn ($f) => is_file($f));
        $text = mb_strtoupper(strtr($name, ['i' => 'İ', 'ı' => 'I']), 'UTF-8');
        $size = 64;
        $box = $font ? imagettfbbox($size, 0, $font, $text) : [0, 0, 300, 0, 300, -60];
        $w = min(1200, $box[2] - $box[0] + 80);
        $h = 200;

        $img = imagecreatetruecolor($w, $h);
        imagealphablending($img, false);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
        imagesavealpha($img, true);
        imagealphablending($img, true);

        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
        $color = imagecolorallocate($img, $r, $g, $b);
        if ($font) {
            imagettftext($img, $size, 0, 40, 122, $color, $font, $text);
        }
        // Altta marka renginde ince vurgu çizgisi
        imagefilledrectangle($img, 42, 150, (int) min($w - 42, 42 + ($box[2] - $box[0]) * 0.35), 160, $color);

        $path = tempnam(sys_get_temp_dir(), 'brand').'.png';
        imagepng($img, $path);

        return new UploadedFile($path, 'logo.png', 'image/png', null, true);
    }
}
