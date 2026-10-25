<?php

namespace App\Domain\Store\Storefront;

use GdImage;
use Illuminate\Support\Facades\Storage;

/**
 * Mağaza görsellerinin küçük boyutları. Listelerde 2000 px'lik asıl görsel yerine uygun genişlik sunulur.
 * İlk istekte üretilir ve diske yazılır (…/w{genişlik}/dosya.webp); sonraki isteklerde doğrudan okunur.
 */
class ImageVariants
{
    /** İzin verilen genişlikler; başka değer istenirse en yakın büyüğü kullanılır. */
    public const WIDTHS = [320, 640, 1000, 2000];

    /**
     * Kayıtlı görselin en/boy oranı (genişlik / yükseklik); okunamazsa null. Vitrin, görseli kırpmadan
     * yerleştirmek için çerçeveyi bu orana göre seçer (x-store.fit-image). Yalnızca dosya başlığı okunur.
     */
    public static function ratio(?string $path): ?float
    {
        if (! $path || ! Storage::disk('local')->exists($path)) {
            return null;
        }
        $info = @getimagesize(Storage::disk('local')->path($path));

        return $info && $info[1] > 0 ? round($info[0] / $info[1], 4) : null;
    }

    /** Sunulacak dosyanın yolu (yoksa üretir). Kaynak yoksa null. */
    public function path(string $source, ?int $width): ?string
    {
        $disk = Storage::disk('local');
        if (! $disk->exists($source)) {
            return null;
        }

        $width = $this->normalize($width);
        if ($width === null) {
            return $source;
        }

        $target = dirname($source).'/w'.$width.'/'.basename($source);
        if ($disk->exists($target)) {
            return $target;
        }

        $image = @imagecreatefromstring($disk->get($source));
        if (! $image instanceof GdImage) {
            return $source;
        }
        if (imagesx($image) <= $width) {
            return $source; // küçültmeye gerek yok
        }

        $resized = imagescale($image, $width, -1, IMG_BICUBIC);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        ob_start();
        imagewebp($resized, null, 85);
        $disk->put($target, (string) ob_get_clean());

        return $target;
    }

    /** Görsel silindiğinde küçük boyutlarını da siler. */
    public function forget(string $source): void
    {
        foreach (self::WIDTHS as $width) {
            Storage::disk('local')->delete(dirname($source).'/w'.$width.'/'.basename($source));
        }
    }

    private function normalize(?int $width): ?int
    {
        if ($width === null || $width <= 0) {
            return null;
        }
        foreach (self::WIDTHS as $allowed) {
            if ($width <= $allowed) {
                return $allowed === 2000 ? null : $allowed;
            }
        }

        return null;
    }
}
