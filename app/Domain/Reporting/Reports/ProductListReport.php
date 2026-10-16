<?php

namespace App\Domain\Reporting\Reports;

use App\Domain\Reporting\Report;
use App\Domain\Reporting\ReportContext;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

class ProductListReport extends Report
{
    public function key(): string
    {
        return 'products';
    }

    public function title(): string
    {
        return 'Ürün ve fiyat listesi';
    }

    public function description(): string
    {
        return 'Ürünler, liste/bayi/alış fiyatları, KDV ve toplam stok.';
    }

    public function group(): string
    {
        return 'Ürün';
    }

    public function filters(ReportContext $context): array
    {
        return [
            'q' => ['label' => 'Ara (ad, kod, barkod)', 'type' => 'text'],
            'category' => ['label' => 'Kategori', 'type' => 'select', 'options' => Options::categories()],
            'brand' => ['label' => 'Marka', 'type' => 'select', 'options' => Options::brands()],
            'status' => ['label' => 'Durum', 'type' => 'select', 'options' => ['' => 'Aktif ve pasif', 'active' => 'Aktif', 'passive' => 'Pasif'], 'advanced' => true],
        ];
    }

    public function columns(): array
    {
        return [
            'sku' => ['label' => 'Ürün kodu', 'type' => 'text'],
            'name' => ['label' => 'Ürün', 'type' => 'text'],
            'category' => ['label' => 'Kategori', 'type' => 'text'],
            'brand' => ['label' => 'Marka', 'type' => 'text'],
            'unit' => ['label' => 'Birim', 'type' => 'text', 'default' => false],
            'list_price' => ['label' => 'Liste fiyatı', 'type' => 'money'],
            'dealer_price' => ['label' => 'Bayi fiyatı', 'type' => 'money'],
            'purchase_price' => ['label' => 'Alış fiyatı', 'type' => 'money', 'default' => false],
            'vat_rate' => ['label' => 'KDV', 'type' => 'percent'],
            'stock' => ['label' => 'Toplam stok', 'type' => 'qty', 'total' => true],
            'status' => ['label' => 'Durum', 'type' => 'text'],
        ];
    }

    public function defaultSort(): array
    {
        return ['name', 'asc'];
    }

    public function query(array $f, ReportContext $context): Builder
    {
        return Product::query()->notArchived()
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('brands', 'brands.id', '=', 'products.brand_id')
            ->join('units', 'units.id', '=', 'products.unit_id')
            ->join('tax_rates', 'tax_rates.id', '=', 'products.tax_rate_id')
            ->select('products.sku', 'products.name', 'categories.name as category', 'brands.name as brand', 'units.name as unit',
                'products.list_price', 'products.dealer_price', 'products.purchase_price', 'tax_rates.rate as vat_rate')
            ->selectRaw('(SELECT COALESCE(SUM(ws.quantity), 0) FROM warehouse_stock ws WHERE ws.product_id = products.id) as stock')
            ->selectRaw("CASE WHEN products.is_active = 1 THEN 'Aktif' ELSE 'Pasif' END as status")
            ->when($f['q'] ?? null, fn ($q, $t) => $q->where(fn ($q) => $q->where('products.name', 'like', Options::like($t))->orWhere('products.sku', 'like', Options::like($t))->orWhere('products.barcode', $t)))
            ->when($f['category'] ?? null, fn ($q, $c) => $q->whereIn('products.category_id', Options::categoryIds((int) $c)))
            ->when($f['brand'] ?? null, fn ($q, $b) => $q->where('products.brand_id', $b))
            ->when(($f['status'] ?? null) === 'active', fn ($q) => $q->where('products.is_active', true))
            ->when(($f['status'] ?? null) === 'passive', fn ($q) => $q->where('products.is_active', false));
    }
}
