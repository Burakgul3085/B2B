<?php

namespace App\Domain\Dealers;

use App\Domain\Audit\AuditLogger;
use App\Domain\Files\ImageUploader;
use App\Models\Dealer;
use Illuminate\Http\UploadedFile;

/**
 * Bayinin kendi logosu. Bayi kaydı toptancıdan bağımsız olduğu için dosya bayi klasöründe tutulur;
 * görsel yeniden kodlanır (WEBP, en uzun kenar 480 px). Yeni logo kaydedilmeden eskisi silinmez.
 */
class DealerLogo
{
    public function __construct(private ImageUploader $images, private AuditLogger $audit) {}

    public function replace(Dealer $dealer, UploadedFile $file): void
    {
        $path = $this->images->store($file, 'dealers/'.$dealer->id.'/logo', 480, 2 * 1024 * 1024, 90);
        $old = $dealer->logo_path;
        $dealer->update(['logo_path' => $path]);
        $this->images->delete($old);
        $this->audit->log('dealer.logo_updated', $dealer);
    }

    public function remove(Dealer $dealer): void
    {
        $old = $dealer->logo_path;
        $dealer->update(['logo_path' => null]);
        $this->images->delete($old);
        $this->audit->log('dealer.logo_removed', $dealer);
    }
}
