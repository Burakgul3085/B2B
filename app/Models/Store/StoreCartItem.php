<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreCartItem extends Model
{
    protected $fillable = ['cart_id', 'variant_id', 'print_job_id', 'payment_code_id', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(StoreProductVariant::class, 'variant_id');
    }

    /** Ürün yerine baskı işi (fotokopi) satırı; variant_id boştur. */
    public function printJob(): BelongsTo
    {
        return $this->belongsTo(StorePrintJob::class, 'print_job_id');
    }

    /** Ürün yerine özel ödeme kodu satırı; variant_id boştur, tutar kodun kaydından gelir. */
    public function paymentCode(): BelongsTo
    {
        return $this->belongsTo(StorePaymentCode::class, 'payment_code_id');
    }
}
