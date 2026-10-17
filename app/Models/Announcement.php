<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Announcement extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['title', 'body', 'dealer_group_id', 'send_email', 'published_at', 'created_by'];

    protected function casts(): array
    {
        return ['send_email' => 'boolean', 'published_at' => 'datetime'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(DealerGroup::class, 'dealer_group_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->orderBy('id');
    }

    /** Bayinin görebildiği duyurular: yayımlanmış, herkese veya bayinin grubuna. */
    public function scopeVisibleTo(Builder $query, DealerRelationship $relationship): void
    {
        $query->whereNotNull('published_at')
            ->where(fn ($q) => $q->whereNull('dealer_group_id')->orWhere('dealer_group_id', $relationship->dealer_group_id));
    }
}
