<?php

namespace App\Domain\Files;

use App\Models\Attachment;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Belge yükleme (başvuru belgeleri, mesaj ekleri). İçerik türü dosyanın kendisinden belirlenir;
 * dosya rastgele adla web kökü dışında saklanır ve yalnızca yetki kontrollü controller'dan indirilir.
 */
class DocumentUploader
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    public const ALLOWED = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    public function store(UploadedFile $file, string $directory, string $attachableType, int $attachableId, ?string $uploaderType = null, ?int $uploaderId = null): Attachment
    {
        if (! $file->isValid() || $file->getSize() > self::MAX_BYTES || $file->getSize() === 0) {
            throw new DomainException('Dosya en fazla 5 MB olabilir.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
        if (! isset(self::ALLOWED[$mime])) {
            throw new DomainException('Yalnızca PDF, JPG veya PNG dosyası yüklenebilir.');
        }

        if ($mime === 'application/pdf' && ! str_starts_with((string) file_get_contents($file->getRealPath(), false, null, 0, 5), '%PDF-')) {
            throw new DomainException('PDF dosyası okunamadı; dosya bozuk olabilir.');
        }
        if (str_starts_with($mime, 'image/') && @getimagesize($file->getRealPath()) === false) {
            throw new DomainException('Görsel okunamadı; dosya bozuk olabilir.');
        }

        $path = trim($directory, '/').'/'.Str::random(40).'.'.self::ALLOWED[$mime];
        Storage::disk('local')->putFileAs(dirname($path), $file, basename($path));

        // Görünen ad: yalnızca güvenli karakterler, uzantı gerçek türe göre.
        $name = Str::limit(preg_replace('/[^\pL\pN ._-]/u', '', pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'belge', 100, '');

        return Attachment::create([
            'attachable_type' => $attachableType,
            'attachable_id' => $attachableId,
            'stored_path' => $path,
            'original_name' => $name.'.'.self::ALLOWED[$mime],
            'mime' => $mime,
            'size' => $file->getSize(),
            'sha256' => hash_file('sha256', $file->getRealPath()),
            'uploaded_by_type' => $uploaderType,
            'uploaded_by_id' => $uploaderId,
        ]);
    }
}
