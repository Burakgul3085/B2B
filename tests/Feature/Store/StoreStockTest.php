<?php

namespace Tests\Feature\Store;

use App\Domain\Store\Inventory\InsufficientStoreStock;
use App\Domain\Store\Inventory\StoreStockService;
use App\Models\AuditLog;
use App\Models\Store\StoreAdmin;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreProductVariant;
use App\Models\Store\StoreStockMovement;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

/** Mağaza stoğu (M3): hareketler, sayım, düzeltme, ters kayıt, ayırma ve satışa çevirme. */
class StoreStockTest extends TestCase
{
    use RefreshDatabase;

    private StoreStockService $stock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stock = app(StoreStockService::class);
        $this->actingAs(StoreAdmin::create(['name' => 'Depocu', 'email' => 'depo@magaza.local', 'password' => self::PASSWORD]), 'store_admin');
    }

    private function variant(string $sku = 'KLM-1', ?StoreProduct $product = null): StoreProductVariant
    {
        $product ??= StoreProduct::create(['name' => 'Kalem '.$sku, 'slug' => strtolower($sku)]);

        return $product->variants()->create(['sku' => $sku, 'price' => '10.00', 'vat_rate' => '20'])->fresh();
    }

    private function assertLedgerConsistent(StoreProductVariant $variant): void
    {
        $this->assertSame((int) StoreStockMovement::where('variant_id', $variant->id)->sum('quantity'), $variant->fresh()->stock_quantity);
    }

    public function test_receive_count_and_adjust_through_the_panel(): void
    {
        $v = $this->variant();

        $this->post(route('store_admin.stock.receive', $v), ['quantity' => 50, 'note' => 'İrsaliye 12'])->assertSessionHasNoErrors();
        $this->post(route('store_admin.stock.count', $v), ['actual' => 47])->assertSessionHasNoErrors();
        $this->post(route('store_admin.stock.count', $v), ['actual' => 47])->assertSessionHas('status', 'Sayılan miktar mevcut stokla aynı; değişiklik yapılmadı.');
        $this->post(route('store_admin.stock.adjust', $v), ['direction' => 'out', 'quantity' => 2, 'note' => ''])->assertSessionHasErrors('note');
        $this->post(route('store_admin.stock.adjust', $v), ['direction' => 'out', 'quantity' => 2, 'note' => 'Hasarlı'])->assertSessionHasNoErrors();
        $this->post(route('store_admin.stock.adjust', $v), ['direction' => 'out', 'quantity' => 99, 'note' => 'Kayıp'])->assertSessionHasErrors('stock');

        $this->assertSame(45, $v->fresh()->stock_quantity);
        $this->assertSame([['receipt', 50, 50], ['count', -3, 47], ['adjustment', -2, 45]],
            StoreStockMovement::orderBy('id')->get()->map(fn ($m) => [$m->type, $m->quantity, $m->balance_after])->all());
        $this->assertSame(['store_admin'], StoreStockMovement::distinct()->pluck('actor_type')->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'store.stock_counted', 'entity_id' => $v->id]);
        $this->assertLedgerConsistent($v);

        $this->get(route('store_admin.stock.index'))->assertOk()->assertSee('KLM-1');
        $this->get(route('store_admin.stock.movements', ['variant' => $v->id]))->assertOk()->assertSee('İrsaliye 12')->assertSee('Hasarlı');
    }

    public function test_manual_movements_are_reversed_once_and_never_edited(): void
    {
        $v = $this->variant();
        $receipt = $this->stock->receive($v, 10);

        $this->post(route('store_admin.stock.reverse', $receipt))->assertSessionHasNoErrors();
        $this->post(route('store_admin.stock.reverse', $receipt))->assertSessionHasErrors('stock');
        $this->assertSame(0, $v->fresh()->stock_quantity);
        $this->assertSame($receipt->id, StoreStockMovement::where('type', 'reversal')->value('reverses_id'));

        // Satış elle geri alınamaz.
        $this->stock->receive($v, 5);
        $sale = $this->stock->sell([$v->id => 2], 'order:1', 'order', 1)->first();
        $this->post(route('store_admin.stock.reverse', $sale))->assertSessionHasErrors('stock');

        $this->expectException(LogicException::class);
        $sale->update(['quantity' => -1]);
    }

    public function test_movements_cannot_be_deleted(): void
    {
        $movement = $this->stock->receive($this->variant(), 3);

        $this->expectException(LogicException::class);
        $movement->delete();
    }

    public function test_holds_reduce_available_stock_and_convert_to_sales(): void
    {
        $v = $this->variant();
        $this->stock->receive($v, 10);

        $this->stock->hold('checkout:a', [$v->id => 7]);
        $this->assertSame([$v->id => 3], $this->stock->available([$v->id]));

        try {
            $this->stock->hold('checkout:b', [$v->id => 4]);
            $this->fail('Satılabilirden fazlası ayrılamamalı.');
        } catch (InsufficientStoreStock $e) {
            $this->assertSame([$v->id => 3], $e->available);
        }

        // Aynı sahip ayırmasını değiştirebilir (sepet güncellendi).
        $this->stock->hold('checkout:a', [$v->id => 5]);
        $this->assertSame([$v->id => 5], $this->stock->available([$v->id]));

        // Elle azaltma ayrılmış miktarın altına inemez.
        $this->post(route('store_admin.stock.count', $v), ['actual' => 4])->assertSessionHasErrors('stock');

        $sales = $this->stock->convert('checkout:a', 'order', 42);
        $this->assertSame([[-5, 5]], $sales->map(fn ($m) => [$m->quantity, $m->balance_after])->all());
        $this->assertSame(5, $this->stock->convert('checkout:a', 'order', 42)->first()->balance_after, 'ikinci çağrı yeni hareket yazmaz');
        $this->assertSame(1, StoreStockMovement::where('type', 'sale')->count());
        $this->assertSame([$v->id => 5], $this->stock->available([$v->id]));
        $this->assertLedgerConsistent($v);
    }

    public function test_expired_and_released_holds_do_not_block_stock(): void
    {
        $v = $this->variant();
        $this->stock->receive($v, 4);

        $this->stock->hold('checkout:a', [$v->id => 4], minutes: 15);
        $this->travel(16)->minutes();
        $this->assertSame([$v->id => 4], $this->stock->available([$v->id]));

        // Süresi dolmuş ayırma, stok hâlâ yetiyorsa satışa çevrilir.
        $this->stock->convert('checkout:a', 'order', 1);
        $this->assertSame(0, $v->fresh()->stock_quantity);

        $this->stock->receive($v, 2);
        $this->stock->hold('checkout:b', [$v->id => 2]);
        $this->stock->release('checkout:b');
        $this->assertSame([$v->id => 2], $this->stock->available([$v->id]));
        $this->expectException(DomainException::class);
        $this->stock->convert('checkout:b', 'order', 2);
    }

    public function test_sale_is_all_or_nothing_and_restock_is_idempotent(): void
    {
        $a = $this->variant('A-1');
        $b = $this->variant('B-1');
        $this->stock->receive($a, 5);
        $this->stock->receive($b, 1);

        try {
            $this->stock->sell([$a->id => 2, $b->id => 3], 'order:9', 'order', 9);
            $this->fail('Yetersiz stokta satış yazılmamalı.');
        } catch (InsufficientStoreStock $e) {
            $this->assertSame([$b->id => 1], $e->available);
        }
        $this->assertSame(0, StoreStockMovement::where('type', 'sale')->count());
        $this->assertSame(5, $a->fresh()->stock_quantity);

        $this->stock->sell([$a->id => 2], 'order:10', 'order', 10);
        $this->stock->restock($a->id, 2, 'order:10:cancel:'.$a->id, 'order', 10);
        $this->stock->restock($a->id, 2, 'order:10:cancel:'.$a->id, 'order', 10);
        $this->assertSame(5, $a->fresh()->stock_quantity);
        $this->assertLedgerConsistent($a);
    }

    public function test_products_with_stock_history_are_deleted_with_their_movements(): void
    {
        $product = StoreProduct::create(['name' => 'Jel Kalem', 'slug' => 'jel', 'variant_name' => 'Renk']);
        $blue = $product->variants()->create(['sku' => 'J-M', 'option_value' => 'Mavi', 'price' => '10', 'vat_rate' => '20']);
        $black = $product->variants()->create(['sku' => 'J-S', 'option_value' => 'Siyah', 'price' => '10', 'vat_rate' => '20']);
        $this->stock->receive($blue, 3);
        $this->stock->receive($black, 4);

        // Seçenek, stok hareketleriyle birlikte silinir; diğer seçeneğin hareketleri kalır.
        $this->delete(route('store_admin.variants.destroy', $blue))->assertSessionHasNoErrors();
        $this->assertSame([0, 1], [StoreStockMovement::where('variant_id', $blue->id)->count(), StoreStockMovement::where('variant_id', $black->id)->count()]);

        $this->delete(route('store_admin.products.destroy', $product))->assertSessionHasNoErrors()->assertRedirect(route('store_admin.products.index'));
        $this->assertFalse(StoreProduct::whereKey($product->id)->exists());
        $this->assertSame(0, StoreStockMovement::count());
        $this->assertSame(4, AuditLog::where('action', 'store.product_deleted')->firstOrFail()->old_values['stock']);
    }

    public function test_critical_stock_is_set_on_the_variant_and_listed(): void
    {
        $v = $this->variant();
        $this->put(route('store_admin.variants.update', $v), ['sku' => 'KLM-1', 'price' => '10', 'vat_rate' => '20', 'critical_stock' => '5', 'is_active' => '1'])->assertSessionHasNoErrors();
        $this->stock->receive($v, 4);

        $this->assertTrue($v->fresh()->isCritical());
        $this->get(route('store_admin.stock.index', ['status' => 'critical']))->assertOk()->assertSee('KLM-1');
        $this->get(route('store_admin.stock.index', ['status' => 'out']))->assertOk()->assertDontSee('KLM-1');
    }
}
