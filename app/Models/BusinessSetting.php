<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessSetting extends Model
{
    protected $fillable = [
        'business_id', 'default_warehouse_id', 'stock_display', 'ledger_post_on',
        'default_payment_term_days', 'order_no_prefix', 'price_display',
    ];
}
