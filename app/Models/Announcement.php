<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
