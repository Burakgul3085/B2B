<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;

/** e-Fatura kalemi. Tutarlar (KDV hariç, KDV, KDV dahil) sunucuda miktar, birim fiyat, indirim ve KDV oranından hesaplanır. */
class StoreEInvoiceLine extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];
}
