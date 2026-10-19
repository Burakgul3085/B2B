<?php

namespace Tests\Feature\Ordering;

use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrders;
use Tests\TestCase;

/** Bayi Siparişlerim: durum sekmeleri, ürün adıyla arama, detay ve "Tekrar sipariş ver". */
class DealerOrderPagesTest extends TestCase
{
    use CreatesOrders, RefreshDatabase;

    public function test_tabs_count_and_search_finds_orders_by_product_name(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $pen = $this->makeProduct($business, ['name' => 'Jel Kalem Mavi']);
        $paper = $this->makeProduct($business, ['name' => 'Fotokopi Kağıdı']);
        $this->stockUp($business, $pen, '100');
        $this->stockUp($business, $paper, '100');
        $penOrder = $this->placeOrder($business, $rel, [$pen->id => '5']);
        $paperOrder = $this->placeOrder($business, $rel, [$paper->id => '2']);
        $paperOrder->forceFill(['status' => 'cancelled'])->saveQuietly();

        $this->actingAs($rel->dealer->user, 'dealer');
        $page = $this->get(route('dealer.orders.index'))->assertOk()->assertSee($penOrder->order_no)->assertSee($paperOrder->order_no);
        $this->assertSame(['' => 2, 'open' => 1, 'completed' => 0, 'closed' => 1], array_intersect_key($page->viewData('counts'), array_flip(['', 'open', 'completed', 'closed'])));

        $this->get(route('dealer.orders.index', ['status' => 'closed']))->assertOk()->assertSee($paperOrder->order_no)->assertDontSee($penOrder->order_no);
        $this->get(route('dealer.orders.index', ['q' => 'Jel Kalem']))->assertOk()->assertSee($penOrder->order_no)->assertDontSee($paperOrder->order_no);
        $this->get(route('dealer.orders.show', $penOrder))->assertOk()->assertSee('Gönderildi')->assertSee('Tekrar sipariş ver');
        $this->get(route('dealer.orders.show', $paperOrder))->assertOk()->assertSee('Sipariş iptal edildi');
    }

    public function test_reorder_adds_items_to_cart_and_skips_unavailable_products(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $pen = $this->makeProduct($business, ['name' => 'Jel Kalem']);
        $gone = $this->makeProduct($business, ['name' => 'Eski Ürün']);
        $this->stockUp($business, $pen, '100');
        $this->stockUp($business, $gone, '100');
        $order = $this->placeOrder($business, $rel, [$pen->id => '3', $gone->id => '4']);
        $gone->forceFill(['is_active' => false])->saveQuietly();

        $this->actingAs($rel->dealer->user, 'dealer')->post(route('dealer.orders.reorder', $order))
            ->assertRedirect(route('dealer.cart'))->assertSessionHas('status')->assertSessionHasErrors('reorder');

        $cart = Cart::where('dealer_relationship_id', $rel->id)->firstOrFail();
        $this->assertSame(['3.000'], CartItem::where('cart_id', $cart->id)->pluck('quantity')->map(fn ($q) => (string) $q)->all());
        $this->assertSame($pen->id, CartItem::where('cart_id', $cart->id)->value('product_id'));
    }

    public function test_dealer_cannot_reorder_someone_elses_order(): void
    {
        $business = $this->makeBusiness();
        $owner = $this->orderingDealer($business);
        $other = $this->orderingDealer($business);
        $product = $this->makeProduct($business);
        $this->stockUp($business, $product, '10');
        $order = $this->placeOrder($business, $owner, [$product->id => '1']);

        $this->actingAs($other->dealer->user, 'dealer')->post(route('dealer.orders.reorder', $order))->assertNotFound();
    }
}
