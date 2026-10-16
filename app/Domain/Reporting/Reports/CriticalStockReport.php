<?php

namespace App\Domain\Reporting\Reports;

use App\Domain\Reporting\Report;
use App\Domain\Reporting\ReportContext;
use App\Models\BusinessSetting;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

/** Kritik stok ve stokta olmayan ürünler (seçili depo; varsayılan sevk deposu). */
class CriticalStockReport extends Report
{
    public function key(): string
    {
        return 'critical-stock';
    }

    public function title(): string
    {
        return 'Kritik stok ve stokta olmayanlar';
    }

    public function description(): string
    {
        return 'Kritik seviyede, altında veya stokta olmayan aktif ürünler.';
    }

    public function group(): string
    {
        return 'Stok';
    }

    public function filters(ReportContext $context): array
    {
        return [
            'warehouse' => ['label' => 'Depo', 'type' => 'select', 'options' => Options::warehouses(false),
                'default' => (string) BusinessSetting::where('business_id', $context->businessId)->value('default_warehouse_id')],
            'level' => ['label' => 'Seviye', 'type' => 'select', 'options' => ['' => 'Kritik ve stokta yok', 'critical' => 'Yalnızca kritik', 'out' => 'Yalnızca stokta yok']],
            'category' => ['label' => 'Kategori', 'type' => 'select', 'options' => Options::categories(), 'advanced' => true],
        ];
    }

    public function columns(): array
    {
        return [
            'sku' => ['label' => 'Ürün kodu', 'type' => 'text'],
            'name' => ['label' => 'Ürün', 'type' => 'text'],
            'quantity' => ['label' => 'Stok', 'type' => 'qty'],
            'critical' => ['label' => 'Kritik seviye', 'type' => 'qty'],
            'deficit' => ['label' => 'Kritik seviyeye eksik', 'type' => 'qty', 'total' => true],
            'unit' => ['label' => 'Birim', 'type' => 'text'],
        ];
    }

    public function defaultSort(): array
    {
        return ['deficit', 'desc'];
    }

    public function query(array $f, ReportContext $context): Builder
    {
        $wh = (int) $f['warehouse'];
        $qty = "COALESCE((SELECT ws.quantity FROM warehouse_stock ws WHERE ws.product_id = products.id AND ws.warehouse_id = {$wh}), 0)";

        return Product::query()->notArchived()->where('products.is_active', true)
            ->join('units', 'units.id', '=', 'products.unit_id')
            ->select('products.sku', 'products.name', 'units.name as unit', 'products.critical_stock_level as critical')
            ->selectRaw("{$qty} as quantity")
            ->selectRaw("GREATEST(products.critical_stock_level - {$qty}, 0) as deficit")
            ->whereRaw("{$qty} <= products.critical_stock_level")
            ->when(($f['level'] ?? null) === 'out', fn ($q) => $q->whereRaw("{$qty} <= 0"))
            ->when(($f['level'] ?? null) === 'critical', fn ($q) => $q->whereRaw("{$qty} > 0"))
            ->when($f['category'] ?? null, fn ($q, $c) => $q->whereIn('products.category_id', Options::categoryIds((int) $c)));
    }
}
