<?php

namespace Tests\Feature\Store;

use App\Domain\Mail\SystemMailer;
use App\Domain\Store\Cart\CartService;
use App\Domain\Store\Inventory\StoreStockService;
use App\Domain\Store\Orders\OrderService;
use App\Domain\Store\Returns\ReturnService;
use App\Http\Controllers\Store\Front\Concerns\CurrentCart;
use App\Models\Store\District;
use App\Models\Store\StoreAdmin;
use App\Models\Store\StoreCarrier;
use App\Models\Store\StoreCart;
use App\Models\Store\StoreOrder;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreProductVariant;
use App\Models\Store\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Havale / EFT ile ödeme: ayarlar, sipariş, yöneticinin onayı, süre dolunca iptal, iade ve iptal kuralları. */
class StoreTransferTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'http://magaza.localhost';

    private const IBAN = 'TR330006100519786457841326';

    /** @var list<array{to: string, subject: string, content: array}> */
    public array $mails = [];

    private StoreProductVariant $pen;

    private StoreCart $cart;

    protected function setUp(): void
    {
        parent::setUp();
        $test = $this;
        $this->instance(SystemMailer::class, new class($test) extends SystemMailer
        {
            public function __construct(private $test) {}

            public function send(string $to, string $subject, array $content): bool
            {
                $this->test->mails[] = compact('to', 'subject', 'content');

                return true;
            }
        });
        StoreSetting::put(['is_open' => '1', 'transfer_enabled' => '1', 'transfer_days' => '3',
            'transfer_accounts' => 'Ziraat Bankası | Yapboz Kırtasiye Ltd. Şti. | '.self::IBAN]);

        $product = StoreProduct::create(['name' => 'Jel Kalem', 'slug' => 'jel-kalem', 'is_published' => true]);
        $this->pen = $product->variants()->create(['sku' => 'JEL-1', 'price' => '100.00', 'vat_rate' => '20']);
        app(StoreStockService::class)->receive($this->pen, 10);
        $this->cart = app(CartService::class)->createGuest();
        app(CartService::class)->add($this->cart, $this->pen->id, 2);
        $this->withCookie(CurrentCart::COOKIE, $this->cart->token);
    }

    private function form(array $override = []): array
    {
        return array_replace_recursive([
            'email' => 'ayse@ornek.com',
            'address' => ['first_name' => 'Ayşe', 'last_name' => 'Yılmaz', 'phone' => '05321234567', 'province_id' => 6,
                'district_id' => District::where('province_id', 6)->where('name', 'Çankaya')->value('id'), 'address_line' => 'Atatürk Bulvarı No 5 D 2'],
            'billing_type' => 'individual', 'billing_same' => '1', 'delivery_method' => 'cargo', 'payment_method' => 'transfer', 'consent' => '1',
        ], $override);
    }

    private function placeOrder(): StoreOrder
    {
        $this->post(self::HOST.'/odeme', $this->form())->assertRedirect();

        return StoreOrder::sole();
    }

    private function actAsAdmin(): void
    {
        $this->flushSession();
        $this->actingAs(StoreAdmin::create(['name' => 'Sipariş Sorumlusu', 'email' => 's@magaza.local', 'password' => self::PASSWORD]), 'store_admin');
    }

    public function test_transfer_order_reserves_stock_and_shows_bank_details(): void
    {
        $this->get(self::HOST.'/odeme')->assertOk()->assertSee('Havale / EFT')->assertSee('3 gün içinde');

        $response = $this->post(self::HOST.'/odeme', $this->form());
        $order = StoreOrder::sole();
        $response->assertRedirect(route('store.order', ['orderNo' => $order->order_no, 'token' => $order->access_token]));

        $this->assertSame(['awaiting_transfer', 'transfer', 'pending'], [$order->status, $order->payment_method, $order->payment_status]);
        $this->assertSame(8, $this->pen->fresh()->stock_quantity, 'stok hemen düşer, başkasına satılmaz');
        $this->assertSame(0, $this->cart->items()->count());
        $this->assertSame('transfer', $order->payments()->sole()->driver);

        $this->get(route('store.order', ['orderNo' => $order->order_no, 'token' => $order->access_token]))->assertOk()
            ->assertSee('Havale / EFT ile ödeme')->assertSee('TR33 0006 1005 1978 6457 8413 26')->assertSee($order->order_no)
            ->assertSee($order->created_at->addDays(3)->timezone('Europe/Istanbul')->format('d.m.Y H:i'));

        $this->app->terminate();
        $mail = collect($this->mails)->firstWhere('to', 'ayse@ornek.com');
        $this->assertStringContainsString('açıklamaya sipariş numaranızı ('.$order->order_no.')', $mail['content']['intro']);
        $this->assertContains(['Ziraat Bankası — Yapboz Kırtasiye Ltd. Şti.', 'TR33 0006 1005 1978 6457 8413 26'], $mail['content']['rows']);
    }

    public function test_transfer_is_refused_when_disabled_or_without_accounts(): void
    {
        StoreSetting::put(['transfer_enabled' => '0']);
        $this->get(self::HOST.'/odeme')->assertOk()->assertDontSee('Havale / EFT');
        $this->post(self::HOST.'/odeme', $this->form())->assertSessionHasErrors(['payment_method' => 'Havale / EFT ile ödeme şu anda kullanılamıyor.']);

        StoreSetting::put(['transfer_enabled' => '1', 'transfer_accounts' => null]);
        $this->post(self::HOST.'/odeme', $this->form())->assertSessionHasErrors('payment_method');
        $this->assertSame(0, StoreOrder::count());
    }

    public function test_admin_confirms_the_transfer_then_order_proceeds(): void
    {
        $order = $this->placeOrder();
        $this->actAsAdmin();
        $this->get(route('store_admin.orders.show', $order))->assertOk()->assertSee('Havale alındı');
        $this->get(route('store_admin.orders.index', ['status' => 'awaiting_transfer']))->assertOk()->assertSee($order->order_no);
        $this->post(route('store_admin.orders.ship', $order))->assertSessionHasErrors('order'); // önce ödeme

        $this->post(route('store_admin.orders.transfer-received', $order))->assertSessionHasNoErrors();
        $this->post(route('store_admin.orders.transfer-received', $order))->assertSessionHasErrors('order'); // iki kez uygulanmaz

        $order->refresh();
        $this->assertSame(['preparing', 'paid'], [$order->status, $order->payment_status]);
        $this->assertNotNull($order->paid_at);
        $this->assertSame('succeeded', $order->payments()->sole()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'store.order_transfer_received', 'entity_id' => $order->id]);
        $this->app->terminate();
        $this->assertTrue(collect($this->mails)->contains(fn ($m) => $m['to'] === 'ayse@ornek.com' && str_starts_with($m['subject'], 'Ödemeniz alındı')));
    }

    public function test_unpaid_transfer_is_cancelled_after_the_deadline_and_stock_returns(): void
    {
        $order = $this->placeOrder();
        $this->travel(2)->days();
        $this->assertSame(0, app(OrderService::class)->expireTransfers(), 'süre dolmadan iptal edilmez');

        $this->travel(1)->days();
        $this->travel(1)->minutes();
        $this->artisan('store:expire-transfers')->assertSuccessful();

        $order->refresh();
        $this->assertSame(['cancelled', 'void'], [$order->status, $order->payment_status]);
        $this->assertSame('Havale / EFT süresi içinde ödeme alınmadı.', $order->cancel_reason);
        $this->assertSame(10, $this->pen->fresh()->stock_quantity);
        $this->assertSame(0, app(OrderService::class)->expireTransfers(), 'bir kez uygulanır');
    }

    public function test_paid_transfer_must_be_refunded_by_hand_before_cancel(): void
    {
        $order = $this->placeOrder();
        $this->actAsAdmin();
        $this->post(route('store_admin.orders.transfer-received', $order));
        $this->get(route('store_admin.orders.show', $order))->assertSee('Havalesi alınmış sipariş: iptal için önce');

        $this->post(route('store_admin.orders.cancel', $order), ['reason' => 'Müşteri vazgeçti'])->assertSessionHasErrors('order');
        $this->assertSame('preparing', $order->fresh()->status);

        $this->post(route('store_admin.orders.refund', $order), ['amount' => '200', 'refund_reason' => 'Sipariş iptali, havale 22.10 dekont 123', 'method' => 'iyzico'])
            ->assertSessionHasNoErrors();
        $this->assertSame('manual', $order->refunds()->sole()->method, 'havalede iade her zaman elle');
        $this->post(route('store_admin.orders.cancel', $order), ['reason' => 'Müşteri vazgeçti'])->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame(['cancelled', 'refunded'], [$order->status, $order->payment_status]);
        $this->assertSame(10, $this->pen->fresh()->stock_quantity);
    }

    public function test_return_of_a_transfer_order_requires_an_iban(): void
    {
        $order = $this->placeOrder();
        $this->actAsAdmin();
        $this->post(route('store_admin.orders.transfer-received', $order));
        $this->post(route('store_admin.orders.ship', $order), ['carrier_id' => StoreCarrier::create(['name' => 'Kargo', 'is_active' => true])->id, 'tracking_number' => 'K1']);
        $this->post(route('store_admin.orders.deliver', $order))->assertSessionHasNoErrors();
        $item = $order->items()->sole();

        try {
            app(ReturnService::class)->request($order->fresh(), ['items' => [$item->id => 1], 'reason' => 'damaged']);
            $this->fail('IBAN zorunlu');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('iban', $e->errors());
        }
        $return = app(ReturnService::class)->request($order->fresh(), ['items' => [$item->id => 1], 'reason' => 'damaged', 'iban' => self::IBAN, 'account_name' => 'Ayşe Yılmaz']);
        $this->assertSame(self::IBAN, $return->refund_iban);
    }

    public function test_settings_validate_bank_accounts(): void
    {
        $this->actAsAdmin();
        $base = ['cod_max_open' => 1, 'return_days' => 14, 'iyzico_mode' => 'sandbox', 'sms_driver' => 'log', 'transfer_days' => 2];

        $this->put(route('store_admin.payments.update'), $base + ['transfer_enabled' => '1', 'transfer_accounts' => ''])->assertSessionHasErrors('transfer_accounts');
        $this->put(route('store_admin.payments.update'), $base + ['transfer_accounts' => 'Ziraat | Yapboz | TR000000000000000000000000'])->assertSessionHasErrors('transfer_accounts');
        $this->put(route('store_admin.payments.update'), $base + ['transfer_accounts' => 'sadece metin'])->assertSessionHasErrors('transfer_accounts');

        $this->put(route('store_admin.payments.update'), $base + ['transfer_enabled' => '1', 'transfer_accounts' => ' Ziraat Bankası | Yapboz Kırtasiye | tr33 0006 1005 1978 6457 8413 26 '.chr(10).chr(10)])
            ->assertSessionHasNoErrors();
        $this->assertSame('Ziraat Bankası | Yapboz Kırtasiye | '.self::IBAN, StoreSetting::get('transfer_accounts'));
        $this->assertSame(['1', '2'], [StoreSetting::get('transfer_enabled'), StoreSetting::get('transfer_days')]);
        $this->get(route('store_admin.payments.edit'))->assertOk()->assertSee('Havale / EFT');
    }
}
