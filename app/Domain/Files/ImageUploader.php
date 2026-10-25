<?php

namespace App\Domain\Files;

use DomainException;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Güvenli görsel yükleme:
 *  - içerik türü dosya uzantısından değil, dosyanın kendisinden (fileinfo + getimagesize) belirlenir,
 *  - görsel GD ile yeniden kodlanır (EXIF/meta veri ve gömülü içerik atılır),
 *  - rastgele adla, web kökü dışındaki private diske kaydedilir; yalnızca yetkili controller sunar.
 */
class ImageUploader
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    public const MAX_PIXELS = 40_000_000; // bellek tüketimi saldırılarına karşı

    private const ALLOWED = ['image/jpeg', 'image/png', 'image/webp'];

    /** @return string private disk üzerindeki yol */
    public function store(UploadedFile $file, string $directory, int $maxEdge = 1600, int $maxBytes = self::MAX_BYTES, int $quality = 82, bool $trimWhitespace = false, bool $sharpen = false): string
    {
        if (! $file->isValid() || $file->getSize() > $maxBytes) {
            throw new DomainException('Görsel en fazla '.intdiv($maxBytes, 1024 * 1024).' MB olabilir.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
        $info = @getimagesize($file->getRealPath());

        if (! in_array($mime, self::ALLOWED, true) || $info === false || ! in_array($info['mime'], self::ALLOWED, true)) {
            throw new DomainException('Yalnızca JPG, PNG veya WEBP görsel yüklenebilir.');
        }

        [$width, $height] = $info;
        if ($width < 1 || $height < 1 || $width * $height > self::MAX_PIXELS) {
            throw new DomainException('Görsel boyutları desteklenmiyor.');
        }

        $source = match ($info['mime']) {
            'image/jpeg' => @imagecreatefromjpeg($file->getRealPath()),
            'image/png' => @imagecreatefrompng($file->getRealPath()),
            'image/webp' => @imagecreatefromwebp($file->getRealPath()),
        };

        if (! $source instanceof GdImage) {
            throw new DomainException('Görsel okunamadı; dosya bozuk olabilir.');
        }

        if ($info['mime'] === 'image/jpeg') {
            $source = $this->applyExifOrientation($source, $file->getRealPath());
            [$width, $height] = [imagesx($source), imagesy($source)];
        }

        if ($trimWhitespace && ($box = $this->contentBox($source)) !== null) {
            $source = imagecrop($source, $box) ?: $source;
            [$width, $height] = [imagesx($source), imagesy($source)];
        }

        $scale = min(1, $maxEdge / max($width, $height));
        $target = imagecreatetruecolor(max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagecopyresampled($target, $source, 0, 0, 0, 0, imagesx($target), imagesy($target), $width, $height);

        // Küçültme dokuyu (deri, kumaş, baskı) bir miktar yumuşatır; hafif netleştirme bunu geri kazandırır.
        if ($sharpen && $scale < 0.9) {
            imageconvolution($target, [[0, -0.5, 0], [-0.5, 3, -0.5], [0, -0.5, 0]], 1, 0);
        }

        ob_start();
        imagewebp($target, null, $quality);
        $binary = (string) ob_get_clean();

        $path = trim($directory, '/').'/'.Str::random(40).'.webp';
        Storage::disk('local')->put($path, $binary);

        return $path;
    }

    /**
     * Telefon fotoğrafları pikselleri sensör yönünde saklar ve doğru yönü EXIF "Orientation" etiketiyle bildirir.
     * Yeniden kodlamada EXIF atıldığı için yön burada piksellere uygulanır; yoksa fotoğraf yan veya ters görünür.
     */
    private function applyExifOrientation(GdImage $image, string $path): GdImage
    {
        $orientation = function_exists('exif_read_data') ? (int) (@exif_read_data($path)['Orientation'] ?? 1) : 1;

        // imagerotate saat yönünün tersine döndürür.
        $rotated = match ($orientation) {
            2 => imageflip($image, IMG_FLIP_HORIZONTAL) ? $image : $image,
            3 => imagerotate($image, 180, 0),
            4 => imageflip($image, IMG_FLIP_VERTICAL) ? $image : $image,
            5 => imageflip($image, IMG_FLIP_HORIZONTAL) ? imagerotate($image, 90, 0) : $image,
            6 => imagerotate($image, -90, 0),
            7 => imageflip($image, IMG_FLIP_HORIZONTAL) ? imagerotate($image, -90, 0) : $image,
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        return $rotated instanceof GdImage ? $rotated : $image;
    }

    /**
     * Ürünün çevresindeki beyaz (veya saydam) boşluğu bulur; kırpılacak alanı döndürür, gerek yoksa null.
     * Yalnızca neredeyse saf beyaz piksel boşluk sayılır: açık renkli ürünler ve gölgeler korunur.
     * Hız için tarama küçültülmüş kopyada yapılır, sonuç asıl boyuta ölçeklenir ve küçük bir pay bırakılır.
     *
     * @return array{x: int, y: int, width: int, height: int}|null
     */
    private function contentBox(GdImage $image): ?array
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $ratio = min(1, 400 / max($width, $height));
        $w = max(1, (int) round($width * $ratio));
        $h = max(1, (int) round($height * $ratio));

        $probe = imagecreatetruecolor($w, $h);
        imagealphablending($probe, false);
        imagesavealpha($probe, true);
        imagecopyresampled($probe, $image, 0, 0, 0, 0, $w, $h, $width, $height);

        $rows = array_fill(0, $h, 0);
        $cols = array_fill(0, $w, 0);
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $c = imagecolorat($probe, $x, $y);
                $transparent = (($c >> 24) & 0x7F) > 100;
                $white = (($c >> 16) & 0xFF) >= 243 && (($c >> 8) & 0xFF) >= 243 && ($c & 0xFF) >= 243;
                if (! $transparent && ! $white) {
                    $rows[$y]++;
                    $cols[$x]++;
                }
            }
        }

        // Tek tük JPEG gürültüsü içerik sayılmaz.
        $isContent = fn (int $count, int $length) => $count >= max(2, $length * 0.005);
        $top = $this->firstIndex($rows, fn ($n) => $isContent($n, $w));
        if ($top === null) {
            return null; // tamamen beyaz görsel
        }
        $bottom = $this->firstIndex(array_reverse($rows), fn ($n) => $isContent($n, $w));
        $left = $this->firstIndex($cols, fn ($n) => $isContent($n, $h));
        $right = $this->firstIndex(array_reverse($cols), fn ($n) => $isContent($n, $h));

        // Asıl boyuta ölçekle; kenara yapışmasın diye içerik boyutunun %3'ü kadar pay bırak.
        $x1 = $left / $ratio;
        $y1 = $top / $ratio;
        $x2 = ($w - $right) / $ratio;
        $y2 = ($h - $bottom) / $ratio;
        $pad = 0.03 * max($x2 - $x1, $y2 - $y1);
        $x1 = (int) max(0, floor($x1 - $pad));
        $y1 = (int) max(0, floor($y1 - $pad));
        $x2 = (int) min($width, ceil($x2 + $pad));
        $y2 = (int) min($height, ceil($y2 + $pad));

        // Kazanç önemsizse dokunma.
        if (($x2 - $x1) * ($y2 - $y1) > 0.92 * $width * $height) {
            return null;
        }

        return ['x' => $x1, 'y' => $y1, 'width' => $x2 - $x1, 'height' => $y2 - $y1];
    }

    /** @param  list<int>  $counts */
    private function firstIndex(array $counts, callable $matches): ?int
    {
        foreach ($counts as $i => $count) {
            if ($matches($count)) {
                return $i;
            }
        }

        return null;
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('local')->delete($path);
        }
    }
}
