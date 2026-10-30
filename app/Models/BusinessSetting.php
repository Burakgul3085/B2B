<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessSetting extends Model
{
    protected $fillable = [
        'business_id', 'default_warehouse_id', 'stock_display', 'ledger_post_on',
        'default_payment_term_days', 'order_no_prefix', 'price_display', 'new_product_days', 'new_products_since',
        'payments_enabled', 'card_enabled', 'iyzico_mode', 'iyzico_api_key', 'iyzico_secret_key', 'installments',
        'transfer_enabled', 'transfer_accounts', 'delivery_fee', 'delivery_free_over',
    ];

    /** iyzico anahtarları şifreli tutulur (B2B ödeme ayarları); modelden düz metin olarak çıkmaz. */
    protected $hidden = ['iyzico_api_key', 'iyzico_secret_key'];

    protected function casts(): array
    {
        return [
            'new_products_since' => 'date', 'payments_enabled' => 'boolean', 'card_enabled' => 'boolean', 'transfer_enabled' => 'boolean',
            'transfer_accounts' => 'array', 'delivery_fee' => 'decimal:2', 'delivery_free_over' => 'decimal:2',
        ];
    }
}
