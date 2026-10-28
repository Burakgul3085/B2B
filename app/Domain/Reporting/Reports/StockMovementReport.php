<?php

namespace App\Domain\Reporting\Reports;

use App\Domain\Reporting\Report;
use App\Domain\Reporting\ReportContext;
use App\Domain\Support\LocalTime;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Builder;

/** Stok giriş, çıkış, transfer ve sayım hareketleri. */
class StockMovementReport extends Report
{
    public const KINDS = [
        '' => 'Tüm hareketler',
        'in' => 'Girişler',
        'out' => 'Çıkışlar',
        'transfer' => 'Depolar arası transferler',
        'count' => 'Sayım farkları',
        'sale' => 'Satışlar (sipariş onayı)',
    ];

    public function key(): string
    {
        return 'stock-movements';
    }

    public function title(): string
    {
        return 'Stok hareketleri';
    }

    public function description(): string
    {
        return 'Tarih aralığında stok girişleri, çıkışları, transferler ve sayım farkları.';
    }

    public function group(): string
    {
        return 'Stok';
    }

    public function filters(ReportContext $context): array
    {
        return Options::dateFilters() + [
            'kind' => ['label' => 'Hareket', 'type' => 'select', 'options' => self::KINDS],
            'warehouse' => ['label' => 'Depo', 'type' => 'select', 'options' => Options::warehouses()],
            'q' => ['label' => 'Ürün', 'type' => 'text', 'advanced' => true],
        ];
    }

    public function columns(): array
    {
        return [
            'created_at' => ['label' => 'Tarih', 'type' => 'datetime'],
            'warehouse' => ['label' => 'Depo', 'type' => 'text'],
            'sku' => ['label' => 'Ürün kodu', 'type' => 'text'],
            'name' => ['label' => 'Ürün', 'type' => 'text'],
            'type_label' => ['label' => 'Hareket', 'type' => 'text'],
            'quantity' => ['label' => 'Miktar', 'type' => 'qty', 'total' => true],
            'balance_after' => ['label' => 'Sonraki stok', 'type' => 'qty'],
            'note' => ['label' => 'Açıklama', 'type' => 'text', 'default' => false],
        ];
    }

    public function defaultSort(): array
    {
        return ['created_at', 'desc'];
    }

    public function query(array $f, ReportContext $context): Builder
    {
        $types = match ($f['kind'] ?? null) {
            'in' => ['opening', 'increase', 'return_in', 'sale_reversal'],
            'out' => ['decrease', 'sale'],
            'transfer' => ['transfer_in', 'transfer_out'],
            'count' => ['count_adjust', 'manual_adjust'],
            'sale' => ['sale'],
            default => null,
        };
        $case = 'CASE stock_movements.type '.collect(StockMovement::TYPES)->map(fn ($l, $k) => "WHEN '{$k}' THEN '".str_replace("'", "''", $l)."'")->implode(' ').' END';

        return StockMovement::query()
            ->join('warehouses', 'warehouses.id', '=', 'stock_movements.warehouse_id')
            ->join('products', 'products.id', '=', 'stock_movements.product_id')
            ->select('stock_movements.created_at', 'warehouses.name as warehouse', 'products.sku', 'products.name',
                'stock_movements.quantity', 'stock_movements.balance_after', 'stock_movements.note')
            ->selectRaw("{$case} as type_label")
            ->whereBetween('stock_movements.created_at', LocalTime::dayRange($f['from'] ?? LocalTime::now()->startOfMonth()->toDateString(), $f['to'] ?? LocalTime::today()))
            ->when($types, fn ($q) => $q->whereIn('stock_movements.type', $types))
            ->when($f['warehouse'] ?? null, fn ($q, $w) => $q->where('stock_movements.warehouse_id', $w))
            ->when($f['q'] ?? null, fn ($q, $t) => $q->where(fn ($q) => $q->where('products.name', 'like', Options::like($t))->orWhere('products.sku', 'like', Options::like($t))));
    }
}
