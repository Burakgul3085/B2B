<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Anlık stok. Yalnızca App\Domain\Inventory\StockService tarafından değiştirilir. */
class WarehouseStock extends Model
{
    use BelongsToBusiness;

    protected $table = 'warehouse_stock';

    protected $fillable = ['warehouse_id', 'product_id', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
