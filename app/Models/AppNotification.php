<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AppNotification extends Model
{
    use BelongsToBusiness;

    public const UPDATED_AT = null;

    protected $fillable = ['recipient_side', 'dealer_relationship_id', 'type', 'title', 'body', 'url', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'created_at' => 'datetime'];
    }

    /** Toptancı için: işletmeye gelen bildirimler. Bayi için: seçili ilişkiye gelenler. */
    public function scopeFor(Builder $query, string $side, ?int $relationshipId = null): void
    {
        $query->where('recipient_side', $side)
            ->when($side === 'dealer', fn ($q) => $q->where('dealer_relationship_id', $relationshipId));
    }
}
