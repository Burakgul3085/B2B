<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;

/** Kargo firması. Takip bağlantısı şablonunda {takip} yerine takip numarası yazılır. */
class StoreCarrier extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['is_active' => true, 'sort_order' => 0];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function trackingUrl(?string $tracking): ?string
    {
        if (! $this->tracking_url || ! $tracking) {
            return null;
        }

        return str_replace('{takip}', rawurlencode($tracking), $this->tracking_url);
    }
}
