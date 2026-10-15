<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderReturnItem extends Model
{
    use BelongsToBusiness;

    public $timestamps = false;

    protected $fillable = ['order_return_id', 'order_item_id', 'quantity', 'line_net', 'line_vat', 'line_total'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'line_net' => 'decimal:2', 'line_vat' => 'decimal:2', 'line_total' => 'decimal:2'];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
