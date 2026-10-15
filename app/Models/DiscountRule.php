<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscountRule extends Model
{
    use BelongsToBusiness;

    public const TARGETS = ['all' => 'Tüm bayiler', 'group' => 'Bayi grubu', 'dealer' => 'Tek bayi'];

    protected $attributes = ['is_active' => true];

    protected $fillable = [
        'name', 'scope', 'product_id', 'category_id', 'target', 'dealer_group_id', 'dealer_relationship_id',
        'percent', 'starts_on', 'ends_on', 'is_active',
    ];

    protected function casts(): array
    {
        return ['percent' => 'decimal:2', 'starts_on' => 'date', 'ends_on' => 'date', 'is_active' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(DealerGroup::class, 'dealer_group_id');
    }

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(DealerRelationship::class, 'dealer_relationship_id');
    }
}
