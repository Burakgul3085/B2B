<?php

namespace App\Domain\Files;

use GdImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Görsellerin küçük boyutları (mağaza ve B2B ortak). Listelerde 1600–2000 px'lik asıl görsel yerine uygun genişlik
 * sunulur. Ürün görselleri yüklenince kuyrukta hazırlanır (App\Jobs\GenerateImageVariants); hazır değilse ilk istekte
 * üretilir. Dosya …/w{genişlik}/ad.webp olarak yazılır ve önce geçici adla yazılıp yerine taşınır: aynı anda gelen
 * istekler yarım dosya okumaz.
 */
class ImageVariants
{
    /** İzin verilen genişlikler; başka değer istenirse en yakın büyüğü kullanılır. 2000: asıl görsel. */
    public const WIDTHS = [320, 640, 1000, 2000];

    /** Yüklemede önceden hazırlanan boyutlar. */
    public const PREPARED = [320, 640, 1000];

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

        $target = $this->target($source, $width);
        if ($disk->exists($target)) {
            return $target;
        }

        return $this->make($source, $width, @imagecreatefromstring($disk->get($source))) ?? $source;
    }

    /** Yüklemede kullanılan boyutları hazırlar (eksik olanları); kaynak bir kez okunur. */
    public function prepare(string $source): void
    {
        $disk = Storage::disk('local');
        $missing = array_filter(self::PREPARED, fn ($w) => ! $disk->exists($this->target($source, $w)));
        if (! $missing || ! $disk->exists($source)) {
            return;
        }

        $image = @imagecreatefromstring($disk->get($source));
        foreach ($missing as $width) {
            $this->make($source, $width, $image);
        }
    }

    /** Hazırlanacak boyutların hepsi var mı? Asıl görsel zaten o genişlikten darsa küçük boyut gerekmez. */
    public function isPrepared(string $source): bool
    {
        $disk = Storage::disk('local');
        $missing = array_filter(self::PREPARED, fn ($w) => ! $disk->exists($this->target($source, $w)));
        if (! $missing) {
            return true;
        }
        $width = $disk->exists($source) ? (int) (@getimagesize($disk->path($source))[0] ?? 0) : 0;

        return $width === 0 || $width <= min($missing);
    }

    /** Görsel silindiğinde küçük boyutlarını da siler. */
    public function forget(string $source): void
    {
        foreach (self::WIDTHS as $width) {
            Storage::disk('local')->delete($this->target($source, $width));
        }
    }

    /** Küçük boyutu üretip yazar; küçültmeye gerek yoksa veya görsel okunamazsa null (asıl görsel sunulur). */
    private function make(string $source, int $width, mixed $image): ?string
    {
        if (! $image instanceof GdImage || imagesx($image) <= $width) {
            return null;
        }

        $resized = imagescale($image, $width, -1, IMG_BICUBIC);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        ob_start();
        imagewebp($resized, null, 85);
        $binary = (string) ob_get_clean();

        $disk = Storage::disk('local');
        $target = $this->target($source, $width);
        $temp = $target.'.'.Str::random(8).'.tmp';
        $disk->put($temp, $binary);
        $disk->move($temp, $target);

        return $target;
    }

    private function target(string $source, int $width): string
    {
        return dirname($source).'/w'.$width.'/'.basename($source);
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
