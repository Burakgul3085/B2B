<?php

namespace Database\Seeders\Support;

use GdImage;
use Illuminate\Http\UploadedFile;

/**
 * Demo verisi için ürün görsellerini ve işletme logosunu GD ile çizer (dış kaynaktan görsel indirilmez).
 * Çizim 2 kat büyük tuvalde yapılır, sonra küçültülerek kenarlar yumuşatılır.
 */
class DemoImages
{
    private const WORK = 1600;

    private const OUT = 800;

    /** @param  array<string, string>  $colors */
    public function product(string $shape, array $colors = []): UploadedFile
    {
        $c = $colors + ['main' => '#2563eb', 'accent' => '#1e3a8a', 'bg' => '#eef2ff'];

        $img = $this->canvas($c['bg']);
        $layer = $this->layer();
        $angle = match ($shape) {
            'pen', 'gel', 'pencil', 'highlighter' => 32,
            'ruler' => 18,
            'eraser', 'stapler' => 8,
            default => 0,
        };

        $this->{'draw'.ucfirst($shape)}($layer, $c);

        $this->shadow($img, in_array($shape, ['pen', 'gel', 'pencil', 'ruler', 'highlighter'], true) ? 520 : 440);
        if ($angle) {
            $layer = imagerotate($layer, $angle, imagecolorallocatealpha($layer, 0, 0, 0, 127));
        }
        $this->paste($img, $layer);

        return $this->toUpload($img, "{$shape}.png");
    }

    public function logo(string $text): UploadedFile
    {
        $w = 960;
        $h = 320;
        $img = imagecreatetruecolor($w * 2, $h * 2);
        imagealphablending($img, false);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
        imagealphablending($img, true);
        imagesavealpha($img, true);

        // Yapboz parçası: kare + iki çıkıntı
        $this->roundRect($img, 60, 140, 420, 500, 46, $this->color($img, '#f97316'));
        $this->circle($img, 240, 140, 70, $this->color($img, '#f97316'));
        $this->circle($img, 420, 320, 70, $this->color($img, '#f97316'));
        $this->circle($img, 240, 500, 60, imagecolorallocatealpha($img, 0, 0, 0, 127), false);
        $this->roundRect($img, 130, 210, 350, 430, 30, $this->color($img, '#ffffff'));
        $this->roundRect($img, 170, 250, 310, 390, 18, $this->color($img, '#1e3a8a'));

        $bold = $this->font(true);
        $regular = $this->font(false);
        if ($bold) {
            imagettftext($img, 118, 0, 540, 330, $this->color($img, '#1e3a8a'), $bold, $text);
            imagettftext($img, 52, 0, 546, 450, $this->color($img, '#f97316'), $regular ?: $bold, 'KIRTASİYE · B2B');
        }

        $out = imagecreatetruecolor($w, $h);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagecopyresampled($out, $img, 0, 0, 0, 0, $w, $h, $w * 2, $h * 2);

        return $this->toUpload($out, 'logo.png', false);
    }

    /* ---------------- Çizimler (yatay/merkezde, 1600×1600 katman) ---------------- */

    private function drawPen(GdImage $l, array $c): void
    {
        $body = $this->color($l, '#f1f5f9');
        $this->roundRect($l, 260, 748, 1180, 852, 40, $this->color($l, '#cbd5e1'));
        $this->roundRect($l, 262, 752, 1176, 846, 38, $body);
        imagefilledrectangle($l, 300, 760, 1150, 780, $this->color($l, '#ffffff'));
        $this->roundRect($l, 1040, 752, 1180, 846, 20, $this->color($l, $c['main']));
        imagefilledpolygon($l, [1178, 762, 1178, 838, 1300, 806, 1300, 794], $this->color($l, '#94a3b8'));
        imagefilledpolygon($l, [1298, 790, 1298, 810, 1335, 800], $this->color($l, '#334155'));
        $this->roundRect($l, 220, 744, 330, 856, 34, $this->color($l, $c['main']));
        $this->roundRect($l, 330, 712, 720, 744, 14, $this->color($l, $c['main']));
        $this->roundRect($l, 330, 712, 360, 770, 12, $this->color($l, $c['main']));
    }

