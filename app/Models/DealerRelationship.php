<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Toptancı ↔ bayi ticari ilişkisi. Fiyat, sipariş ve cari bu kayda bağlıdır. */
class DealerRelationship extends Model
{
    use BelongsToBusiness;

    public const STATUSES = ['active' => 'Aktif', 'suspended' => 'Askıda', 'closed' => 'Kapatıldı'];

    protected $fillable = [
        'dealer_id', 'dealer_code', 'dealer_group_id', 'status', 'payment_term_days',
        'can_edit_addresses', 'allow_open_account', 'allow_on_delivery', 'allow_pay_later', 'credit_limit', 'approved_at', 'approved_by',
    ];

    protected function casts(): array
    {
        return ['can_edit_addresses' => 'boolean', 'allow_open_account' => 'boolean', 'allow_on_delivery' => 'boolean', 'allow_pay_later' => 'boolean',
            'credit_limit' => 'decimal:2', 'approved_at' => 'datetime'];
    }

    public function dealer(): BelongsTo
    {
        return $this->belongsTo(Dealer::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(DealerGroup::class, 'dealer_group_id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(DealerAddress::class)->whereNull('archived_at')->orderByDesc('is_default')->orderBy('title');
    }

    public function currentAccount(): HasOne
    {
        return $this->hasOne(CurrentAccount::class);
    }

    public function productPrices(): HasMany
    {
        return $this->hasMany(DealerProductPrice::class);
    }

    /** Sepette görünen "Sonra öde" adı: süre 0 ise teslimatta, değilse teslimden N gün sonra. */
    /** Bayi şu an "sonra öde" seçebilir mi: özellik sistemde açık (config b2b.pay_later_enabled) ve bayiye izin verilmiş. */
    public function payLaterEnabled(): bool
    {
        return (bool) config('b2b.pay_later_enabled') && (bool) $this->allow_pay_later;
    }

    public function payLaterLabel(): string
    {
        return Order::payLaterLabelFor((int) $this->payment_term_days);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
