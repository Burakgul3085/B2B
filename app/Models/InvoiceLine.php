<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

/** B2B fatura kalemi: KDV hariç birim fiyat ve iskonto; tutarlar siparişin hesaplanmış tutarlarıdır. */
class InvoiceLine extends Model
{
    use BelongsToBusiness;

    public $timestamps = false;

    protected $guarded = ['id', 'business_id'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3', 'unit_price' => 'decimal:4', 'discount' => 'decimal:2', 'vat_rate' => 'decimal:2',
            'net_amount' => 'decimal:2', 'vat_amount' => 'decimal:2', 'line_total' => 'decimal:2',
        ];
    }
}