    private function drawGel(GdImage $l, array $c): void
    {
        $this->roundRect($l, 250, 740, 1170, 860, 46, $this->color($l, $c['accent']));
        $this->roundRect($l, 256, 746, 1164, 854, 42, $this->color($l, $c['main'], 35));
        imagefilledrectangle($l, 300, 762, 1120, 778, $this->color($l, '#ffffff', 70));
        $this->roundRect($l, 980, 740, 1170, 860, 30, $this->color($l, '#1f2937'));
        for ($x = 1000; $x < 1160; $x += 26) {
            imagefilledrectangle($l, $x, 744, $x + 10, 856, $this->color($l, '#374151'));
        }
        imagefilledpolygon($l, [1168, 760, 1168, 840, 1290, 806, 1290, 794], $this->color($l, '#cbd5e1'));
        imagefilledpolygon($l, [1288, 790, 1288, 810, 1325, 800], $this->color($l, $c['main']));
        $this->roundRect($l, 200, 736, 320, 864, 40, $this->color($l, $c['main']));
        $this->roundRect($l, 320, 704, 700, 738, 15, $this->color($l, $c['main']));
    }

    private function drawPencil(GdImage $l, array $c): void
    {
        $this->pencil($l, 800, 800, 1.0, $c['main'], $c['accent']);
    }

    private function drawPencilbox(GdImage $l, array $c): void
    {
        $palette = $c['pencils'] ?? null;
        foreach (range(0, 5) as $i) {
            $pencil = $this->layer();
            $color = $palette ? explode(',', $palette)[$i] : $c['main'];
            $this->pencil($pencil, 800, 800, 0.9, $color, $palette ? $color : $c['accent']);
            $pencil = imagerotate($pencil, 90, imagecolorallocatealpha($pencil, 0, 0, 0, 127));
            $this->copyAt($l, $pencil, -280 + $i * 104, -90 - ($i % 2) * 50);
        }
        $this->roundRect($l, 430, 760, 1170, 1240, 28, $this->color($l, $c['box'] ?? $c['accent']));
        $this->roundRect($l, 430, 760, 1170, 830, 20, $this->color($l, '#000000', 95));
        $this->roundRect($l, 520, 900, 1080, 1120, 22, $this->color($l, '#ffffff'));
        if ($font = $this->font(true)) {
            imagettftext($l, 62, 0, 590, 1040, $this->color($l, $c['accent']), $font, $c['label'] ?? 'HB × 12');
        }
    }

    private function drawMarkers(GdImage $l, array $c): void
    {
        foreach (explode(',', $c['set']) as $i => $color) {
            $x = 470 + $i * 140;
            $this->roundRect($l, $x, 470, $x + 110, 1220, 40, $this->color($l, '#e2e8f0'));
            $this->roundRect($l, $x + 4, 474, $x + 106, 1216, 38, $this->color($l, '#f8fafc'));
            $this->roundRect($l, $x, 390, $x + 110, 650, 40, $this->color($l, $color));
            imagefilledrectangle($l, $x + 4, 900, $x + 106, 1000, $this->color($l, $color, 30));
            $this->roundRect($l, $x + 10, 1160, $x + 100, 1230, 20, $this->color($l, $color));
        }
    }

    private function drawHighlighter(GdImage $l, array $c): void
    {
        $this->roundRect($l, 260, 700, 1120, 900, 70, $this->color($l, $c['main']));
        imagefilledrectangle($l, 320, 722, 1060, 746, $this->color($l, '#ffffff', 80));
        $this->roundRect($l, 900, 690, 1260, 910, 60, $this->color($l, $c['accent']));
        $this->roundRect($l, 1180, 740, 1290, 860, 22, $this->color($l, $c['accent']));
        $this->roundRect($l, 460, 760, 820, 840, 16, $this->color($l, '#ffffff', 25));
    }

