<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderReturn extends Model
{
    use BelongsToBusiness;

    public const UPDATED_AT = null;

    protected $fillable = ['order_id', 'note', 'net_total', 'vat_total', 'grand_total', 'created_by'];

    protected function casts(): array
    {
        return ['net_total' => 'decimal:2', 'vat_total' => 'decimal:2', 'grand_total' => 'decimal:2', 'created_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderReturnItem::class);
    }
}
