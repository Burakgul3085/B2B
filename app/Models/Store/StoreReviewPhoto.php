<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Yorum fotoğrafı (özel diskte). Vitrinde yalnızca yayındaki yorumun fotoğrafı, ayrıca yorum sahibine ve yöneticiye gösterilir. */
class StoreReviewPhoto extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    public function review(): BelongsTo
    {
        return $this->belongsTo(StoreReview::class, 'review_id');
    }
}
