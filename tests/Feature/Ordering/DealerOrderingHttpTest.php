<?php

namespace Tests\Feature\Ordering;

use App\Domain\Ordering\CartService;
use App\Domain\Ordering\ProductRequestService;
use App\Http\Middleware\SetDealerContext;
use App\Models\BusinessModule;
use App\Models\Order;
use App\Models\ProductImage;
use App\Models\ProductRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesOrders;
use Tests\TestCase;

class DealerOrderingHttpTest extends TestCase
{
    use CreatesOrders, RefreshDatabase;

    public function test_catalog_shows_only_orderable_products_with_dealer_price_and_never_purchase_price(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $visible = $this->makeProduct($business, ['name' => 'Görünen Kalem', 'dealer_price' => '7.25', 'purchase_price' => '3.17']);
        $this->makeProduct($business, ['name' => 'Gizli Ürün', 'visible_to_dealers' => false]);
        $this->makeProduct($business, ['name' => 'Pasif Ürün', 'is_active' => false]);
        $this->makeProduct($this->makeBusiness(), ['name' => 'Başka Toptancı Ürünü']);

        $response = $this->actingAs($rel->dealer->user, 'dealer')->get(route('dealer.catalog'));

        $response->assertOk()->assertSee('Görünen Kalem')->assertSee('₺7,25')
            ->assertDontSee('Gizli Ürün')->assertDontSee('Pasif Ürün')->assertDontSee('Başka Toptancı Ürünü')
            ->assertDontSee('3,17')->assertDontSee('3.17');

        $this->get(route('dealer.catalog.show', $visible))->assertOk()->assertDontSee('3,17');
    }

    public function test_out_of_stock_products_are_listed_after_in_stock_ones_in_every_sort(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $this->makeProduct($business, ['name' => 'A Stoksuz Defter']);
        $this->stockUp($business, $this->makeProduct($business, ['name' => 'B Stoklu Kalem']), '10');
        $this->stockUp($business, $this->makeProduct($business, ['name' => 'C Stoklu Silgi']), '5');

        $this->actingAs($rel->dealer->user, 'dealer');
        foreach (['name', 'new', 'stock'] as $sort) {
            $this->get(route('dealer.catalog', ['sort' => $sort]))->assertOk()
                ->assertSeeInOrder($sort === 'stock' ? ['B Stoklu Kalem', 'C Stoklu Silgi', 'A Stoksuz Defter'] : ['Stoklu', 'Stoklu', 'A Stoksuz Defter']);
        }
    }

    public function test_hidden_product_detail_and_image_are_not_accessible(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $hidden = $this->makeProduct($business, ['visible_to_dealers' => false]);
        $image = $this->inTenant($business, fn () => ProductImage::create(['product_id' => $hidden->id, 'path' => 'x.webp', 'is_primary' => true]));

        $this->actingAs($rel->dealer->user, 'dealer');
        $this->get(route('dealer.catalog.show', $hidden))->assertNotFound();
        $this->get(route('dealer.images.show', $image))->assertNotFound();
    }

    public function test_cart_enforces_minimum_and_step_rules(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $product = $this->makeProduct($business, ['min_order_qty' => '12', 'order_step' => '12']);
        $this->actingAs($rel->dealer->user, 'dealer');

        $this->post(route('dealer.cart.add'), ['product_id' => $product->id, 'quantity' => '6'])->assertSessionHasErrors('cart');
        $this->post(route('dealer.cart.add'), ['product_id' => $product->id, 'quantity' => '13'])->assertSessionHasErrors('cart');
        $this->post(route('dealer.cart.add'), ['product_id' => $product->id, 'quantity' => '1,5'])->assertSessionHasErrors('cart');
        $this->post(route('dealer.cart.add'), ['product_id' => $product->id, 'quantity' => '24'])->assertSessionHasNoErrors()->assertRedirect();

        $this->get(route('dealer.cart'))->assertOk()->assertSee('Stokta yok');
    }

