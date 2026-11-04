<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Kupon / kampanya görseli: kapak (`is_cover`) veya detay fotoğrafı. Dosya private diskte; yetkili controller sunar. */
class PromotionImage extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['promotion_id', 'path', 'is_cover', 'sort'];

    protected function casts(): array
    {
        return ['is_cover' => 'boolean', 'sort' => 'integer'];
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }
}
