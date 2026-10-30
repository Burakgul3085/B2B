<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ürün sorusu. Durum: pending (cevap bekliyor, vitrinde görünmez) → answered (soru ve cevap birlikte yayında) | hidden.
 * Soranın e-postası yalnızca cevabı iletmek için tutulur; vitrinde ad "Ayşe K." biçiminde gösterilir.
 */
class StoreProductQuestion extends Model
{
    public const STATUSES = ['pending' => 'Cevap bekliyor', 'answered' => 'Yayında', 'hidden' => 'Gizlendi'];

    /** Aynı e-postanın cevap bekleyen en fazla soru sayısı. */
    public const MAX_OPEN_PER_EMAIL = 3;

    protected $attributes = ['status' => 'pending'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['answered_at' => 'datetime', 'answer_mailed_at' => 'datetime'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(StoreProduct::class, 'product_id');
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('status', 'answered');
    }

    /** "Ayşe Kaya" → "Ayşe K." */
    public function displayName(): string
    {
        $last = trim($this->last_name);

        return trim(mb_convert_case(trim($this->first_name), MB_CASE_TITLE, 'UTF-8').($last !== '' ? ' '.mb_strtoupper(mb_substr($last, 0, 1)).'.' : ''));
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            'pending' => 'warning',
            'answered' => 'success',
            default => 'neutral',
        };
    }
}
