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
    public function store(UploadedFile $file, string $directory, int $maxEdge = 1600): string
    {
        if (! $file->isValid() || $file->getSize() > self::MAX_BYTES) {
            throw new DomainException('Görsel en fazla 5 MB olabilir.');
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

        $scale = min(1, $maxEdge / max($width, $height));
        $target = imagecreatetruecolor(max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagecopyresampled($target, $source, 0, 0, 0, 0, imagesx($target), imagesy($target), $width, $height);

        ob_start();
        imagewebp($target, null, 82);
        $binary = (string) ob_get_clean();

        $path = trim($directory, '/').'/'.Str::random(40).'.webp';
        Storage::disk('local')->put($path, $binary);

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('local')->delete($path);
        }
    }
}
