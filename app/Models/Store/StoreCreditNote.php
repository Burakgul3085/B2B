<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;

/** İade / iptal faturası (elle). PDF yerel diskte, herkese açık olmayan klasörde durur. */
class StoreCreditNote extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['issued_on' => 'date'];
    }
}
