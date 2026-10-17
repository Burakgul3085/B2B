<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShowcaseProduct extends Model
{
    use BelongsToBusiness;

    public $timestamps = false;

    protected $fillable = ['showcase_id', 'product_id', 'sort'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
