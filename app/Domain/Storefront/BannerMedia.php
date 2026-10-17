<?php

namespace App\Domain\Storefront;

use App\Domain\Files\ImageUploader;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Banner medyası. Görseller yeniden kodlanır (WEBP, kenar en fazla 2560 px, yüksek kalite);
 * videolar dosyanın kendisinden doğrulanır (MP4/WEBM imzası) ve olduğu gibi, rastgele adla saklanır.
 * Dosyalar web kökü dışındadır; yalnızca yetki kontrollü controller sunar.
 */
class BannerMedia
{
    public const MAX_VIDEO_BYTES = 50 * 1024 * 1024;

    public const MAX_IMAGE_BYTES = 15 * 1024 * 1024;

    public const VIDEO_TYPES = ['video/mp4' => 'mp4', 'video/webm' => 'webm'];

    public function __construct(private ImageUploader $images) {}

    /** @return array{type: 'image'|'video', path: string, mime: string, size: int} */
    public function store(UploadedFile $file, string $directory): array
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());

        if (str_starts_with((string) $mime, 'video/') || $mime === 'application/octet-stream') {
            return $this->storeVideo($file, $directory);
        }

        $path = $this->images->store($file, $directory, 2560, self::MAX_IMAGE_BYTES, 90);

        return ['type' => 'image', 'path' => $path, 'mime' => 'image/webp', 'size' => (int) Storage::disk('local')->size($path)];
    }

    /** Mobil görsel ve video kapağı: yalnızca görsel. */
    public function storeImage(UploadedFile $file, string $directory): string
    {
        return $this->images->store($file, $directory, 1600, self::MAX_IMAGE_BYTES, 88);
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('local')->delete($path);
        }
    }

    /** @return array{type: 'video', path: string, mime: string, size: int} */
    private function storeVideo(UploadedFile $file, string $directory): array
    {
        if (! $file->isValid() || $file->getSize() === 0) {
            throw new DomainException('Video yüklenemedi; dosya bozuk olabilir.');
        }
        if ($file->getSize() > self::MAX_VIDEO_BYTES) {
            throw new DomainException('Video en fazla 50 MB olabilir. Daha kısa bir video seçin veya sıkıştırın.');
        }

        $head = (string) file_get_contents($file->getRealPath(), false, null, 0, 16);
        $mime = match (true) {
            substr($head, 4, 4) === 'ftyp' => 'video/mp4',
            str_starts_with($head, "\x1A\x45\xDF\xA3") => 'video/webm',
            default => null,
        };
        if (! $mime) {
            throw new DomainException('Yalnızca MP4 veya WEBM video yüklenebilir.');
        }

        $path = trim($directory, '/').'/'.Str::random(40).'.'.self::VIDEO_TYPES[$mime];
        Storage::disk('local')->putFileAs(dirname($path), $file, basename($path));

        return ['type' => 'video', 'path' => $path, 'mime' => $mime, 'size' => (int) $file->getSize()];
    }
}
