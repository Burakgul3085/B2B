<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use BelongsToBusiness;

    public const STATUSES = [
        'submitted' => 'Toptancıya iletildi',
        'in_review' => 'İnceleniyor',
        'approved' => 'Onaylandı',
        'preparing' => 'Hazırlanıyor',
        'completed' => 'Tamamlandı',
        'rejected' => 'Reddedildi',
        'cancel_requested' => 'İptal talebi',
        'cancelled' => 'İptal edildi',
    ];

    /** Toptancının kararını bekleyen durumlar. */
    public const PENDING = ['submitted', 'in_review'];

    /** Stoğu düşülmüş, henüz kapanmamış durumlar. */
    public const ACTIVE_FULFILMENT = ['approved', 'preparing', 'cancel_requested'];

    protected $guarded = ['id', 'business_id'];

    protected function casts(): array
    {
        return [
            'is_partial' => 'boolean',
            'subtotal' => 'decimal:2', 'discount_total' => 'decimal:2', 'net_total' => 'decimal:2',
            'vat_total' => 'decimal:2', 'grand_total' => 'decimal:2',
            'delivery_planned_on' => 'date',
            'approved_at' => 'datetime', 'stock_applied_at' => 'datetime', 'stock_reversed_at' => 'datetime',
            'ledger_applied_at' => 'datetime', 'ledger_reversed_at' => 'datetime',
            'completed_at' => 'datetime', 'cancelled_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('id');
    }

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(DealerRelationship::class, 'dealer_relationship_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status].($this->is_partial && in_array($this->status, ['approved', 'preparing', 'completed'], true) ? ' · Kısmi' : '');
    }

    public function statusTone(): string
    {
        return [
            'submitted' => 'info', 'in_review' => 'primary', 'approved' => 'success', 'preparing' => 'primary',
            'completed' => 'success', 'rejected' => 'danger', 'cancel_requested' => 'warning', 'cancelled' => 'neutral',
        ][$this->status];
    }

    public function shippingLines(): array
    {
        return array_filter([
            $this->ship_title.' — '.$this->ship_recipient.' · '.$this->ship_phone,
            $this->ship_address,
            $this->ship_district.' / '.$this->ship_city,
            $this->ship_note,
        ]);
    }
}
