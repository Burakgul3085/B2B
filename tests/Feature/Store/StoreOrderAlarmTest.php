<?php

namespace Tests\Feature\Store;

use App\Domain\Store\Cart\CartService;
use App\Domain\Store\Inventory\StoreStockService;
use App\Domain\Store\Orders\CheckoutService;
use App\Domain\Store\Orders\NewOrderAlerts;
use App\Models\Store\District;
use App\Models\Store\StoreAdmin;
use App\Models\Store\StoreOrder;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreProductVariant;
use App\Models\Store\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Yönetim panelindeki sesli yeni sipariş alarmı: sipariş gelince çalar, Siparişler sayfası açılınca yönetici başına susar. */
class StoreOrderAlarmTest extends TestCase
{
    use RefreshDatabase;

    private StoreProductVariant $pen;

    private StoreAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        StoreSetting::put(['is_open' => '1', 'cod_enabled' => '1', 'cod_max' => '5000.00']);
        $product = StoreProduct::create(['name' => 'Jel Kalem', 'slug' => 'jel-kalem', 'is_published' => true]);
        $this->pen = $product->variants()->create(['sku' => 'JEL-1', 'price' => '50.00', 'vat_rate' => '20']);
        app(StoreStockService::class)->receive($this->pen, 10);
        $this->admin = StoreAdmin::create(['name' => 'Sipariş Sorumlusu', 'email' => 's@magaza.local', 'password' => self::PASSWORD]);
        $this->actingAs($this->admin, 'store_admin');
    }

    private function codOrder(): StoreOrder
    {
        $carts = app(CartService::class);
        $cart = $carts->createGuest();
        $carts->add($cart, $this->pen->id, 1);
        $checkout = app(CheckoutService::class);
        $draft = $checkout->validate([
            'email' => 'musteri@ornek.com',
            'address' => ['first_name' => 'Ayşe', 'last_name' => 'Yılmaz', 'phone' => '05321234567', 'province_id' => 26,
                'district_id' => District::where('province_id', 26)->where('name', 'Tepebaşı')->value('id'), 'address_line' => 'Atatürk Bulvarı No 5 D 2'],
            'billing_type' => 'individual', 'billing_same' => '1', 'delivery_method' => 'local', 'payment_method' => 'cod', 'consent' => '1',
        ], null, $carts->summary($cart));

        return $checkout->place($draft, null, $cart, '127.0.0.1', 'test');
    }

    public function test_new_order_rings_on_every_panel_page_until_the_orders_page_is_opened(): void
    {
        $this->getJson(route('store_admin.orders.alarm'))->assertOk()->assertExactJson(['count' => 0, 'latest' => null]);
        $this->get(route('store_admin.dashboard'))->assertOk()->assertSee('orderAlarm(', false);

        $this->travel(1)->minutes();
        $order = $this->codOrder();
        $this->assertNotNull($order->fresh()->alerted_at);
        $this->getJson(route('store_admin.orders.alarm'))->assertOk()->assertJson(['count' => 1])
            ->assertJsonPath('latest', $order->order_no.' · ₺50,00 · Ayşe Yılmaz');

        // Başka bir yönetici de duyar; Siparişler sayfasını açan yöneticinin alarmı susar, ötekinin sürer.
        $other = StoreAdmin::create(['name' => 'Depo', 'email' => 'd@magaza.local', 'password' => self::PASSWORD]);
        $other->forceFill(['orders_seen_at' => now()->subHour()])->save();
        $this->travel(1)->minutes();
        $this->get(route('store_admin.orders.index'))->assertOk();
        $this->getJson(route('store_admin.orders.alarm'))->assertJson(['count' => 0]);
        $this->assertSame(1, app(NewOrderAlerts::class)->pending($other->fresh())['count']);
    }

    public function test_alarm_can_be_turned_off_and_old_orders_never_ring(): void
    {
        // Duyurulmamış (alarm öncesi) sipariş sayılmaz
        StoreOrder::query()->update(['alerted_at' => null]);
        $this->getJson(route('store_admin.orders.alarm'))->assertJson(['count' => 0]);

        $this->travel(1)->minutes();
        $this->codOrder();
        $this->getJson(route('store_admin.orders.alarm'))->assertJson(['count' => 1]);

        $this->put(route('store_admin.settings.update'), ['order_alarm' => '0', 'notify_orders' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('0', StoreSetting::get('order_alarm'));
        $this->getJson(route('store_admin.orders.alarm'))->assertJson(['count' => 0]);
        $this->get(route('store_admin.dashboard'))->assertOk()->assertDontSee('orderAlarm(', false);
        $this->get(route('store_admin.settings.edit'))->assertOk()->assertSee('Panelde sesli sipariş alarmı')->assertSee('Alarmı dene');
    }

    public function test_alarm_endpoint_needs_a_store_admin(): void
    {
        auth('store_admin')->logout();
        $this->getJson(route('store_admin.orders.alarm'))->assertUnauthorized();
    }
}
