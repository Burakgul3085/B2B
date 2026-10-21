<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Müşteri adresi. İl ve ilçe sabit listeden seçilir. */
class StoreCustomerAddress extends Model
{
    protected $attributes = ['is_default' => false, 'neighborhood' => null, 'postal_code' => null];

    protected $fillable = ['title', 'first_name', 'last_name', 'phone', 'province_id', 'district_id', 'neighborhood', 'address_line', 'postal_code'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(StoreCustomer::class, 'customer_id');
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class, 'province_id');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class, 'district_id');
    }

    /** Tek satır adres: "Mahalle, Açık adres, İlçe / İl". province ve district yüklü olmalıdır. */
    public function oneLine(): string
    {
        return implode(', ', array_filter([$this->neighborhood, $this->address_line])).' — '.$this->district->name.' / '.$this->province->name;
    }
}
