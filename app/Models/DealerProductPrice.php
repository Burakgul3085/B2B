<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DealerProductPrice extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['dealer_relationship_id', 'product_id', 'price', 'starts_on', 'ends_on'];

    protected function casts(): array
    {
        return ['price' => 'decimal:4', 'starts_on' => 'date', 'ends_on' => 'date'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
