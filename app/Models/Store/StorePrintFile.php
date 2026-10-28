<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fotokopi için yüklenen belge. Dosyalar herkese açık değildir (local disk, store/print/{token}); yalnızca sahibi
 * (aynı sepet) ve mağaza yönetimi erişir. Süresi dolan dosya silinir, kayıt "deleted" olarak kalır (App\Domain\Store\Print).
 */
class StorePrintFile extends Model
{
    public const STATUSES = ['uploading' => 'Yükleniyor', 'processing' => 'Hazırlanıyor', 'ready' => 'Hazır', 'failed' => 'Hata', 'deleted' => 'Silindi'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['size' => 'integer', 'received' => 'integer', 'pages' => 'integer', 'expires_at' => 'datetime'];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(StoreCart::class, 'cart_id');
    }

    public function isImage(): bool
    {
        return in_array($this->extension, ['jpg', 'jpeg', 'png'], true);
    }

    public function isReady(): bool
    {
        return $this->status === 'ready' && $this->pages > 0;
    }

    /** Baskıya gidecek dosya: çevrilmiş PDF, yoksa (PDF ve görselde) özgün dosya. */
    public function printablePath(): string
    {
        return $this->pdf_path ?? $this->path;
    }
}
