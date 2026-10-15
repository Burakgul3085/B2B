<?php

namespace App\Models;

use App\Domain\Tenancy\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Stokta olmayan ürün talebi. Stoğu değiştirmez, ürünü ayırmaz, siparişe dönüşmez. */
class ProductRequest extends Model
{
    use BelongsToBusiness;

    public const STATUSES = [
        'new' => 'Yeni',
        'in_review' => 'İnceleniyor',
        'available' => 'Tedarik edilebilir',
        'unavailable' => 'Tedarik edilemiyor',
        'stock_updated' => 'Stok güncellendi',
        'closed' => 'Kapatıldı',
    ];

    public const OPEN = ['new', 'in_review', 'available'];

    protected $attributes = ['status' => 'new', 'notify_when_in_stock' => true];

    protected $fillable = ['dealer_relationship_id', 'product_id', 'quantity', 'note', 'status', 'notify_when_in_stock', 'notified_at', 'response_note', 'responded_by', 'responded_at'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'notify_when_in_stock' => 'boolean', 'notified_at' => 'datetime', 'responded_at' => 'datetime'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(DealerRelationship::class, 'dealer_relationship_id');
    }

    public function statusTone(): string
    {
        return ['new' => 'info', 'in_review' => 'primary', 'available' => 'success', 'unavailable' => 'danger', 'stock_updated' => 'success', 'closed' => 'neutral'][$this->status];
    }
}