    public function test_dealer_places_order_through_http_and_business_approves(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $product = $this->makeProduct($business, ['dealer_price' => '10.00']);
        $this->stockUp($business, $product, '20');

        $this->actingAs($rel->dealer->user, 'dealer');
        $this->post(route('dealer.cart.add'), ['product_id' => $product->id, 'quantity' => '3'])->assertSessionHasNoErrors()->assertRedirect();
        $this->get(route('dealer.cart'))->assertOk()->assertSee('₺36,00');

        $token = (string) Str::uuid();
        $addressId = $this->inTenant($business, fn () => $rel->addresses()->value('id'));
        $this->post(route('dealer.cart.submit'), ['address_id' => $addressId, 'submit_token' => $token, 'note' => 'Sabah teslim'])->assertRedirect();
        $this->post(route('dealer.cart.submit'), ['address_id' => $addressId, 'submit_token' => $token])->assertRedirect();

        $order = $this->inTenant($business, fn () => Order::sole());
        $this->get(route('dealer.orders.show', $order))->assertOk()->assertSee($order->order_no)->assertSee('Sabah teslim');
        $this->get(route('dealer.orders.index'))->assertOk()->assertSee($order->order_no);
        $this->get(route('dealer.dashboard'))->assertOk()->assertSee($order->order_no);

        $this->flushSession();
        $this->actingAs($business->user, 'business');
        $this->get(route('business.orders.index'))->assertOk()->assertSee($order->order_no);
        $this->get(route('business.orders.show', $order))->assertOk()->assertSee('Siparişi onayla')->assertSee('Moda Cd. 1');
        $this->get(route('business.dashboard'))->assertOk()->assertSee($order->order_no);
        $item = $order->items()->first();
        $this->post(route('business.orders.approve', $order), [
            'warehouse_id' => $this->defaultWarehouse($business)->id, 'qty' => [$item->id => '3'], 'delivery_note' => 'Öğleden sonra',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('approved', $order->fresh()->status);
        $this->assertSame('17.000', $this->stockQty($business, $product));
        $this->get(route('business.orders.show', $order))->assertOk()->assertSee('Öğleden sonra');
    }

    public function test_orders_of_other_dealers_and_businesses_are_not_accessible(): void
    {
        $a = $this->makeBusiness();
        $b = $this->makeBusiness();
        $relA1 = $this->orderingDealer($a);
        $relA2 = $this->orderingDealer($a);
        $p = $this->makeProduct($a);
        $this->stockUp($a, $p, '10');
        $order = $this->placeOrder($a, $relA1, [$p->id => '1']);

        // Aynı toptancının başka bayisi
        $this->actingAs($relA2->dealer->user, 'dealer');
        $this->get(route('dealer.orders.show', $order))->assertNotFound();
        $this->post(route('dealer.orders.cancel', $order), ['reason' => 'x'])->assertNotFound();
        $this->get(route('dealer.orders.index'))->assertDontSee($order->order_no);

        // Başka toptancı
        $this->flushSession();
        $this->actingAs($b->user, 'business');
        $this->get(route('business.orders.show', $order))->assertNotFound();
        $this->post(route('business.orders.approve', $order), ['warehouse_id' => 1, 'qty' => []])->assertNotFound();
        $this->assertSame('submitted', $order->fresh()->status);
    }

    public function test_dealer_cannot_touch_cart_items_of_another_dealer(): void
    {
        $business = $this->makeBusiness();
        $rel1 = $this->orderingDealer($business);
        $rel2 = $this->orderingDealer($business);
        $product = $this->makeProduct($business);
        $item = $this->inTenant($business, fn () => app(CartService::class)->add($rel1, $product->id, '2'));

        $this->actingAs($rel2->dealer->user, 'dealer');
        $this->put(route('dealer.cart.update', $item), ['quantity' => '5'])->assertNotFound();
        $this->delete(route('dealer.cart.remove', $item))->assertNotFound();
        $this->assertSame('2.000', (string) $item->fresh()->quantity);
    }

    public function test_cart_and_orders_are_separate_per_wholesaler(): void
    {
        $a = $this->makeBusiness();
        $b = $this->makeBusiness();
        $user = $this->makeDealer();
        $relA = $this->orderingDealer($a, $user);
        $relB = $this->orderingDealer($b, $user);
        $pa = $this->makeProduct($a, ['name' => 'A ürünü']);

        $this->actingAs($user, 'dealer')->withSession([SetDealerContext::SESSION_KEY => $relA->id])
            ->post(route('dealer.cart.add'), ['product_id' => $pa->id, 'quantity' => '1'])->assertSessionHasNoErrors()->assertRedirect();

        // B toptancısı seçiliyken A'nın ürünü sepete eklenemez ve sepet boştur.
        $this->withSession([SetDealerContext::SESSION_KEY => $relB->id])
            ->post(route('dealer.cart.add'), ['product_id' => $pa->id, 'quantity' => '1'])->assertSessionHasErrors('cart');
        $this->withSession([SetDealerContext::SESSION_KEY => $relB->id])->get(route('dealer.cart'))->assertSee('Sepetiniz boş');
    }

    public function test_product_request_flow_and_stock_arrival(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $product = $this->makeProduct($business, ['name' => 'Tükenen Ürün']);
        $this->actingAs($rel->dealer->user, 'dealer');

        $this->get(route('dealer.catalog'))->assertSee('Ürün talebi oluştur');
        $this->post(route('dealer.requests.store'), ['product_id' => $product->id, 'quantity' => '50', 'notify' => '1'])->assertSessionHasNoErrors()->assertRedirect();
        $this->post(route('dealer.requests.store'), ['product_id' => $product->id, 'quantity' => '80', 'notify' => '1'])->assertRedirect();

        $request = $this->inTenant($business, fn () => ProductRequest::sole());
        $this->assertSame('80.000', (string) $request->quantity, 'Açık talep varken yenisi açılmamalı, güncellenmeli.');
        $this->assertSame('0.000', $this->stockQty($business, $product), 'Talep stoğu değiştirmemeli.');

        $this->stockUp($business, $product, '100');
        $this->assertSame('stock_updated', $request->fresh()->status);
        $this->assertSame(0, $this->inTenant($business, fn () => Order::count()), 'Talep siparişe dönüşmemeli.');

        $this->get(route('dealer.dashboard'))->assertSee('Talep ettiğiniz ürünler stoğa girdi')->assertSee('Tükenen Ürün');
    }

    public function test_business_responds_to_product_request(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $product = $this->makeProduct($business);
        $request = $this->inTenant($business, fn () => app(ProductRequestService::class)->create($rel, $product->id, '10', null, true));

        $this->actingAs($business->user, 'business')->get(route('business.product-requests.index'))->assertOk()->assertSee($product->name);
        $this->put(route('business.product-requests.respond', $request), ['status' => 'unavailable', 'response_note' => 'Üretici tedarik edemiyor'])
            ->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('unavailable', $request->fresh()->status);
        $this->flushSession();
        $this->actingAs($rel->dealer->user, 'dealer')->get(route('dealer.requests.index'))->assertSee('Üretici tedarik edemiyor');
    }

    public function test_product_request_module_can_be_disabled(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        BusinessModule::create(['business_id' => $business->id, 'module_key' => 'product_requests', 'enabled' => false]);

        $this->actingAs($rel->dealer->user, 'dealer')->get(route('dealer.requests.index'))->assertForbidden();
        $this->get(route('dealer.catalog'))->assertOk()->assertDontSee('Taleplerim');
    }
}
