<?php

namespace App\Domain\Store\Reports\Definitions;

use App\Domain\Store\Reports\StoreReport;
use App\Models\Store\StoreProductVariant;
use Illuminate\Database\Eloquent\Builder;

/** Stok durumu: ürün (seçenek) bazında stok, kritik seviye ve stok değeri. */
class StockReport extends StoreReport
{
    public function key(): string
    {
        return 'stok-durumu';
    }

    public function title(): string
    {
        return 'Stok durumu';
    }

    public function description(): string
    {
        return 'Ürünlerin güncel stoğu, kritik seviyesi, satış fiyatı ve stok değeri; tükenen ve kritik ürünler.';
    }

    public function group(): string
    {
        return 'Stok';
    }

    public function icon(): string
    {
        return 'warehouse';
    }

    public function filters(): array
    {
        return [
            'stock' => ['label' => 'Stok durumu', 'type' => 'select', 'options' => ['' => 'Tümü', 'out' => 'Tükenenler', 'critical' => 'Kritik seviyedekiler', 'low' => 'Tükenen ve kritik', 'in' => 'Stokta olanlar']],
            'category' => ['label' => 'Kategori', 'type' => 'select', 'options' => ReportOptions::categories()],
            'brand' => ['label' => 'Marka', 'type' => 'select', 'options' => ReportOptions::brands()],
            'published' => ['label' => 'Yayın', 'type' => 'select', 'options' => ['' => 'Tümü', '1' => 'Yayında', '0' => 'Yayında değil'], 'advanced' => true],
            'q' => ['label' => 'Ara (ürün adı, ürün kodu, barkod)', 'type' => 'text', 'advanced' => true],
        ];
    }

    public function columns(): array
    {
        return [
            'sku' => ['label' => 'Ürün kodu', 'type' => 'text'],
            'barcode' => ['label' => 'Barkod', 'type' => 'text', 'default' => false],
            'name' => ['label' => 'Ürün', 'type' => 'text'],
            'option_value' => ['label' => 'Seçenek', 'type' => 'text', 'default' => false],
            'category' => ['label' => 'Kategori', 'type' => 'text'],
            'brand' => ['label' => 'Marka', 'type' => 'text', 'default' => false],
            'stock_quantity' => ['label' => 'Stok', 'type' => 'qty', 'total' => true],
            'critical_stock' => ['label' => 'Kritik seviye', 'type' => 'qty'],
            'stock_label' => ['label' => 'Durum', 'type' => 'text'],
            'price' => ['label' => 'Satış fiyatı', 'type' => 'money'],
            'stock_value' => ['label' => 'Stok değeri', 'type' => 'money', 'total' => true],
            'published_label' => ['label' => 'Yayın', 'type' => 'text', 'default' => false],
        ];
    }

    public function defaultSort(): array
    {
        return ['stock_quantity', 'asc'];
    }

    public function query(array $f): Builder
    {
        $critical = 'v.stock_quantity > 0 AND v.critical_stock IS NOT NULL AND v.stock_quantity <= v.critical_stock';

        return StoreProductVariant::query()->from('store_product_variants as v')
            ->join('store_products as p', 'p.id', '=', 'v.product_id')
            ->leftJoin('store_categories as c', 'c.id', '=', 'p.category_id')
            ->leftJoin('store_brands as b', 'b.id', '=', 'p.brand_id')
            ->where('v.is_active', true)
            ->when(($f['stock'] ?? null) === 'out', fn ($q) => $q->where('v.stock_quantity', '<=', 0))
            ->when(($f['stock'] ?? null) === 'critical', fn ($q) => $q->whereRaw($critical))
            ->when(($f['stock'] ?? null) === 'low', fn ($q) => $q->whereRaw("(v.stock_quantity <= 0 OR ({$critical}))"))
            ->when(($f['stock'] ?? null) === 'in', fn ($q) => $q->where('v.stock_quantity', '>', 0))
            ->when($f['category'] ?? null, fn ($q, $c) => $q->whereIn('p.category_id', ReportOptions::categoryIds((int) $c)))
            ->when($f['brand'] ?? null, fn ($q, $b) => $q->where('p.brand_id', $b))
            ->when(($f['published'] ?? '') !== '' && ($f['published'] ?? null) !== null, fn ($q) => $q->where('p.is_published', (bool) $f['published']))
            ->when(trim((string) ($f['q'] ?? '')), function ($q, $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($w) => $w->where('p.name', 'like', $like)->orWhere('v.sku', 'like', $like)->orWhere('v.barcode', 'like', $like));
            })
            ->select('v.sku', 'v.barcode', 'p.name', 'v.option_value', 'c.name as category', 'b.name as brand', 'v.stock_quantity', 'v.critical_stock', 'v.price')
            ->selectRaw('GREATEST(v.stock_quantity, 0) * v.price as stock_value')
            ->selectRaw("CASE WHEN v.stock_quantity <= 0 THEN 'Tükendi' WHEN {$critical} THEN 'Kritik' ELSE 'Yeterli' END as stock_label")
            ->selectRaw("CASE WHEN p.is_published THEN 'Yayında' ELSE 'Yayında değil' END as published_label");
    }
}
