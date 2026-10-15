<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

/** Toptancı–bayi ilişkisinin cari hesabı. Bakiye yalnızca cari hareketleriyle değişir (Aşama 6). */
class CurrentAccount extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['dealer_relationship_id', 'code'];

    protected function casts(): array
    {
        return ['balance' => 'decimal:2'];
    }
}
