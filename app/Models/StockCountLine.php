<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCountLine extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['stock_count_id', 'product_id', 'counted_qty', 'system_qty', 'difference'];

    protected function casts(): array
    {
        return ['counted_qty' => 'decimal:3', 'system_qty' => 'decimal:3', 'difference' => 'decimal:3'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
