<?php

namespace Database\Seeders\Support;

use GdImage;
use Illuminate\Http\UploadedFile;

/**
 * Demo ana sayfa slaytları: metinsiz kompozisyonlar (metin slayt üzerine HTML olarak yazılır).
 * Sol taraf metin için sade bırakılır; ürünler sağda, yumuşak gölge ve ışık halkalarıyla dizilir.
 */
class DemoBanners
{
    public function __construct(private DemoImages $images = new DemoImages) {}

    /**
     * @param  array{0: string, 1: string, 2: string}  $palette  koyu, orta, ışık rengi
     * @param  list<array{0: string, 1: array, 2: float, 3: float, 4: float, 5: float}>  $items  [şekil, renkler, x(0-1), y(0-1), boyut(yükseklik oranı), dönüş]
     */
    public function make(array $palette, array $items, int $w = 1920, int $h = 640): UploadedFile
    {
        $img = imagecreatetruecolor($w, $h);
        imagealphablending($img, true);
        [$dark, $mid, $light] = $palette;

        // Yatay degrade (koyu → orta)
        [$r1, $g1, $b1] = $this->rgb($dark);
        [$r2, $g2, $b2] = $this->rgb($mid);
        for ($x = 0; $x < $w; $x++) {
            $t = $x / ($w - 1);
            imageline($img, $x, 0, $x, $h, imagecolorallocate($img, (int) ($r1 + ($r2 - $r1) * $t), (int) ($g1 + ($g2 - $g1) * $t), (int) ($b1 + ($b2 - $b1) * $t)));
        }

        // Işık halkaları ve nokta ızgarası
        [$lr, $lg, $lb] = $this->rgb($light);
        $ring = max(400, (int) ($h * 1.5));
        for ($i = 0; $i < 40; $i++) {
            $d = $ring - (int) ($i * $ring / 44);
            imagefilledellipse($img, (int) ($w * 0.72), (int) ($h * 0.5), $d, $d, imagecolorallocatealpha($img, $lr, $lg, $lb, 126 - intdiv($i, 3)));
        }
        foreach ([[0.93, 0.12, 0.55], [0.55, 0.95, 0.35]] as [$cx, $cy, $s]) {
            imagesetthickness($img, max(2, (int) ($h / 200)));
            imageellipse($img, (int) ($w * $cx), (int) ($h * $cy), (int) ($h * $s), (int) ($h * $s), imagecolorallocatealpha($img, 255, 255, 255, 112));
        }
        $dot = imagecolorallocatealpha($img, 255, 255, 255, 118);
        $step = max(18, (int) ($h / 22));
        for ($y = $step; $y < $h; $y += $step) {
            for ($x = (int) ($w * 0.04); $x < $w * 0.38; $x += $step) {
                imagefilledellipse($img, $x, $y, 4, 4, $dot);
            }
        }

        // Ürünler (önce gölge, sonra kesik görsel)
        foreach ($items as [$shape, $colors, $px, $py, $scale, $rotate]) {
            $size = (int) ($h * $scale);
            $cut = $this->images->cutout($shape, $colors, $size, $rotate);
            $x = (int) ($w * $px - $size / 2);
            $y = (int) ($h * $py - $size / 2);
            $this->shadow($img, $x + (int) ($size * 0.5), $y + (int) ($size * 0.86), (int) ($size * 0.62));
            imagecopy($img, $cut, $x, $y, 0, 0, $size, $size);
        }

        $path = tempnam(sys_get_temp_dir(), 'banner').'.png';
        imagepng($img, $path, 6);

        return new UploadedFile($path, 'slayt.png', 'image/png', null, true);
    }

    private function shadow(GdImage $img, int $cx, int $cy, int $width): void
    {
        for ($i = 0; $i < 10; $i++) {
            imagefilledellipse($img, $cx, $cy, $width - $i * (int) ($width / 14), (int) ($width / 6) - $i * 2, imagecolorallocatealpha($img, 0, 0, 0, 122 - $i));
        }
    }

    /** @return array{int, int, int} */
    private function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
}
