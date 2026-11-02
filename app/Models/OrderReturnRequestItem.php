<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderReturnRequestItem extends Model
{
    use BelongsToBusiness;

    public $timestamps = false;

    protected $fillable = ['order_return_request_id', 'order_item_id', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(OrderReturnRequest::class, 'order_return_request_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
