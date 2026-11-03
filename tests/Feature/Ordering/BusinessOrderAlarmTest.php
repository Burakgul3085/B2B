<?php

namespace Tests\Feature\Ordering;

use App\Models\BusinessSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrders;
use Tests\TestCase;

/** Toptancı panelinde sesli yeni bayi siparişi alarmı (App\Domain\Ordering\NewOrderAlerts). */
class BusinessOrderAlarmTest extends TestCase
{
    use CreatesOrders, RefreshDatabase;

    public function test_new_dealer_order_rings_until_orders_page_is_opened(): void
    {
        $business = $this->makeBusiness();
        $other = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $product = $this->makeProduct($business, ['dealer_price' => '10.00']);
        $this->stockUp($business, $product, '100');
        $business->user->forceFill(['orders_seen_at' => now()->subMinute()])->save();

        $this->actingAs($business->user, 'business');
        $this->getJson(route('business.orders.alarm'))->assertOk()->assertExactJson(['count' => 0, 'latest' => null]);
        $this->get(route('business.dashboard'))->assertOk()->assertSee('orderAlarm(', false);

        $order = $this->placeOrder($business, $rel, [$product->id => '2']);
        $this->getJson(route('business.orders.alarm'))->assertOk()
            ->assertJson(['count' => 1, 'latest' => $order->order_no.' · ₺24,00 · '.$rel->dealer->legal_name]);

        // Başka işletmenin toptancısı bu siparişten etkilenmez.
        $other->user->forceFill(['orders_seen_at' => now()->subMinute()])->save();
        $this->flushSession();
        $this->actingAs($other->user, 'business')->getJson(route('business.orders.alarm'))->assertJson(['count' => 0]);

        // Siparişler sayfası açılınca alarm susar.
        $this->flushSession();
        $this->actingAs($business->user, 'business');
        $this->travel(1)->seconds();
        $this->get(route('business.orders.index'))->assertOk();
        $this->getJson(route('business.orders.alarm'))->assertJson(['count' => 0]);
    }

    public function test_alarm_can_be_turned_off_in_settings(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $product = $this->makeProduct($business);
        $this->stockUp($business, $product, '10');
        $business->user->forceFill(['orders_seen_at' => now()->subMinute()])->save();
        $this->placeOrder($business, $rel, [$product->id => '1']);

        $this->actingAs($business->user, 'business');
        $this->getJson(route('business.orders.alarm'))->assertJson(['count' => 1]);
        $this->get(route('business.settings.edit'))->assertOk()->assertSee('Panelde sesli sipariş alarmı')->assertSee('Alarmı dene');

        $settings = BusinessSetting::where('business_id', $business->id)->first();
        $this->put(route('business.settings.update'), [
            'display_name' => $business->display_name ?? 'Toptancı', 'stock_display' => $settings->stock_display, 'ledger_post_on' => $settings->ledger_post_on,
            'default_payment_term_days' => $settings->default_payment_term_days, 'price_display' => $settings->price_display, 'order_alarm' => '0',
        ])->assertSessionHasNoErrors();

        $this->assertFalse((bool) BusinessSetting::where('business_id', $business->id)->value('order_alarm'));
        $this->getJson(route('business.orders.alarm'))->assertExactJson(['count' => 0, 'latest' => null]);
        $this->get(route('business.dashboard'))->assertOk()->assertDontSee('orderAlarm(', false);
    }
}
