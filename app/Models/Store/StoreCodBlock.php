<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;

/** Kapıda ödemesi kapatılan telefon (teslim almayan müşteri). */
class StoreCodBlock extends Model
{
    protected $table = 'store_cod_blocklist';

    protected $guarded = ['id'];
}
