<?php

namespace App\Domain\Files;

use App\Models\Attachment;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Belge yükleme (başvuru belgeleri, mesaj ve duyuru ekleri). İçerik türü dosyanın kendisinden belirlenir;
 * dosya rastgele adla web kökü dışında saklanır ve yalnızca yetki kontrollü controller'dan indirilir.
 * Office dosyaları (Word/Excel) yalnızca açıkça izin verilen yerlerde kabul edilir; makrolu dosyalar reddedilir.
 */
class DocumentUploader
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    public const ALLOWED = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    public const DOCX = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    public const XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    /** Duyuru ekleri: görsel ve PDF'e ek olarak Word ve Excel. */
    public const WITH_OFFICE = self::ALLOWED + [
        self::DOCX => 'docx',
        self::XLSX => 'xlsx',
        'application/msword' => 'doc',
        'application/vnd.ms-excel' => 'xls',
    ];

    /** Office dosyaları için finfo'nun verebildiği genel türler; asıl tür içerikten çıkarılır. */
    private const ZIP_LIKE = ['application/zip', 'application/octet-stream', self::DOCX, self::XLSX];

    private const OLE_LIKE = ['application/msword', 'application/vnd.ms-excel', 'application/vnd.ms-office', 'application/CDFV2', 'application/x-ole-storage'];

    /** @param  array<string, string>  $allowed  izin verilen içerik türü => uzantı */
    public function store(UploadedFile $file, string $directory, string $attachableType, int $attachableId, ?string $uploaderType = null, ?int $uploaderId = null, array $allowed = self::ALLOWED, int $maxBytes = self::MAX_BYTES): Attachment
    {
        if (! $file->isValid() || $file->getSize() > $maxBytes || $file->getSize() === 0) {
            throw new DomainException('Dosya en fazla '.intdiv($maxBytes, 1024 * 1024).' MB olabilir.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
        if (isset($allowed[self::DOCX]) && in_array($mime, self::ZIP_LIKE, true)) {
            $mime = $this->officeOpenXmlType($file->getRealPath()) ?? 'invalid/office';
        } elseif (isset($allowed['application/msword']) && in_array($mime, self::OLE_LIKE, true)) {
            $mime = $this->legacyOfficeType($file) ?? 'invalid/office';
        }

        if (! isset($allowed[$mime])) {
            throw new DomainException('Yalnızca '.collect($allowed)->unique()->map(fn ($ext) => strtoupper($ext))->join(', ', ' veya ').' dosyası yüklenebilir.');
        }

        if ($mime === 'application/pdf' && ! str_starts_with((string) file_get_contents($file->getRealPath(), false, null, 0, 5), '%PDF-')) {
            throw new DomainException('PDF dosyası okunamadı; dosya bozuk olabilir.');
        }
        if (str_starts_with($mime, 'image/') && @getimagesize($file->getRealPath()) === false) {
            throw new DomainException('Görsel okunamadı; dosya bozuk olabilir.');
        }

        $path = trim($directory, '/').'/'.Str::random(40).'.'.$allowed[$mime];
        Storage::disk('local')->putFileAs(dirname($path), $file, basename($path));

        // Görünen ad: yalnızca güvenli karakterler, uzantı gerçek türe göre.
        $name = Str::limit(preg_replace('/[^\pL\pN ._-]/u', '', pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'belge', 100, '');

        return Attachment::create([
            'attachable_type' => $attachableType,
            'attachable_id' => $attachableId,
            'stored_path' => $path,
            'original_name' => $name.'.'.$allowed[$mime],
            'mime' => $mime,
            'size' => $file->getSize(),
            'sha256' => hash_file('sha256', $file->getRealPath()),
            'uploaded_by_type' => $uploaderType,
            'uploaded_by_id' => $uploaderId,
        ]);
    }

    /** Word/Excel (OOXML): gerçek bir Office paketi mi ve makro içermiyor mu? */
    private function officeOpenXmlType(string $path): ?string
    {
        if ((string) file_get_contents($path, false, null, 0, 4) !== "PK\x03\x04" || ! class_exists(ZipArchive::class)) {
            return null;
        }

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            return null;
        }

        try {
            $types = (string) $zip->getFromName('[Content_Types].xml');
            if ($types === '' || stripos($types, 'macroEnabled') !== false || $zip->locateName('vbaProject.bin', ZipArchive::FL_NODIR) !== false) {
                return null;
            }

            return match (true) {
                $zip->locateName('word/document.xml') !== false => self::DOCX,
                $zip->locateName('xl/workbook.xml') !== false => self::XLSX,
                default => null,
            };
        } finally {
            $zip->close();
        }
    }

    /** Eski biçim Word/Excel (OLE): imza doğrulanır, tür uzantıdan seçilir (doc/xls dışı reddedilir). */
    private function legacyOfficeType(UploadedFile $file): ?string
    {
        if ((string) file_get_contents($file->getRealPath(), false, null, 0, 8) !== "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1") {
            return null;
        }

        return match (strtolower($file->getClientOriginalExtension())) {
            'doc' => 'application/msword',
            'xls' => 'application/vnd.ms-excel',
            default => null,
        };
    }
}
