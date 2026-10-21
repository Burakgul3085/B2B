<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;

/** Siparişte onaylanan yasal metnin o anki, siparişe göre doldurulmuş hâli ve onay kaydı. */
class StoreOrderConsent extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['accepted_at' => 'datetime'];
    }
}
