<?php

namespace Tests\Feature\Catalog;

use App\Domain\Ordering\CartService;
use App\Domain\Ordering\OrderService;
use App\Models\AuditLog;
use App\Models\CartItem;
use App\Models\DealerProductPrice;
use App\Models\DiscountRule;
use App\Models\OrderItem;
use App\Models\OrderReturnRequest;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\StockMovement;
use App\Models\WarehouseStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrders;
use Tests\TestCase;

/** Stok hareketi görmüş ürünün kalıcı silinmesi (docs/03-is-kurallari.md §1). */
class ProductDeleteTest extends TestCase
{
    use CreatesOrders, RefreshDatabase;

    /** Teslim edilmiş sipariş: 5 × 10,00 + %20 KDV = 60,00. */
    private function completedOrder($business, $rel, Product $product)
    {
        $order = $this->placeOrder($business, $rel, [$product->id => '5']);

        return $this->inTenant($business, function () use ($business, $order) {
            $order = app(OrderService::class)->approve($order, [], $this->defaultWarehouse($business)->id, [], $business->user->id);

            return app(OrderService::class)->complete($order)->fresh('items');
        });
    }

    public function test_product_with_stock_movements_and_delivered_order_is_deleted_and_history_is_kept(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $product = $this->makeProduct($business, ['name' => 'Silinecek Kalem', 'sku' => 'SIL-1', 'dealer_price' => '10.00']);
        $other = $this->makeProduct($business, ['dealer_price' => '10.00']);
        $this->stockUp($business, $product, '100');
        $order = $this->completedOrder($business, $rel, $product);

        $promotion = $this->inTenant($business, function () use ($rel, $product, $other) {
            app(CartService::class)->add($rel, $product->id, '1');
            DealerProductPrice::create(['dealer_relationship_id' => $rel->id, 'product_id' => $product->id, 'price' => '9.00']);
            DiscountRule::create(['name' => 'Kural', 'scope' => 'product', 'product_id' => $product->id, 'target' => 'all', 'percent' => '5']);

            return Promotion::create(['name' => 'Kampanya', 'type' => 'percent', 'value' => '10', 'product_ids' => [$product->id, $other->id]]);
        });

        $this->actingAs($business->user, 'business');
        $this->delete(route('business.products.destroy', $product))
            ->assertRedirect(route('business.products.index'))->assertSessionHasNoErrors();

        $this->inTenant($business, function () use ($product, $other, $order, $promotion) {
            $this->assertNull(Product::find($product->id));
            $this->assertSame(0, StockMovement::where('product_id', $product->id)->count());
            $this->assertSame(0, WarehouseStock::where('product_id', $product->id)->count());
            $this->assertSame(0, CartItem::where('product_id', $product->id)->count());
            $this->assertSame(0, DealerProductPrice::where('product_id', $product->id)->count());
            $this->assertSame(0, DiscountRule::where('product_id', $product->id)->count());
            $this->assertSame([$other->id], $promotion->fresh()->product_ids);

            $item = OrderItem::where('order_id', $order->id)->sole();
            $this->assertNull($item->product_id);
            $this->assertSame(['Silinecek Kalem', 'SIL-1'], [$item->name, $item->sku]);
            $this->assertSame('60.00', (string) $order->fresh()->grand_total);

            $log = AuditLog::where('action', 'product.deleted')->sole();
            $this->assertSame('95.000', (string) $log->old_values['stock']);
        });

        // Sipariş, rapor ve tekrar sipariş silinmiş ürünle çalışmaya devam eder.
        $this->get(route('business.orders.show', $order))->assertOk()->assertSee('Silinecek Kalem');
        $this->get(route('business.reports.show', 'order-items'))->assertOk()->assertSee('SIL-1');
        $this->get(route('business.reports.show', 'top-products'))->assertOk()->assertSee('Silinecek Kalem');
        $this->get(route('business.dashboard'))->assertOk();

        $this->actingAs($rel->dealer->user, 'dealer');
        $this->get(route('dealer.orders.show', $order))->assertOk()->assertSee('Silinecek Kalem');
        $this->post(route('dealer.orders.reorder', $order))->assertRedirect();
    }

    public function test_product_in_open_order_cannot_be_deleted(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $product = $this->makeProduct($business, ['dealer_price' => '10.00']);
        $this->stockUp($business, $product, '10');
        $order = $this->placeOrder($business, $rel, [$product->id => '2']);

        $this->actingAs($business->user, 'business');
        $this->delete(route('business.products.destroy', $product))->assertSessionHasErrors('product');
        $this->assertNotNull($product->fresh());

        // Onaylanmış (hazırlanan) sipariş de açıktır.
        $this->inTenant($business, fn () => app(OrderService::class)->approve($order, [], $this->defaultWarehouse($business)->id, [], $business->user->id));
        $this->delete(route('business.products.destroy', $product))->assertSessionHasErrors('product');
        $this->assertNotNull($product->fresh());
        $this->assertSame(1, $this->inTenant($business, fn () => StockMovement::where('product_id', $product->id)->where('quantity', '<', 0)->count()));
    }

    public function test_product_in_open_return_request_cannot_be_deleted(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $product = $this->makeProduct($business, ['dealer_price' => '10.00']);
        $this->stockUp($business, $product, '10');
        $order = $this->completedOrder($business, $rel, $product);
        $itemId = $order->items->first()->id;

        $this->actingAs($rel->dealer->user, 'dealer');
        $this->post(route('dealer.returns.store', $order), ['qty' => [$itemId => '1'], 'reason' => 'excess'])->assertSessionHasNoErrors();
        $this->assertSame(1, $this->inTenant($business, fn () => OrderReturnRequest::count()));

        $this->actingAs($business->user, 'business');
        $this->delete(route('business.products.destroy', $product))->assertSessionHasErrors('product');
        $this->assertNotNull($product->fresh());
    }

    public function test_return_of_deleted_product_credits_dealer_without_stock_entry(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $product = $this->makeProduct($business, ['dealer_price' => '10.00']);
        $this->stockUp($business, $product, '10');
        $order = $this->completedOrder($business, $rel, $product);
        $itemId = $order->items->first()->id;

        $this->actingAs($business->user, 'business');
        $this->delete(route('business.products.destroy', $product))->assertSessionHasNoErrors();
        $before = $this->balance($business, $rel);

        $this->get(route('business.orders.show', $order))->assertOk()->assertSee('Ürün silindi; iade tutarı yazılır, stoğa alınmaz.');
        $this->post(route('business.orders.returns.store', $order), ['qty' => [$itemId => '2']])->assertSessionHasNoErrors();

        $this->assertSame('24.00', bcsub($before, $this->balance($business, $rel), 2)); // 2 × 10,00 + %20 KDV alacak
        $this->assertSame(0, $this->inTenant($business, fn () => StockMovement::where('product_id', $product->id)->count()));
    }

    public function test_product_of_another_business_returns_404(): void
    {
        $a = $this->makeBusiness();
        $b = $this->makeBusiness();
        $foreign = $this->makeProduct($b);

        $this->actingAs($a->user, 'business')->delete(route('business.products.destroy', $foreign))->assertNotFound();
        $this->assertNotNull($foreign->fresh());
    }
}
