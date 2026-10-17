<?php

namespace App\Domain\Branding;

use GdImage;

/**
 * Yapboz logosunun PNG hâli (e-postalar ve varsayılan işletme logosu için).
 * Giriş sayfalarındaki SVG logoyla aynı geometri: dört parça, klasik kulakçık/yuva kesimi.
 * Kabartma ışığı bulanıklaştırılmış maskenin eğiminden, gölge küçültülüp büyütülen maskeden üretilir.
 */
final class PuzzleLogo
{
    private const KNOB = [
        [[0.40, 0.00], [0.43, 0.03], [0.40, 0.09]],
        [[0.36, 0.19], [0.43, 0.27], [0.50, 0.27]],
        [[0.57, 0.27], [0.64, 0.19], [0.60, 0.09]],
        [[0.57, 0.03], [0.60, 0.00], [0.65, 0.00]],
    ];

    /** [sol üst köşe, kenar tipleri üst/sağ/alt/sol, açık renk, koyu renk] */
    private const PIECES = [
        [[0, 0], [0, 1, -1, 0], '#fdba74', '#ea580c'],
        [[100, 0], [0, 0, 1, -1], '#7dd3fc', '#0284c7'],
        [[0, 100], [1, -1, 0, 0], '#86efac', '#16a34a'],
        [[100, 100], [-1, 0, 0, 1], '#fde68a', '#eab308'],
    ];

    /** Yalnız simge, kare, saydam arka plan. */
    public static function icon(int $size = 256): GdImage
    {
        $scale = 3;
        $work = $size * $scale;
        $canvas = self::transparent($work, $work);
        // Mantıksal alan -24..224 (kulakçıklar ve gölge için pay) çalışma tuvaline eşlenir.
        $map = fn (float $v) => ($v + 24) * $work / 248;
        $polygons = array_map(fn ($p) => array_map($map, self::polygon($p)), self::PIECES);

        self::shadow($canvas, $polygons, $work);
        foreach (self::PIECES as $i => $piece) {
            self::piece($canvas, $polygons[$i], $piece[2], $piece[3], $work);
        }

        $out = self::transparent($size, $size);
        imagecopyresampled($out, $canvas, 0, 0, 0, 0, $size, $size, $work, $work);

        return $out;
    }

    /** Simge + işletme adı + alt satır; geniş logo (e-posta başlığı ve işletme logosu). */
    public static function withWordmark(string $name, string $tagline): GdImage
    {
        $h = 240;
        $tagline = self::upperTr($tagline);
        $bold = self::font(true);
        $regular = self::font(false) ?? $bold;
        $textWidth = 0;
        if ($bold) {
            $box = imagettfbbox(78, 0, $bold, $name);
            $sub = imagettfbbox(30, 0, $regular, $tagline);
            $textWidth = max($box[2] - $box[0], $sub[2] - $sub[0] + 40);
        }

        $img = self::transparent($h + 40 + $textWidth + 30, $h);
        imagealphablending($img, true);
        imagecopy($img, self::icon($h), 0, 0, 0, 0, $h, $h);

        if ($bold) {
            $x = $h + 34;
            imagettftext($img, 78, 0, $x, 128, self::color($img, '#172554'), $bold, $name);
            imagettftext($img, 30, 0, $x + 4, 190, self::color($img, '#ea580c'), $regular, $tagline);
        }

        return $img;
    }

    public static function png(GdImage $image): string
    {
        ob_start();
        imagepng($image, null, 9);

        return (string) ob_get_clean();
    }

    /* ---------------- Çizim ---------------- */

    private static function piece(GdImage $canvas, array $points, string $light, string $dark, int $work): void
    {
        [$x0, $y0, $x1, $y1] = self::bounds($points, $work, 12);
        $w = $x1 - $x0;
        $h = $y1 - $y0;
        $local = self::shift($points, -$x0, -$y0);

        $mask = imagecreatetruecolor($w, $h);
        imagefilledpolygon($mask, $local, imagecolorallocate($mask, 255, 255, 255));
        $bump = self::blurred($mask, 7);

        [$lr, $lg, $lb] = self::rgb($light);
        [$dr, $dg, $db] = self::rgb($dark);
        mt_srand(7);

        for ($y = 2; $y < $h - 2; $y++) {
            for ($x = 2; $x < $w - 2; $x++) {
                if ((imagecolorat($mask, $x, $y) & 0xFF) < 128) {
                    continue;
                }
                // Kabartma: bulanık maskenin eğimi yüzey normali gibi kullanılır; ışık sol üstten.
                $gx = (imagecolorat($bump, $x + 2, $y) & 0xFF) - (imagecolorat($bump, $x - 2, $y) & 0xFF);
                $gy = (imagecolorat($bump, $x, $y + 2) & 0xFF) - (imagecolorat($bump, $x, $y - 2) & 0xFF);
                $shade = max(-0.55, min(0.55, ($gx + $gy) / 150));
                $t = (($x / $w) + ($y / $h)) / 2;
                $grain = mt_rand(-7, 7);

                $mix = fn ($a, $b) => $a + ($b - $a) * $t;
                $tone = fn ($c) => (int) max(0, min(255, $shade >= 0 ? $c + (255 - $c) * $shade * 0.8 + $grain : $c * (1 + $shade) + $grain));
                imagesetpixel($canvas, $x0 + $x, $y0 + $y, imagecolorallocatealpha($canvas, $tone($mix($lr, $dr)), $tone($mix($lg, $dg)), $tone($mix($lb, $db)), 0));
            }
        }

        // Kesim çizgisi
        imagesetthickness($canvas, max(1, (int) round($work / 300)));
        imagepolygon($canvas, $points, imagecolorallocatealpha($canvas, 15, 23, 42, 95));
        imagesetthickness($canvas, 1);
    }

