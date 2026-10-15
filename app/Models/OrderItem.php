<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Sipariş kalemi: fiyat, KDV, ad ve kod sipariş anındaki haliyle saklanır; ürün değişse de değişmez. */
class OrderItem extends Model
{
    use BelongsToBusiness;

    protected $guarded = ['id', 'business_id'];

    protected function casts(): array
    {
        return [
            'vat_rate' => 'decimal:2', 'quantity_requested' => 'decimal:3', 'quantity_approved' => 'decimal:3',
            'list_unit_price' => 'decimal:4', 'unit_price' => 'decimal:4', 'discount_percent' => 'decimal:2',
            'line_subtotal' => 'decimal:2', 'line_discount' => 'decimal:2', 'line_net' => 'decimal:2',
            'line_vat' => 'decimal:2', 'line_total' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Onaydan sonra onaylanan, öncesinde istenen miktar. */
    public function effectiveQuantity(): string
    {
        return (string) ($this->quantity_approved ?? $this->quantity_requested);
    }
}
