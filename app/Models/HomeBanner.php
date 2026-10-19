<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Bayi ana sayfasındaki slayt (resim veya video). */
class HomeBanner extends Model
{
    use BelongsToBusiness;

    public const LINK_TYPES = [
        'none' => 'Bağlantı yok',
        'catalog' => 'Tüm ürünler',
        'category' => 'Kategori',
        'brand' => 'Marka',
        'product' => 'Ürün',
        'showcase' => 'Vitrin',
    ];

    /**
     * Konumlar ve önerilen ölçüler. Bayi ekranındaki oranlar bunlardır; farklı oranlı dosyalar kırpılmaz,
     * kalan alan görselin bulanık hâliyle dolar.
     */
    public const PLACEMENTS = [
        'hero' => ['label' => 'Ana slayt', 'hint' => 'Sayfanın en üstündeki büyük alan', 'desktop' => '1920×600', 'mobile' => '1080×810', 'ratio' => '16:5'],
        'strip' => ['label' => 'Ara bant', 'hint' => 'Ürün vitrinlerinin arasında ince şerit', 'desktop' => '1920×320', 'mobile' => '1080×540', 'ratio' => '6:1'],
    ];

    public const TEXT_POSITIONS = ['left' => 'Solda', 'center' => 'Ortada', 'right' => 'Sağda'];

    protected $fillable = [
        'placement', 'title', 'subtitle', 'button_label', 'link_type', 'link_id', 'media_type', 'media_path', 'media_mime', 'media_size',
        'mobile_media_path', 'poster_path', 'text_position', 'starts_on', 'ends_on', 'dealer_group_id', 'sort', 'is_active',
    ];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'is_active' => 'boolean'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(DealerGroup::class, 'dealer_group_id');
    }

    public function isVideo(): bool
    {
        return $this->media_type === 'video';
    }

    /** Yayında mı: etkin ve tarih aralığında. */
    public function isLive(): bool
    {
        $today = now()->toDateString();

        return $this->is_active
            && (! $this->starts_on || $this->starts_on->toDateString() <= $today)
            && (! $this->ends_on || $this->ends_on->toDateString() >= $today);
    }

    /** Bayinin şu an görebildiği slaytlar: etkin, tarihi uygun, herkese veya bayinin grubuna. */
    public function scopeVisibleTo(Builder $query, DealerRelationship $relationship): void
    {
        $today = now()->toDateString();
        $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_on')->orWhere('starts_on', '<=', $today))
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $today))
            ->where(fn ($q) => $q->whereNull('dealer_group_id')->orWhere('dealer_group_id', $relationship->dealer_group_id));
    }
}