    private function drawNotebook(GdImage $l, array $c): void
    {
        $this->roundRect($l, 520, 380, 1200, 1240, 28, $this->color($l, '#e2e8f0'));
        $this->roundRect($l, 500, 360, 1180, 1220, 28, $this->color($l, '#ffffff'));
        $this->roundRect($l, 480, 340, 1160, 1200, 28, $this->color($l, $c['main']));
        imagefilledrectangle($l, 480, 1040, 1160, 1060, $this->color($l, $c['accent']));
        $this->roundRect($l, 660, 470, 1060, 640, 18, $this->color($l, '#ffffff'));
        foreach ([520, 560, 600] as $y) {
            imagefilledrectangle($l, 700, $y, 1020, $y + 6, $this->color($l, '#cbd5e1'));
        }
        if ($c['spiral'] ?? true) {
            for ($y = 390; $y < 1170; $y += 58) {
                $this->ellipse($l, 486, $y, 78, 30, $this->color($l, '#94a3b8'));
                $this->ellipse($l, 486, $y - 3, 70, 22, $this->color($l, '#e2e8f0'));
                $this->circle($l, 530, $y, 9, $this->color($l, '#1f2937'));
            }
        } else {
            $this->roundRect($l, 480, 340, 560, 1200, 20, $this->color($l, $c['accent']));
        }
        if ($font = $this->font(true)) {
            imagettftext($l, 54, 0, 700, 1150, $this->color($l, '#ffffff'), $font, $c['label'] ?? 'A4');
        }
    }

    private function drawStapler(GdImage $l, array $c): void
    {
        $this->roundRect($l, 330, 960, 1250, 1060, 40, $this->color($l, '#1f2937'));
        $this->roundRect($l, 360, 930, 1230, 990, 20, $this->color($l, '#9ca3af'));
        $this->roundRect($l, 350, 760, 1270, 930, 70, $this->color($l, $c['main']));
        imagefilledrectangle($l, 420, 782, 1180, 800, $this->color($l, '#ffffff', 75));
        $this->circle($l, 420, 900, 70, $this->color($l, '#111827'));
        $this->circle($l, 420, 900, 26, $this->color($l, '#9ca3af'));
        $this->roundRect($l, 1180, 900, 1260, 960, 14, $this->color($l, '#6b7280'));
    }

    private function drawFolder(GdImage $l, array $c): void
    {
        $this->roundRect($l, 420, 340, 760, 460, 26, $this->color($l, $c['accent']));
        $this->roundRect($l, 420, 400, 1180, 1220, 30, $this->color($l, $c['accent']));
        $this->roundRect($l, 450, 470, 1150, 1180, 20, $this->color($l, '#ffffff'));
        foreach ([560, 620, 680, 740, 800] as $y) {
            imagefilledrectangle($l, 540, $y, 1080, $y + 8, $this->color($l, '#e2e8f0'));
        }
        imagefilledpolygon($l, [420, 600, 1180, 560, 1180, 1220, 420, 1220], $this->color($l, $c['main'], 15));
        $this->roundRect($l, 470, 640, 500, 1150, 12, $this->color($l, '#cbd5e1'));
        $this->roundRect($l, 820, 900, 1100, 1000, 16, $this->color($l, '#ffffff', 30));
    }

    private function drawSticky(GdImage $l, array $c): void
    {
        foreach (array_reverse(explode(',', $c['set'])) as $i => $color) {
            $sheet = $this->layer();
            imagefilledrectangle($sheet, 520, 520, 1080, 1080, $this->color($sheet, $color));
            imagefilledrectangle($sheet, 520, 520, 1080, 560, $this->color($sheet, '#000000', 110));
            imagefilledpolygon($sheet, [1080, 980, 1080, 1080, 980, 1080], $this->color($sheet, '#000000', 90));
            $sheet = imagerotate($sheet, [-10, 4, 14][$i] ?? 0, imagecolorallocatealpha($sheet, 0, 0, 0, 127));
            $this->copyAt($l, $sheet, -80 + $i * 80, -60 + $i * 60);
        }
    }

