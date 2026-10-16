<?php

namespace App\Domain\Reporting\Reports;

use App\Domain\Pricing\PriceResolver;
use App\Domain\Pricing\ResolvedPrice;
use App\Domain\Reporting\Report;
use App\Domain\Reporting\ReportContext;
use App\Models\DealerRelationship;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Seçilen bayinin göreceği fiyat listesi (öncelik kurallarıyla hesaplanmış). */
class DealerPriceReport extends Report
{
    public function key(): string
    {
        return 'dealer-prices';
    }

    public function title(): string
    {
        return 'Bayiye özel fiyat listesi';
    }

    public function description(): string
    {
        return 'Seçilen bayinin bugün göreceği fiyatlar: kaynak, indirim ve KDV dahil fiyat.';
    }

    public function group(): string
    {
        return 'Ürün';
    }

    public function filters(ReportContext $context): array
    {
        return [
            'dealer' => ['label' => 'Bayi', 'type' => 'select', 'options' => Options::dealers()],
            'category' => ['label' => 'Kategori', 'type' => 'select', 'options' => Options::categories()],
        ];
    }

    public function missingRequirement(array $filters): ?string
    {
        return empty($filters['dealer']) ? 'Fiyat listesini görmek için bir bayi seçin.' : null;
    }

    public function columns(): array
    {
        return [
            'sku' => ['label' => 'Ürün kodu', 'type' => 'text'],
            'name' => ['label' => 'Ürün', 'type' => 'text'],
            'list_net' => ['label' => 'Liste (net)', 'type' => 'money', 'sortable' => false],
            'source' => ['label' => 'Fiyat kaynağı', 'type' => 'text', 'sortable' => false],
            'discount' => ['label' => 'İndirim', 'type' => 'percent', 'sortable' => false],
            'unit_net' => ['label' => 'Bayi fiyatı (net)', 'type' => 'money', 'sortable' => false],
            'unit_gross' => ['label' => 'KDV dahil', 'type' => 'money', 'sortable' => false],
        ];
    }

    public function defaultSort(): array
    {
        return ['name', 'asc'];
    }

    public function query(array $f, ReportContext $context): Builder
    {
        return Product::query()->notArchived()->where('is_active', true)->where('visible_to_dealers', true)
            ->select('products.id', 'products.sku', 'products.name')
            ->when($f['category'] ?? null, fn ($q, $c) => $q->whereIn('products.category_id', Options::categoryIds((int) $c)));
    }

    public function transform(Collection $rows, array $filters, ReportContext $context): Collection
    {
        $relationship = DealerRelationship::find($filters['dealer'] ?? 0);
        if (! $relationship || $rows->isEmpty()) {
            return $rows;
        }
        $prices = app(PriceResolver::class)->resolveMany($relationship, Product::with('taxRate')->whereIn('id', $rows->pluck('id'))->get());

        return $rows->map(function ($row) use ($prices) {
            /** @var ResolvedPrice $p */
            $p = $prices[$row->id];
            $row->list_net = $p->listPrice;
            $row->source = $p->sourceLabel();
            $row->discount = $p->discountPercent;
            $row->unit_net = $p->unitPrice;
            $row->unit_gross = $p->unitPriceWithTax();

            return $row;
        });
    }
}