    private static function shadow(GdImage $canvas, array $polygons, int $work): void
    {
        $mask = imagecreatetruecolor($work, $work);
        $white = imagecolorallocate($mask, 255, 255, 255);
        $offset = (int) round($work * 0.022);
        foreach ($polygons as $points) {
            imagefilledpolygon($mask, self::shift($points, $offset * 0.5, $offset), $white);
        }
        $soft = self::blurred($mask, 18);

        for ($y = 0; $y < $work; $y += 1) {
            for ($x = 0; $x < $work; $x += 1) {
                $v = imagecolorat($soft, $x, $y) & 0xFF;
                if ($v > 4) {
                    imagesetpixel($canvas, $x, $y, imagecolorallocatealpha($canvas, 15, 23, 42, (int) (127 - $v / 255 * 60)));
                }
            }
        }
    }

    /** Küçültüp büyüterek yumuşatma (GD'nin 3x3 bulanıklığından çok daha geniş ve hızlı). */
    private static function blurred(GdImage $mask, int $factor): GdImage
    {
        $w = imagesx($mask);
        $h = imagesy($mask);
        $small = imagecreatetruecolor(max(1, intdiv($w, $factor)), max(1, intdiv($h, $factor)));
        imagecopyresampled($small, $mask, 0, 0, 0, 0, imagesx($small), imagesy($small), $w, $h);
        $big = imagecreatetruecolor($w, $h);
        imagecopyresampled($big, $small, 0, 0, 0, 0, $w, $h, imagesx($small), imagesy($small));

        return $big;
    }

    /** Parça sınırını nokta listesine çevirir (mantıksal birim, saat yönünde). */
    private static function polygon(array $piece): array
    {
        [[$x, $y], [$top, $right, $bottom, $left]] = $piece;
        $corners = [[$x, $y], [$x + 100, $y], [$x + 100, $y + 100], [$x, $y + 100]];
        $types = [$top, $right, $bottom, $left];
        $points = [];

        foreach ($types as $i => $type) {
            $from = $corners[$i];
            $to = $corners[($i + 1) % 4];
            array_push($points, ...$from);
            if ($type !== 0) {
                array_push($points, ...self::knob($from, $to, $type));
            }
        }

        return $points;
    }

    private static function knob(array $from, array $to, int $type): array
    {
        [$x1, $y1] = $from;
        [$dx, $dy] = [($to[0] - $x1) / 100, ($to[1] - $y1) / 100];
        [$nx, $ny] = [$dy, -$dx];
        $pt = fn ($u, $v) => [$x1 + $u * 100 * $dx + $v * 100 * $type * $nx, $y1 + $u * 100 * $dy + $v * 100 * $type * $ny];

        $current = $pt(0.35, 0);
        $points = $current;
        foreach (self::KNOB as [$c1, $c2, $end]) {
            [$p1, $p2, $p3] = [$pt(...$c1), $pt(...$c2), $pt(...$end)];
            for ($s = 1; $s <= 14; $s++) {
                $t = $s / 14;
                $u = 1 - $t;
                $points[] = $u ** 3 * $current[0] + 3 * $u ** 2 * $t * $p1[0] + 3 * $u * $t ** 2 * $p2[0] + $t ** 3 * $p3[0];
                $points[] = $u ** 3 * $current[1] + 3 * $u ** 2 * $t * $p1[1] + 3 * $u * $t ** 2 * $p2[1] + $t ** 3 * $p3[1];
            }
            $current = $p3;
        }

        return $points;
    }

    private static function bounds(array $points, int $max, int $pad): array
    {
        $xs = array_values(array_filter($points, fn ($k) => $k % 2 === 0, ARRAY_FILTER_USE_KEY));
        $ys = array_values(array_filter($points, fn ($k) => $k % 2 === 1, ARRAY_FILTER_USE_KEY));

        return [max(0, (int) min($xs) - $pad), max(0, (int) min($ys) - $pad), min($max, (int) max($xs) + $pad), min($max, (int) max($ys) + $pad)];
    }

    private static function shift(array $points, float $dx, float $dy): array
    {
        return array_map(fn ($v, $i) => $v + ($i % 2 ? $dy : $dx), $points, array_keys($points));
    }

    private static function transparent(int $w, int $h): GdImage
    {
        $img = imagecreatetruecolor($w, $h);
        imagealphablending($img, false);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
        imagesavealpha($img, true);
        imagealphablending($img, true);

        return $img;
    }

    private static function color(GdImage $img, string $hex): int
    {
        return imagecolorallocate($img, ...self::rgb($hex));
    }

    /** @return array{int, int, int} */
    private static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    /** Türkçe büyük harf (i → İ, ı → I). */
    private static function upperTr(string $text): string
    {
        return mb_strtoupper(str_replace(['i', 'ı'], ['İ', 'I'], $text), 'UTF-8');
    }

    private static function font(bool $bold): ?string
    {
        $candidates = $bold
            ? ['C:/Windows/Fonts/segoeuib.ttf', 'C:/Windows/Fonts/arialbd.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf', '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf']
            : ['C:/Windows/Fonts/segoeui.ttf', 'C:/Windows/Fonts/arial.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf', '/usr/share/fonts/dejavu/DejaVuSans.ttf'];

        foreach ($candidates as $file) {
            if (is_file($file)) {
                return $file;
            }
        }

        return null;
    }
}
