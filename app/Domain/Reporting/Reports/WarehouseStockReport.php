<?php

namespace App\Domain\Reporting\Reports;

use App\Domain\Reporting\Report;
use App\Domain\Reporting\ReportContext;
use App\Models\WarehouseStock;
use Illuminate\Database\Eloquent\Builder;

/** Depo bazında güncel stok ve alış fiyatıyla değeri. */
class WarehouseStockReport extends Report
{
    public function key(): string
    {
        return 'warehouse-stock';
    }

    public function title(): string
    {
        return 'Depo bazında stok';
    }

    public function description(): string
    {
        return 'Her depodaki güncel stok ve alış fiyatı × miktar değeri.';
    }

    public function group(): string
    {
        return 'Stok';
    }

    public function filters(ReportContext $context): array
    {
        return [
            'warehouse' => ['label' => 'Depo', 'type' => 'select', 'options' => Options::warehouses()],
            'category' => ['label' => 'Kategori', 'type' => 'select', 'options' => Options::categories()],
            'q' => ['label' => 'Ara', 'type' => 'text', 'advanced' => true],
            'nonzero' => ['label' => 'Yalnızca stoklu', 'type' => 'select', 'options' => ['1' => 'Evet', '0' => 'Hayır'], 'default' => '1', 'advanced' => true],
        ];
    }

    public function columns(): array
    {
        return [
            'warehouse' => ['label' => 'Depo', 'type' => 'text'],
            'sku' => ['label' => 'Ürün kodu', 'type' => 'text'],
            'name' => ['label' => 'Ürün', 'type' => 'text'],
            'quantity' => ['label' => 'Stok', 'type' => 'qty', 'total' => true],
            'unit' => ['label' => 'Birim', 'type' => 'text', 'default' => false],
            'purchase_price' => ['label' => 'Alış fiyatı', 'type' => 'money', 'default' => false],
            'value' => ['label' => 'Stok değeri (alış)', 'type' => 'money', 'total' => true],
        ];
    }

    public function defaultSort(): array
    {
        return ['name', 'asc'];
    }

    public function query(array $f, ReportContext $context): Builder
    {
        return WarehouseStock::query()
            ->join('warehouses', 'warehouses.id', '=', 'warehouse_stock.warehouse_id')
            ->join('products', 'products.id', '=', 'warehouse_stock.product_id')
            ->join('units', 'units.id', '=', 'products.unit_id')
            ->whereNull('products.archived_at')
            ->select('warehouses.name as warehouse', 'products.sku', 'products.name', 'warehouse_stock.quantity', 'units.name as unit', 'products.purchase_price')
            ->selectRaw('ROUND(warehouse_stock.quantity * COALESCE(products.purchase_price, 0), 2) as value')
            ->when($f['warehouse'] ?? null, fn ($q, $w) => $q->where('warehouse_stock.warehouse_id', $w))
            ->when($f['category'] ?? null, fn ($q, $c) => $q->whereIn('products.category_id', Options::categoryIds((int) $c)))
            ->when($f['q'] ?? null, fn ($q, $t) => $q->where(fn ($q) => $q->where('products.name', 'like', Options::like($t))->orWhere('products.sku', 'like', Options::like($t))))
            ->when(($f['nonzero'] ?? '1') === '1', fn ($q) => $q->where('warehouse_stock.quantity', '>', 0));
    }
}
