<?php

namespace Tests\Feature\Inventory;

use App\Domain\Inventory\InsufficientStock;
use App\Domain\Inventory\StockService;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\Concerns\CreatesCatalog;
use Tests\TestCase;

/** docs/03-is-kurallari.md §2: stok kuralları S1–S7. */
class StockServiceTest extends TestCase
{
    use CreatesCatalog, RefreshDatabase;

    private function stock(): StockService
    {
        return app(StockService::class);
    }

    private function qty(int $warehouseId, int $productId): string
    {
        return (string) (WarehouseStock::where('warehouse_id', $warehouseId)->where('product_id', $productId)->value('quantity') ?? '0.000');
    }

    private function assertInvariant(): void
    {
        // S1: her (depo, ürün) için anlık stok = hareket toplamı.
        $mismatch = DB::select('
            SELECT ws.warehouse_id, ws.product_id FROM warehouse_stock ws
            LEFT JOIN (SELECT warehouse_id, product_id, SUM(quantity) total FROM stock_movements GROUP BY warehouse_id, product_id) m
              ON m.warehouse_id = ws.warehouse_id AND m.product_id = ws.product_id
            WHERE ws.quantity <> COALESCE(m.total, 0)');
        $this->assertSame([], $mismatch, 'Anlık stok hareket toplamıyla eşleşmiyor.');
    }

    public function test_increase_and_decrease_keep_stock_equal_to_movements(): void
    {
        $business = $this->makeBusiness();
        $product = $this->makeProduct($business);
        $warehouse = $this->defaultWarehouse($business);

        $this->inTenant($business, function () use ($product, $warehouse) {
            $this->stock()->adjust($product, $warehouse, 'opening', '100');
            $this->stock()->adjust($product, $warehouse, 'increase', '25');
            $movement = $this->stock()->adjust($product, $warehouse, 'decrease', '40');

            $this->assertSame('85.000', $this->qty($warehouse->id, $product->id));
            $this->assertSame('85.000', (string) $movement->balance_after);
            $this->assertSame('-40.000', (string) $movement->quantity);
        });

        $this->assertInvariant();
    }

    public function test_stock_cannot_go_negative_and_nothing_is_written(): void
    {
        $business = $this->makeBusiness();
        $product = $this->makeProduct($business);
        $warehouse = $this->defaultWarehouse($business);

        $this->inTenant($business, function () use ($product, $warehouse) {
            $this->stock()->adjust($product, $warehouse, 'increase', '5');

            try {
                $this->stock()->adjust($product, $warehouse, 'decrease', '6');
                $this->fail('Yetersiz stokta çıkış yapılabildi.');
            } catch (InsufficientStock $e) {
                $this->assertStringContainsString('Mevcut: 5', $e->getMessage());
            }

            $this->assertSame('5.000', $this->qty($warehouse->id, $product->id));
            $this->assertSame(1, StockMovement::count());
        });
    }

    public function test_database_check_rejects_negative_stock_even_if_code_is_bypassed(): void
    {
        $business = $this->makeBusiness();
        $product = $this->makeProduct($business);
        $warehouse = $this->defaultWarehouse($business);
        $this->inTenant($business, fn () => $this->stock()->adjust($product, $warehouse, 'increase', '1'));

        $this->expectException(QueryException::class);
        DB::table('warehouse_stock')->where('product_id', $product->id)->update(['quantity' => -1]);
    }

    public function test_whole_units_reject_fractions_but_kg_accepts(): void
    {
        $business = $this->makeBusiness();
        $piece = $this->makeProduct($business);
        $kg = $this->makeProduct($business, [], 'KG');
        $warehouse = $this->defaultWarehouse($business);

        $this->inTenant($business, function () use ($piece, $kg, $warehouse) {
            $this->stock()->adjust($kg, $warehouse, 'increase', '2.750');
            $this->assertSame('2.750', $this->qty($warehouse->id, $kg->id));

            $this->expectException(DomainException::class);
            $this->stock()->adjust($piece, $warehouse, 'increase', '1.5');
        });
    }

    public function test_manual_adjustment_requires_a_note(): void
    {
        $business = $this->makeBusiness();
        $product = $this->makeProduct($business);
        $warehouse = $this->defaultWarehouse($business);

        $this->inTenant($business, function () use ($product, $warehouse) {
            $this->stock()->adjust($product, $warehouse, 'manual_adjust', '3', 'Fazla bulundu');
            $this->stock()->adjust($product, $warehouse, 'manual_adjust', '-1', 'Eksik');
            $this->assertSame('2.000', $this->qty($warehouse->id, $product->id));

            $this->expectException(DomainException::class);
            $this->stock()->adjust($product, $warehouse, 'manual_adjust', '1');
        });
    }

    public function test_opening_stock_only_once_per_warehouse(): void
    {
        $business = $this->makeBusiness();
        $product = $this->makeProduct($business);
        $warehouse = $this->defaultWarehouse($business);

        $this->inTenant($business, function () use ($product, $warehouse) {
            $this->stock()->adjust($product, $warehouse, 'opening', '10');
            $this->expectException(DomainException::class);
            $this->stock()->adjust($product, $warehouse, 'opening', '10');
        });
    }

    public function test_same_source_key_cannot_be_applied_twice(): void
    {
        $business = $this->makeBusiness();
        $product = $this->makeProduct($business);
        $warehouse = $this->defaultWarehouse($business);

        $this->inTenant($business, function () use ($product, $warehouse) {
            $this->stock()->adjust($product, $warehouse, 'increase', '10', null, 'test:once');

            try {
                $this->stock()->adjust($product, $warehouse, 'increase', '10', null, 'test:once');
                $this->fail('Aynı işlem iki kez uygulandı.');
            } catch (QueryException) {
            }

            $this->assertSame('10.000', $this->qty($warehouse->id, $product->id));
        });
        $this->assertInvariant();
    }

    public function test_movements_are_append_only(): void
    {
        $business = $this->makeBusiness();
        $product = $this->makeProduct($business);
        $warehouse = $this->defaultWarehouse($business);

        $this->inTenant($business, function () use ($product, $warehouse) {
            $movement = $this->stock()->adjust($product, $warehouse, 'increase', '10');

            $this->expectException(LogicException::class);
            $movement->update(['quantity' => 99]);
        });
    }

    public function test_transfer_is_atomic(): void
    {
        $business = $this->makeBusiness();
        $product = $this->makeProduct($business);
        $main = $this->defaultWarehouse($business);

        $this->inTenant($business, function () use ($product, $main) {
            $second = Warehouse::create(['code' => 'IKINCI', 'name' => 'İkinci Depo']);
            $this->stock()->adjust($product, $main, 'increase', '10');

            try {
                $this->stock()->transfer($product, $main, $second, '11');
                $this->fail('Yetersiz stokla transfer yapıldı.');
            } catch (InsufficientStock) {
            }
            $this->assertSame('10.000', $this->qty($main->id, $product->id));
            $this->assertSame('0.000', $this->qty($second->id, $product->id));
            $this->assertSame(1, StockMovement::count());

            $uuid = $this->stock()->transfer($product, $main, $second, '4');
            $this->assertSame('6.000', $this->qty($main->id, $product->id));
            $this->assertSame('4.000', $this->qty($second->id, $product->id));
            $this->assertSame(2, StockMovement::where('transfer_uuid', $uuid)->count());
        });
        $this->assertInvariant();
    }

    public function test_inactive_warehouse_cannot_receive_movements(): void
    {
        $business = $this->makeBusiness();
        $product = $this->makeProduct($business);

        $this->inTenant($business, function () use ($product) {
            $closed = Warehouse::create(['code' => 'KAPALI', 'name' => 'Kapalı Depo', 'is_active' => false]);
            $this->expectException(DomainException::class);
            $this->stock()->adjust($product, $closed, 'increase', '1');
        });
    }

    public function test_apply_many_writes_nothing_if_one_line_is_short(): void
    {
        $business = $this->makeBusiness();
        $a = $this->makeProduct($business);
        $b = $this->makeProduct($business);
        $warehouse = $this->defaultWarehouse($business);

        $this->inTenant($business, function () use ($a, $b, $warehouse) {
            $this->stock()->adjust($a, $warehouse, 'increase', '10');
            $this->stock()->adjust($b, $warehouse, 'increase', '2');

            try {
                DB::transaction(fn () => $this->stock()->applyMany($warehouse->id, 'sale', [
                    ['product' => $a, 'qty' => '-5', 'source_key' => 'order:1:a'],
                    ['product' => $b, 'qty' => '-3', 'source_key' => 'order:1:b'],
                ]));
                $this->fail('Eksik kalem varken satış uygulandı.');
            } catch (InsufficientStock $e) {
                $this->assertSame($b->id, $e->productId);
            }

            $this->assertSame('10.000', $this->qty($warehouse->id, $a->id));
            $this->assertSame('2.000', $this->qty($warehouse->id, $b->id));
            $this->assertSame(0, StockMovement::where('type', 'sale')->count());
        });
    }

    public function test_count_difference_is_calculated_at_apply_time(): void
    {
        $business = $this->makeBusiness();
        $product = $this->makeProduct($business);
        $warehouse = $this->defaultWarehouse($business);

        $this->inTenant($business, function () use ($product, $warehouse) {
            $this->stock()->adjust($product, $warehouse, 'increase', '20');

            $count = StockCount::create(['warehouse_id' => $warehouse->id]);
            StockCountLine::create(['stock_count_id' => $count->id, 'product_id' => $product->id, 'counted_qty' => '18']);

            // Sayım taslaktayken 5 adet çıkış oldu: uygulama anındaki stok 15, fark +3 olmalı.
            $this->stock()->adjust($product, $warehouse, 'decrease', '5');
            $this->stock()->applyCount($count);

            $line = $count->lines()->first();
            $this->assertSame('15.000', (string) $line->system_qty);
            $this->assertSame('3.000', (string) $line->difference);
            $this->assertSame('18.000', $this->qty($warehouse->id, $product->id));
            $this->assertSame('applied', $count->fresh()->status);

            $this->expectException(DomainException::class);
            $this->stock()->applyCount($count);
        });
        $this->assertInvariant();
    }
}