    private function drawEraser(GdImage $l, array $c): void
    {
        $this->roundRect($l, 420, 640, 1180, 960, 40, $this->color($l, '#ffffff'));
        $this->roundRect($l, 420, 640, 1180, 960, 40, $this->color($l, '#000000', 118));
        $this->roundRect($l, 700, 620, 1200, 980, 24, $this->color($l, $c['main']));
        imagefilledrectangle($l, 700, 760, 1200, 840, $this->color($l, $c['accent']));
        $this->roundRect($l, 430, 650, 690, 700, 20, $this->color($l, '#ffffff', 30));
    }

    private function drawRuler(GdImage $l, array $c): void
    {
        $this->roundRect($l, 140, 660, 1460, 940, 20, $this->color($l, $c['main'], 40));
        $this->roundRect($l, 140, 660, 1460, 700, 16, $this->color($l, '#ffffff', 60));
        $tick = $this->color($l, $c['accent']);
        for ($i = 0; $i <= 60; $i++) {
            $x = 190 + $i * 20.5;
            $len = $i % 10 === 0 ? 110 : ($i % 5 === 0 ? 75 : 45);
            imagefilledrectangle($l, (int) $x, 660, (int) $x + 4, 660 + $len, $tick);
        }
        if ($font = $this->font(false)) {
            foreach ([0, 10, 20, 30, 40, 50] as $i => $n) {
                imagettftext($l, 34, 0, 182 + $i * 205, 840, $tick, $font, (string) ($n / 2));
            }
        }
    }

    private function drawScissors(GdImage $l, array $c): void
    {
        $steel = $this->color($l, '#94a3b8');
        imagefilledpolygon($l, [760, 760, 1400, 520, 1420, 560, 820, 830], $steel);
        imagefilledpolygon($l, [760, 840, 1400, 1080, 1420, 1040, 820, 770], $this->color($l, '#cbd5e1'));
        foreach ([[460, 600], [460, 1000]] as [$x, $y]) {
            imagefilledpolygon($l, [$x + 80, $y - 40, 820, 780, 820, 820, $x + 80, $y + 40], $this->color($l, $c['main']));
            $this->ellipse($l, $x, $y, 300, 220, $this->color($l, $c['main']));
            $this->ellipse($l, $x, $y, 190, 120, imagecolorallocatealpha($l, 0, 0, 0, 127), false);
        }
        $this->circle($l, 800, 800, 40, $this->color($l, '#475569'));
        $this->circle($l, 800, 800, 16, $this->color($l, '#e2e8f0'));
    }

    private function drawGlue(GdImage $l, array $c): void
    {
        $this->roundRect($l, 650, 560, 950, 1240, 50, $this->color($l, '#f8fafc'));
        $this->roundRect($l, 650, 560, 950, 1240, 50, $this->color($l, '#000000', 118));
        $this->roundRect($l, 640, 360, 960, 600, 46, $this->color($l, $c['main']));
        imagefilledrectangle($l, 670, 380, 700, 580, $this->color($l, '#ffffff', 80));
        imagefilledrectangle($l, 652, 760, 948, 1020, $this->color($l, $c['accent']));
        imagefilledrectangle($l, 652, 820, 948, 960, $this->color($l, '#ffffff'));
        if ($font = $this->font(true)) {
            imagettftext($l, 52, 0, 700, 915, $this->color($l, $c['accent']), $font, $c['label'] ?? '21 gr');
        }
        $this->roundRect($l, 660, 1180, 940, 1250, 26, $this->color($l, $c['main']));
    }

    private function drawSharpener(GdImage $l, array $c): void
    {
        $this->roundRect($l, 520, 600, 1080, 1020, 60, $this->color($l, $c['main']));
        imagefilledrectangle($l, 560, 620, 1040, 650, $this->color($l, '#ffffff', 85));
        $this->roundRect($l, 820, 640, 1060, 800, 14, $this->color($l, '#cbd5e1'));
        $this->circle($l, 940, 720, 18, $this->color($l, '#64748b'));
        $this->circle($l, 700, 820, 95, $this->color($l, $c['accent']));
        $this->circle($l, 700, 820, 62, $this->color($l, '#0f172a'));
    }

