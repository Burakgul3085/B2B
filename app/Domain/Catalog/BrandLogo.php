<?php

namespace App\Domain\Catalog;

use App\Domain\Audit\AuditLogger;
use App\Domain\Files\ImageUploader;
use App\Models\Brand;
use Illuminate\Http\UploadedFile;

/**
 * Marka logosu: görsel yeniden kodlanır (WEBP, en uzun kenar 480 px), web kökü dışında saklanır.
 * Yeni logo kaydedilmeden eski dosya silinmez.
 */
class BrandLogo
{
    public function __construct(private ImageUploader $images, private AuditLogger $audit) {}

    public function replace(Brand $brand, UploadedFile $file): void
    {
        $path = $this->images->store($file, 'businesses/'.$brand->business_id.'/brands', 480, 2 * 1024 * 1024, 90);
        $old = $brand->logo_path;
        $brand->update(['logo_path' => $path]);
        $this->images->delete($old);
        $this->audit->log('catalog.brand_logo_updated', $brand);
    }

    public function remove(Brand $brand): void
    {
        $old = $brand->logo_path;
        $brand->update(['logo_path' => null]);
        $this->images->delete($old);
        $this->audit->log('catalog.brand_logo_removed', $brand);
    }
}
