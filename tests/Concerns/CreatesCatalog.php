<?php

namespace Tests\Concerns;

use App\Domain\Tenancy\TenantContext;
use App\Models\Business;
use App\Models\Product;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\Warehouse;
use Illuminate\Support\Str;

trait CreatesCatalog
{
    protected function makeProduct(Business $business, array $overrides = [], string $unitCode = 'ADET'): Product
    {
        return app(TenantContext::class)->runAs($business->id, fn () => Product::create($overrides + [
            'sku' => 'SKU-'.Str::upper(Str::random(6)),
            'name' => 'Ürün '.Str::random(4),
            'unit_id' => Unit::where('code', $unitCode)->value('id'),
            'tax_rate_id' => TaxRate::where('rate', '20.00')->value('id'),
            'list_price' => '10.0000',
            'dealer_price' => '8.0000',
            'purchase_price' => '5.0000',
        ])->fresh(['unit']));
    }

    protected function defaultWarehouse(Business $business): Warehouse
    {
        return app(TenantContext::class)->runAs($business->id, fn () => Warehouse::findOrFail($business->settings()->value('default_warehouse_id')));
    }

    protected function inTenant(Business $business, callable $callback): mixed
    {
        return app(TenantContext::class)->runAs($business->id, $callback);
    }
}