    private function drawPaint(GdImage $l, array $c): void
    {
        $this->roundRect($l, 260, 540, 1340, 1080, 50, $this->color($l, '#1f2937'));
        $this->roundRect($l, 290, 570, 1310, 1050, 36, $this->color($l, '#f8fafc'));
        foreach (explode(',', $c['set']) as $i => $color) {
            $x = 400 + ($i % 6) * 160;
            $y = $i < 6 ? 700 : 920;
            $this->roundRect($l, $x - 62, $y - 70, $x + 62, $y + 70, 22, $this->color($l, '#e2e8f0'));
            $this->roundRect($l, $x - 52, $y - 60, $x + 52, $y + 60, 18, $this->color($l, $color));
            $this->ellipse($l, $x - 18, $y - 28, 40, 22, $this->color($l, '#ffffff', 80));
        }
    }

    private function drawPaper(GdImage $l, array $c): void
    {
        foreach ([0, 1, 2] as $i) {
            $this->roundRect($l, 380 + $i * 8, 520 + $i * 8, 1220 + $i * 8, 1120 + $i * 8, 16, $this->color($l, $i === 2 ? '#ffffff' : '#e2e8f0'));
        }
        imagefilledrectangle($l, 396, 760, 1236, 920, $this->color($l, $c['main']));
        $this->roundRect($l, 560, 600, 1060, 720, 14, $this->color($l, '#f1f5f9'));
        if ($font = $this->font(true)) {
            imagettftext($l, 90, 0, 740, 880, $this->color($l, '#ffffff'), $font, 'A4');
            imagettftext($l, 40, 0, 640, 682, $this->color($l, $c['accent']), $font, '500 yaprak · 80 g');
        }
    }

    /* ---------------- Yardımcılar ---------------- */

    private function pencil(GdImage $l, int $cx, int $cy, float $s, string $main, string $stripe): void
    {
        $p = fn (array $pts) => array_map(fn ($v, $i) => (int) round($i % 2 ? $cy + ($v - 800) * $s : $cx + ($v - 800) * $s), $pts, array_keys($pts));
        imagefilledpolygon($l, $p([300, 748, 1150, 748, 1150, 852, 300, 852]), $this->color($l, $main));
        imagefilledpolygon($l, $p([300, 784, 1150, 784, 1150, 816, 300, 816]), $this->color($l, $stripe, 50));
        imagefilledpolygon($l, $p([1150, 748, 1150, 852, 1330, 800]), $this->color($l, '#e7c9a0'));
        imagefilledpolygon($l, $p([1270, 783, 1270, 817, 1330, 800]), $this->color($l, '#1f2937'));
        imagefilledpolygon($l, $p([235, 744, 300, 744, 300, 856, 235, 856]), $this->color($l, '#cbd5e1'));
        foreach ([250, 270] as $x) {
            imagefilledpolygon($l, $p([$x, 744, $x + 6, 744, $x + 6, 856, $x, 856]), $this->color($l, '#94a3b8'));
        }
        imagefilledpolygon($l, $p([170, 750, 235, 750, 235, 850, 170, 850]), $this->color($l, '#f472b6'));
    }

    private function canvas(string $bg): GdImage
    {
        $img = imagecreatetruecolor(self::WORK, self::WORK);
        [$r, $g, $b] = $this->rgb($bg);
        for ($y = 0; $y < self::WORK; $y++) {
            $t = $y / self::WORK;
            $mix = fn ($v) => (int) round($v + (255 - $v) * (0.65 * $t));
            imageline($img, 0, $y, self::WORK, $y, imagecolorallocate($img, $mix($r), $mix($g), $mix($b)));
        }
        imagealphablending($img, true);

        return $img;
    }

