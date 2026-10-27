<?php

namespace App\Models\Store;

use App\Domain\Store\Shipping\CarrierBrands;
use Illuminate\Database\Eloquent\Model;

/**
 * Kargo firması. Ücreti girilmişse müşteri ödeme sayfasında bu firmayı seçebilir. Takip bağlantısı şablonunda
 * {takip} yerine takip numarası yazılır; boşsa bilinen firmanın bağlantısı kullanılır (CarrierBrands).
 */
class StoreCarrier extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['is_active' => true, 'sort_order' => 0];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer', 'fee' => 'decimal:2'];
    }

    /** @return array{key: string, name: string, tracking_url: string, logo: string}|null */
    public function brand(): ?array
    {
        return CarrierBrands::match($this->name);
    }

    public function logoUrl(): ?string
    {
        return $this->brand()['logo'] ?? null;
    }

    /** Geçerli takip bağlantısı şablonu: girilen ya da bilinen firmanınki. */
    public function trackingTemplate(): ?string
    {
        return $this->tracking_url ?: ($this->brand()['tracking_url'] ?? null);
    }

    public function trackingUrl(?string $tracking): ?string
    {
        $template = $this->trackingTemplate();
        if (! $template || ! $tracking) {
            return null;
        }

        return str_replace('{takip}', rawurlencode($tracking), $template);
    }
}
