<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** İade talebindeki ürün fotoğrafı (özel diskte; yönetimde ve imzalı bağlantıyla müşteriye gösterilir). */
class StoreReturnPhoto extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    public function storeReturn(): BelongsTo
    {
        return $this->belongsTo(StoreReturn::class, 'return_id');
    }
}
