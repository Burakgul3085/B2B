<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Manuel tahsilat kaydı (tablo: collections). Sistem ödeme almaz; kayıt toptancı tarafından girilir. */
class CollectionEntry extends Model
{
    use BelongsToBusiness;

    protected $table = 'collections';

    public const METHODS = [
        'cash' => 'Nakit',
        'transfer' => 'Havale / EFT',
        'check' => 'Çek',
        'promissory_note' => 'Senet',
        'card_pos' => 'Kredi kartı (fiziki POS)',
        'other' => 'Diğer',
    ];

    protected $fillable = ['current_account_id', 'collected_on', 'amount', 'method', 'order_id', 'description', 'created_by'];

    protected function casts(): array
    {
        return ['collected_on' => 'date', 'amount' => 'decimal:2', 'cancelled_at' => 'datetime'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CurrentAccount::class, 'current_account_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }
}