    private function layer(): GdImage
    {
        $l = imagecreatetruecolor(self::WORK, self::WORK);
        imagealphablending($l, false);
        imagefill($l, 0, 0, imagecolorallocatealpha($l, 0, 0, 0, 127));
        imagesavealpha($l, true);
        imagealphablending($l, true);

        return $l;
    }

    private function paste(GdImage $img, GdImage $layer): void
    {
        $x = (int) ((imagesx($layer) - self::WORK) / 2);
        $y = (int) ((imagesy($layer) - self::WORK) / 2);
        imagecopy($img, $layer, 0, 0, $x, $y, self::WORK, self::WORK);
    }

    /** Aynı boyuttaki katmanı kaydırarak kopyalar (negatif kaydırma desteklenir). */
    private function copyAt(GdImage $dst, GdImage $src, int $dx, int $dy): void
    {
        imagecopy($dst, $src, max(0, $dx), max(0, $dy), max(0, -$dx), max(0, -$dy), self::WORK - abs($dx), self::WORK - abs($dy));
    }

    private function shadow(GdImage $img, int $width): void
    {
        for ($i = 0; $i < 12; $i++) {
            imagefilledellipse($img, 800, 1330, $width * 2 - $i * 40, 110 - $i * 7, imagecolorallocatealpha($img, 15, 23, 42, 125 - intdiv($i, 2)));
        }
    }

    private function roundRect(GdImage $img, int $x1, int $y1, int $x2, int $y2, int $r, int $color): void
    {
        $r = min($r, (int) (($x2 - $x1) / 2), (int) (($y2 - $y1) / 2));
        imagefilledrectangle($img, $x1 + $r, $y1, $x2 - $r, $y2, $color);
        imagefilledrectangle($img, $x1, $y1 + $r, $x1 + $r - 1, $y2 - $r, $color);
        imagefilledrectangle($img, $x2 - $r + 1, $y1 + $r, $x2, $y2 - $r, $color);
        foreach ([[$x1 + $r, $y1 + $r, 180], [$x2 - $r, $y1 + $r, 270], [$x1 + $r, $y2 - $r, 90], [$x2 - $r, $y2 - $r, 0]] as [$cx, $cy, $start]) {
            imagefilledarc($img, $cx, $cy, $r * 2, $r * 2, $start, $start + 90, $color, IMG_ARC_PIE);
        }
    }

    private function circle(GdImage $img, int $x, int $y, int $r, int $color, bool $blend = true): void
    {
        $this->ellipse($img, $x, $y, $r * 2, $r * 2, $color, $blend);
    }

    private function ellipse(GdImage $img, int $x, int $y, int $w, int $h, int $color, bool $blend = true): void
    {
        imagealphablending($img, $blend);
        imagefilledellipse($img, $x, $y, $w, $h, $color);
        imagealphablending($img, true);
    }

    /** @param  int  $alpha  0 (opak) – 127 (saydam) */
    private function color(GdImage $img, string $hex, int $alpha = 0): int
    {
        [$r, $g, $b] = $this->rgb($hex);

        return imagecolorallocatealpha($img, $r, $g, $b, $alpha);
    }

    /** @return array{int, int, int} */
    private function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    private function font(bool $bold): ?string
    {
        $candidates = $bold
            ? ['C:/Windows/Fonts/segoeuib.ttf', 'C:/Windows/Fonts/arialbd.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf']
            : ['C:/Windows/Fonts/segoeui.ttf', 'C:/Windows/Fonts/arial.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'];

        return collect($candidates)->first(fn ($f) => is_file($f));
    }

    private function toUpload(GdImage $img, string $name, bool $downscale = true): UploadedFile
    {
        if ($downscale) {
            $out = imagecreatetruecolor(self::OUT, self::OUT);
            imagecopyresampled($out, $img, 0, 0, 0, 0, self::OUT, self::OUT, self::WORK, self::WORK);
            $img = $out;
        }

        $path = tempnam(sys_get_temp_dir(), 'demo').'.png';
        imagepng($img, $path);

        return new UploadedFile($path, $name, 'image/png', null, true);
    }
}
