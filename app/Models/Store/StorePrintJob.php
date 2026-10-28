<?php

namespace App\Models\Store;

use App\Domain\Store\Print\PrintOptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Fiyatlanmış baskı işi: bir belge + seçenekler. Fiyat sunucuda hesaplanır (PrintPricing) ve sepette her gösterimde yenilenir. */
class StorePrintJob extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['options' => 'array', 'breakdown' => 'array', 'pages' => 'integer', 'sides' => 'integer', 'sheets' => 'integer', 'copies' => 'integer'];
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(StorePrintFile::class, 'file_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(StoreOrder::class, 'order_id');
    }

    public function printOptions(): PrintOptions
    {
        return PrintOptions::fromArray($this->options);
    }

    /** Sepette ve siparişte görünen kısa özet: "48 sayfa · S/B · Önlü arkalı · 2 sayfa/yüz · Spiral · 2 kopya" */
    public function summary(): string
    {
        return $this->printOptions()->summary($this->pages);
    }
}
