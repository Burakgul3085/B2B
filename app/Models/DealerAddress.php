<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Sevk adresi. Siparişte kopyası saklanır; burada yapılan değişiklik geçmiş siparişleri etkilemez. */
class DealerAddress extends Model
{
    use BelongsToBusiness;

    public const EDITABLE = ['title', 'recipient_name', 'phone', 'city', 'district', 'address_line', 'note'];

    protected $attributes = ['is_default' => false];

    protected $fillable = [...self::EDITABLE, 'dealer_relationship_id', 'is_default'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'archived_at' => 'datetime'];
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    public function oneLine(): string
    {
        return "{$this->address_line}, {$this->district} / {$this->city}";
    }
}
