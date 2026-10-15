<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Bayi işletmesi. Toptancıdan bağımsızdır; ticari ilişki DealerRelationship kaydındadır. */
class Dealer extends Model
{
    protected $fillable = ['legal_name', 'tax_office', 'tax_number', 'phone', 'email', 'city', 'district', 'address'];

    public function user(): HasOne
    {
        return $this->hasOne(DealerUser::class);
    }
}
