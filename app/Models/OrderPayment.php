<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * B2B sipariş ödemesi: iyzico kart ödemesi (driver iyzico) veya toptancının kaydettiği havale / nakit / POS ödemesi
 * (driver manual). Başarılı ödeme bayinin carisine tahsilat olarak yazılır (collection_id).
 */
class OrderPayment extends Model
{
    use BelongsToBusiness;

    public const MANUAL_METHODS = ['transfer' => 'Havale / EFT', 'cash' => 'Nakit', 'card_pos' => 'Kredi kartı (fiziki POS)'];

    protected $guarded = ['id', 'business_id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'raw' => 'array', 'installment' => 'integer', 'refunded_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function label(): string
    {
        return $this->driver === 'iyzico'
            ? 'Kartla ödeme'.($this->card_last_four ? " (**** {$this->card_last_four})" : '').($this->installment > 1 ? " · {$this->installment} taksit" : '')
            : (self::MANUAL_METHODS[$this->method] ?? 'Ödeme');
    }
}
