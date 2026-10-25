<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Mağaza ana sayfası banner'ı. Etkin ve yayın tarih aralığındaysa gösterilir. */
class StoreBanner extends Model
{
    protected $attributes = ['is_active' => true, 'sort' => 0, 'mobile_image_path' => null, 'starts_at' => null, 'ends_at' => null];

    public const PLACEMENTS = ['hero' => 'Büyük alan (tam genişlik, kayan)', 'side' => 'Kampanya kutusu (büyük alanın altında)'];

    /**
     * Görsel ölçüleri [genişlik, yükseklik]. Vitrin bu oranlarda sabit çerçeve kullanır; görsel tam bu orandaysa
     * kırpılmadan ve boşluk kalmadan çerçeveyi doldurur. Bu yüzden yüklemede oran denetlenir (bkz. StorefrontAdmin).
     */
    public const SIZES = ['hero' => [1920, 640], 'mobile' => [1080, 1350], 'side' => [900, 600]];

    /** Oran toleransı (%1): yüklenen görselin oranı bu kadar sapabilir; gözle görülmez. */
    public const RATIO_TOLERANCE = 0.01;

    protected $fillable = ['title', 'subtitle', 'button_text', 'link_url', 'placement', 'sort', 'is_active', 'starts_at', 'ends_at'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->orderBy('sort')->orderBy('id');
    }
}
