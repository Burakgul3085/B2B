<?php

namespace App\Domain\Store\Reports\Definitions;

use App\Domain\Store\Reports\StoreReport;
use App\Models\Store\StoreStockMovement;
use Illuminate\Database\Eloquent\Builder;

/** Stok hareketleri: giriş, sayım, düzeltme, satış ve iade kayıtları. */
class StockMovementsReport extends StoreReport
{
    public function key(): string
    {
        return 'stok-hareketleri';
    }

    public function title(): string
    {
        return 'Stok hareketleri';
    }

    public function description(): string
    {
        return 'Stok girişi, sayım farkı, düzeltme, satış ve iade hareketleri; hareket sonrası stok ve ilgili sipariş.';
    }

    public function group(): string
    {
        return 'Stok';
    }

    public function icon(): string
    {
        return 'arrows';
    }

    public function filters(): array
    {
        return ReportOptions::dates() + [
            'type' => ['label' => 'Hareket türü', 'type' => 'select', 'options' => ['' => 'Tümü', 'in' => 'Girişler (artı)', 'out' => 'Çıkışlar (eksi)'] + StoreStockMovement::TYPES],
            'q' => ['label' => 'Ara (ürün adı, ürün kodu)', 'type' => 'text'],
        ];
    }

    public function columns(): array
    {
        return [
            'created_at' => ['label' => 'Tarih', 'type' => 'datetime'],
            'sku' => ['label' => 'Ürün kodu', 'type' => 'text'],
            'name' => ['label' => 'Ürün', 'type' => 'text'],
            'option_value' => ['label' => 'Seçenek', 'type' => 'text', 'default' => false],
            'type_label' => ['label' => 'Hareket', 'type' => 'text'],
            'quantity' => ['label' => 'Miktar', 'type' => 'qty', 'total' => true],
            'balance_after' => ['label' => 'Sonraki stok', 'type' => 'qty'],
            'order_no' => ['label' => 'Sipariş', 'type' => 'text'],
            'note' => ['label' => 'Not', 'type' => 'text', 'default' => false],
        ];
    }

    public function defaultSort(): array
    {
        return ['created_at', 'desc'];
    }

    public function query(array $f): Builder
    {
        $query = StoreStockMovement::query()->from('store_stock_movements as m')
            ->join('store_product_variants as v', 'v.id', '=', 'm.variant_id')
            ->join('store_products as p', 'p.id', '=', 'v.product_id')
            ->leftJoin('store_orders as o', fn ($j) => $j->on('o.id', '=', 'm.reference_id')->where('m.reference_type', '=', 'store_order'));
        if ($bounds = ReportOptions::bounds($f)) {
            $query->whereBetween('m.created_at', $bounds);
        }

        return $query
            ->when(($f['type'] ?? null) === 'in', fn ($q) => $q->where('m.quantity', '>', 0))
            ->when(($f['type'] ?? null) === 'out', fn ($q) => $q->where('m.quantity', '<', 0))
            ->when(isset(StoreStockMovement::TYPES[$f['type'] ?? '']), fn ($q) => $q->where('m.type', $f['type']))
            ->when(trim((string) ($f['q'] ?? '')), function ($q, $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($w) => $w->where('p.name', 'like', $like)->orWhere('v.sku', 'like', $like));
            })
            ->select('m.created_at', 'v.sku', 'p.name', 'v.option_value', 'm.quantity', 'm.balance_after', 'o.order_no', 'm.note')
            ->selectRaw(ReportOptions::label('m.type', StoreStockMovement::TYPES).' as type_label');
    }
}
