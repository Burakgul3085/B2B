<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockCount extends Model
{
    use BelongsToBusiness;

    public const STATUSES = ['draft' => 'Taslak', 'applied' => 'Uygulandı', 'cancelled' => 'İptal edildi'];

    protected $fillable = ['warehouse_id', 'status', 'note', 'created_by', 'applied_at'];

    protected function casts(): array
    {
        return ['applied_at' => 'datetime'];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockCountLine::class);
    }
}
