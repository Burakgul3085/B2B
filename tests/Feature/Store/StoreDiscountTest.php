<?php

namespace Tests\Feature\Store;

use App\Domain\Store\Cart\CartService;
use App\Domain\Store\Inventory\StoreStockService;
use App\Domain\Store\Mail\StoreMailer;
use App\Domain\Store\Orders\CheckoutService;
use App\Domain\Store\Orders\OrderService;
use App\Domain\Store\Reports\OrderExport;
use App\Domain\Store\Reports\StoreReports;
use App\Domain\Store\Returns\ReturnService;
use App\Http\Controllers\Store\Front\Concerns\CurrentCart;
use App\Models\Store\District;
use App\Models\Store\StoreAdmin;
use App\Models\Store\StoreCart;
use App\Models\Store\StoreDiscount;
use App\Models\Store\StoreDiscountRedemption;
use App\Models\Store\StoreOrder;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreProductVariant;
use App\Models\Store\StoreSetting;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Mağaza M8: kupon ve kampanyalar, indirimin satırlara paylaştırılması, raporlar, yönetici bildirimleri. */
class StoreDiscountTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'http://magaza.localhost';

    private StoreProductVariant $pen;

    private StoreProductVariant $book;

    /** @var list<array{to: string, subject: string}> */
    public array $mails = [];

    protected function setUp(): void
    {
        parent::setUp();
        StoreSetting::put(['is_open' => '1', 'cod_enabled' => '1', 'cod_max' => '5000.00', 'cod_max_open' => '10', 'cod_first_order_approval' => '0', 'shipping_cargo_fee' => '50.00']);

        $this->pen = StoreProduct::create(['name' => 'Jel Kalem', 'slug' => 'jel-kalem', 'is_published' => true])
            ->variants()->create(['sku' => 'JEL-1', 'price' => '100.00', 'vat_rate' => '20', 'critical_stock' => 5]);
        $this->book = StoreProduct::create(['name' => 'Kitap', 'slug' => 'kitap', 'is_published' => true])
            ->variants()->create(['sku' => 'KIT-1', 'price' => '33.33', 'vat_rate' => '10']);
        app(StoreStockService::class)->receive($this->pen, 20);
        app(StoreStockService::class)->receive($this->book, 20);

        $test = $this;
        $this->instance(StoreMailer::class, new class($test) extends StoreMailer
        {
            public function __construct(private $test) {}

            public function send(string $to, string $subject, array $content): bool
            {
                $this->test->mails[] = ['to' => $to, 'subject' => $subject];

                return true;
            }
        });
    }

    private function cart(int $pens = 2, int $books = 1, ?string $coupon = null): StoreCart
    {
        $carts = app(CartService::class);
        $cart = $carts->createGuest();
        $pens && $carts->add($cart, $this->pen->id, $pens);
        $books && $carts->add($cart, $this->book->id, $books);
        $coupon && $cart->update(['coupon_code' => $coupon]);

        return $cart;
    }

    private function input(string $phone = '05321234567', string $email = 'ayse@ornek.com', string $delivery = 'local'): array
    {
        return [
            'email' => $email,
            'address' => ['first_name' => 'Ayşe', 'last_name' => 'Yılmaz', 'phone' => $phone, 'province_id' => 26,
                'district_id' => District::where('province_id', 26)->where('name', 'Tepebaşı')->value('id'), 'address_line' => 'Atatürk Bulvarı No 5 D 2'],
            'billing_type' => 'individual', 'billing_same' => '1', 'delivery_method' => $delivery, 'payment_method' => 'cod', 'consent' => '1',
        ];
    }

    private function order(StoreCart $cart, string $phone = '05321234567', string $email = 'ayse@ornek.com', string $delivery = 'local'): StoreOrder
    {
        $checkout = app(CheckoutService::class);
        $draft = $checkout->validate($this->input($phone, $email, $delivery), null, app(CartService::class)->summary($cart), $cart->coupon_code);

        return $checkout->place($draft, null, $cart, '127.0.0.1', 'test');
    }

    private function coupon(array $attrs = []): StoreDiscount
    {
        return StoreDiscount::create($attrs + ['name' => 'Hoş geldin', 'code' => 'HOSGELDIN10', 'type' => 'percent', 'value' => '10']);
    }

    public function test_admin_creates_and_validates_discounts(): void
    {
        $this->actingAs(StoreAdmin::create(['name' => 'Yönetici', 'email' => 'y@m.local', 'password' => self::PASSWORD]), 'store_admin');
        $base = ['name' => 'Hoş geldin', 'kind' => 'coupon', 'code' => ' hos geldin-10 ', 'type' => 'percent', 'value' => '10', 'is_active' => '1'];

        $this->post(route('store_admin.discounts.store'), ['value' => '150'] + $base)->assertSessionHasErrors('value');
        $this->post(route('store_admin.discounts.store'), ['value' => ''] + $base)->assertSessionHasErrors('value');
        $this->post(route('store_admin.discounts.store'), ['code' => 'İNDİRİM'] + $base)->assertSessionHasErrors('code');
        $this->post(route('store_admin.discounts.store'), ['ends_at' => '2026-01-01T00:00', 'starts_at' => '2026-02-01T00:00'] + $base)->assertSessionHasErrors('ends_at');
        $this->post(route('store_admin.discounts.store'), $base + ['max_discount' => '75,50', 'min_subtotal' => '200'])->assertSessionHasNoErrors();

        $coupon = StoreDiscount::sole();
        $this->assertSame(['HOSGELDIN-10', '10.00', '75.50', '200.00', true], [$coupon->code, $coupon->value, $coupon->max_discount, $coupon->min_subtotal, $coupon->is_active]);
        $this->post(route('store_admin.discounts.store'), $base)->assertSessionHasErrors('code'); // aynı kod
        $this->post(route('store_admin.discounts.store'), ['kind' => 'campaign', 'name' => 'Kargo bedava', 'type' => 'free_shipping', 'min_subtotal' => '300', 'is_active' => '1'])->assertSessionHasNoErrors();
        $this->assertNull(StoreDiscount::where('name', 'Kargo bedava')->value('code'));
        $this->get(route('store_admin.discounts.index'))->assertOk()->assertSee('HOSGELDIN-10')->assertSee('Otomatik kampanya');

        // Kullanılmış kuponun kodu değişmez, kendisi silinmez.
        $order = $this->order($this->cart(3, 0, 'HOSGELDIN-10'));
        $this->assertSame('30.00', $order->discount_total);
        $this->put(route('store_admin.discounts.update', $coupon), ['code' => 'YENI'] + $base)->assertSessionHasErrors('code');
        $this->delete(route('store_admin.discounts.destroy', $coupon))->assertSessionHasErrors('discount');
        $this->get(route('store_admin.discounts.edit', $coupon))->assertOk()->assertSee($order->order_no);
        $this->assertDatabaseHas('audit_logs', ['action' => 'store.discount_created']);
    }

    public function test_discount_is_allocated_to_lines_and_vat_is_on_net_amounts(): void
    {
        $this->coupon(['value' => '15']);
        $order = $this->order($this->cart(2, 1, 'hosgeldin10')); // 200 + 33.33 = 233.33; %15 = 35.00

        $this->assertSame(['233.33', '35.00', '198.33', 'Kupon (HOSGELDIN10)'], [$order->subtotal, $order->discount_total, $order->total, $order->discount_name]);
        [$pen, $book] = $order->items()->get()->all();
        $this->assertSame('35.00', bcadd($pen->discount_amount, $book->discount_amount, 2));
        $this->assertSame(['30.00', '5.00'], [$pen->discount_amount, $book->discount_amount], 'en yakına yuvarlanır');
        // KDV indirimli satır tutarından: (200 − 30) × 20/120 = 28.33; (33.33 − 5) × 10/110 = 2.58
        $this->assertSame(['28.33', '2.58', '30.91'], [$pen->vat_amount, $book->vat_amount, $order->vat_total]);
        $this->assertSame(1, StoreDiscountRedemption::where('status', 'active')->count());

        // İade tutarı indirimli satırdan hesaplanır: 1 kalem = (200 − 30) / 2 = 85.00
        $order->forceFill(['status' => 'delivered', 'delivered_at' => now(), 'payment_status' => 'collected'])->save();
        $return = app(ReturnService::class)->request($order, ['items' => [$pen->id => 1], 'reason' => 'changed_mind', 'iban' => 'TR330006100519786457841326', 'account_name' => 'Ayşe Yılmaz']);
        $this->assertSame('85.00', app(ReturnService::class)->maxRefund($return));
    }

    public function test_large_discount_leaves_every_line_payable(): void
    {
        $this->coupon(['type' => 'fixed', 'value' => '1000']);
        $order = $this->order($this->cart(1, 1, 'HOSGELDIN10'));

        $this->assertSame('133.31', $order->discount_total, 'her satırda 0,01 kalır');
        $this->assertSame(['0.01', '0.01'], $order->items()->get()->map(fn ($i) => bcsub($i->line_total, $i->discount_amount, 2))->all());
    }

    public function test_coupon_conditions_limits_and_void_on_cancel(): void
    {
        $this->coupon(['min_subtotal' => '150', 'usage_limit' => 2, 'per_customer_limit' => 1]);
        $check = fn (StoreCart $cart, $phone = '05321234567', $email = 'ayse@ornek.com') => app(CheckoutService::class)
            ->validate($this->input($phone, $email), null, app(CartService::class)->summary($cart), $cart->coupon_code);

        try {
            $check($this->cart(1, 0, 'HOSGELDIN10'));
            $this->fail('alt tutar');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('150,00', $e->errors()['coupon'][0]);
        }

        $first = $this->order($this->cart(2, 0, 'HOSGELDIN10'));
        try {
            $check($this->cart(2, 0, 'HOSGELDIN10'), '05329990000'); // aynı e-posta
            $this->fail('kişi başı sınır');
        } catch (ValidationException $e) {
            $this->assertSame('Bu kuponu kullanım hakkınız doldu. Kuponu sepetinizden kaldırarak devam edebilirsiniz.', $e->errors()['coupon'][0]);
        }
        $this->order($this->cart(2, 0, 'HOSGELDIN10'), '05329990001', 'b@ornek.com');
        try {
            $check($this->cart(2, 0, 'HOSGELDIN10'), '05329990002', 'c@ornek.com');
            $this->fail('toplam sınır');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('kullanım sınırı doldu', $e->errors()['coupon'][0]);
        }

        // İptal edilen siparişin kullanım hakkı geri döner.
        $this->actingAs(StoreAdmin::create(['name' => 'Yönetici', 'email' => 'y@m.local', 'password' => self::PASSWORD]), 'store_admin');
        app(OrderService::class)->cancel($first, 'Vazgeçti');
        $this->assertSame('void', StoreDiscountRedemption::where('order_id', $first->id)->value('status'));
        $this->assertSame('30.00', $this->order($this->cart(3, 0, 'HOSGELDIN10'), '05329990002', 'c@ornek.com')->discount_total);
    }

    public function test_usage_limit_is_rechecked_when_placing_the_order(): void
    {
        $this->coupon(['usage_limit' => 1]);
        $checkout = app(CheckoutService::class);
        $cartA = $this->cart(2, 0, 'HOSGELDIN10');
        $cartB = $this->cart(2, 0, 'HOSGELDIN10');
        $draftA = $checkout->validate($this->input(), null, app(CartService::class)->summary($cartA), 'HOSGELDIN10');
        $draftB = $checkout->validate($this->input('05329990000', 'b@ornek.com'), null, app(CartService::class)->summary($cartB), 'HOSGELDIN10');

        $checkout->place($draftA, null, $cartA, null, null);
        $this->expectException(DomainException::class);
        try {
            $checkout->place($draftB, null, $cartB, null, null);
        } finally {
            $this->assertSame(1, StoreOrder::count(), 'ikinci sipariş oluşmadı');
            $this->assertSame(18, $this->pen->fresh()->stock_quantity);
        }
    }

    public function test_automatic_campaigns_first_order_and_free_shipping(): void
    {
        StoreDiscount::create(['name' => '250 TL üzeri %5', 'type' => 'percent', 'value' => '5', 'min_subtotal' => '250']);
        StoreDiscount::create(['name' => 'Yaz indirimi', 'type' => 'fixed', 'value' => '20']);
        StoreDiscount::create(['name' => 'İlk siparişe 40 TL', 'type' => 'fixed', 'value' => '40', 'first_order_only' => true]);
        StoreDiscount::create(['name' => 'Gelecek', 'type' => 'fixed', 'value' => '90', 'starts_at' => now()->addDay()]);

        $first = $this->order($this->cart(2, 0));
        $this->assertSame(['İlk siparişe 40 TL', '40.00'], [$first->discount_name, $first->discount_total]);
        $second = $this->order($this->cart(2, 0));
        $this->assertSame(['Yaz indirimi', '20.00'], [$second->discount_name, $second->discount_total], 'ilk sipariş kampanyası artık geçmez');
        $third = $this->order($this->cart(3, 0));
        $this->assertSame(['Yaz indirimi', '20.00'], [$third->discount_name, $third->discount_total], '%5 = 15 < 20');

        // Ücretsiz teslimat kuponu kampanyanın yerine geçer; vazgeçilen kargo ücreti kullanım tutarına yazılır.
        $this->coupon(['code' => 'KARGOBEDAVA', 'type' => 'free_shipping', 'value' => null]);
        StoreSetting::put(['local_delivery_fee' => '30.00']);
        $order = $this->order($this->cart(1, 0, 'KARGOBEDAVA'), '05329990000', 'b@ornek.com');
        $this->assertSame(['0.00', '0.00', '100.00'], [$order->shipping_fee, $order->discount_total, $order->total]);
        $this->assertSame('30.00', StoreDiscountRedemption::where('order_id', $order->id)->value('amount'));
    }

    public function test_cart_coupon_form_and_card_basket_matches_total(): void
    {
        $this->coupon(['value' => '15']);
        $cart = $this->cart(2, 1);
        $this->withCookie(CurrentCart::COOKIE, $cart->token);

        $this->post(self::HOST.'/sepet/kupon', ['code' => 'YOKBOYLE'])->assertSessionHasErrors('code');
        $this->post(self::HOST.'/sepet/kupon', ['code' => 'hosgeldin10'])->assertSessionHasNoErrors();
        $this->assertSame('HOSGELDIN10', $cart->fresh()->coupon_code);
        $this->get(self::HOST.'/sepet')->assertOk()->assertSee('Kupon (HOSGELDIN10)')->assertSee('−₺35,00', false)->assertSee('₺198,33', false);
        $this->get(self::HOST.'/odeme')->assertOk()->assertSee('Kupon (HOSGELDIN10)');

        // Kartla ödeme: iyzico sepet kalemlerinin toplamı ödenecek tutara eşit.
        StoreSetting::put(['card_enabled' => '1']);
        StoreSetting::putSecret('iyzico_api_key', 'k');
        StoreSetting::putSecret('iyzico_secret_key', 's');
        Http::fake(['*' => Http::response(['status' => 'success', 'token' => 't', 'paymentPageUrl' => 'https://sandbox.iyzico.test/odeme'])]);
        $this->post(self::HOST.'/odeme', ['payment_method' => 'card', 'delivery_method' => 'cargo'] + $this->input())->assertRedirect('https://sandbox.iyzico.test/odeme');
        Http::assertSent(function (HttpRequest $r) {
            $sum = array_reduce($r['basketItems'], fn ($s, $i) => bcadd($s, $i['price'], 2), '0.00');

            return $r['price'] === '248.33' && $sum === '248.33'; // 233.33 − 35 + 50 kargo
        });

        $this->delete(self::HOST.'/sepet/kupon')->assertSessionHasNoErrors();
        $this->assertNull($cart->fresh()->coupon_code);
    }

    public function test_reports_export_and_dashboard(): void
    {
        $this->coupon();
        $a = $this->order($this->cart(2, 0, 'HOSGELDIN10')); // 180
        $this->order($this->cart(1, 1), '05329990000', 'b@ornek.com'); // 133.33
        $cancelled = $this->order($this->cart(1, 0), '05329990001', 'c@ornek.com');
        $this->actingAs(StoreAdmin::create(['name' => 'Yönetici', 'email' => 'y@m.local', 'password' => self::PASSWORD]), 'store_admin');
        app(OrderService::class)->cancel($cancelled, 'Test');
        $a->forceFill(['shipping_name' => '=HYPERLINK("http://kotu.example")'])->save();

        $today = now('Europe/Istanbul')->toDateString();
        $s = app(StoreReports::class)->summary($today, $today);
        $this->assertSame([2, '313.33', '20.00', '313.33', 1, 4], [$s['orders'], $s['gross'], $s['discounts'], $s['net'], $s['cancelled'], $s['items']]);
        $top = app(StoreReports::class)->topProducts($today, $today);
        $this->assertSame(['JEL-1', 3, '280.00'], [$top[0]->sku, (int) $top[0]->quantity, bcadd($top[0]->revenue, '0', 2)]);

        $this->get(route('store_admin.reports.index'))->assertOk()->assertSee('₺313,33', false)->assertSee('Kupon (HOSGELDIN10)');
        $this->get(route('store_admin.dashboard'))->assertOk()->assertSee('Bekleyen işler')->assertSee('Bu ay net satış');

        $response = $this->get(route('store_admin.reports.export', ['from' => $today, 'to' => $today]))->assertOk();
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('content-type'));
        $this->assertSame("'=HYPERLINK(\"x\")", OrderExport::text('=HYPERLINK("x")'));
        $path = tempnam(sys_get_temp_dir(), 't').'.xlsx';
        $this->assertSame(3, app(OrderExport::class)->write($path, $today, $today));
        @unlink($path);
    }

    public function test_admin_notifications_for_orders_returns_and_low_stock(): void
    {
        StoreAdmin::create(['name' => 'A', 'email' => 'a@magaza.local', 'password' => self::PASSWORD]);
        StoreAdmin::create(['name' => 'Pasif', 'email' => 'p@magaza.local', 'password' => self::PASSWORD, 'is_active' => false]);

        // Alıcılar ve ayarlar bildirim anında belirlenir; e-postalar yanıttan sonra (burada terminate ile) gider.
        $first = $this->order($this->cart(15, 0)); // stok 20 → 5 (kritik 5)
        StoreSetting::put(['notify_emails' => 'depo@ornek.com']);
        $second = $this->order($this->cart(1, 0), '05329990000', 'b@ornek.com'); // 5 → 4: zaten kritikte, tekrar uyarılmaz
        StoreSetting::put(['notify_orders' => '0']);
        $this->order($this->cart(1, 0), '05329990001', 'c@ornek.com');
        $this->app->terminate();

        $toAdmins = array_values(array_filter($this->mails, fn ($m) => ! str_ends_with($m['to'], '@ornek.com') || $m['to'] === 'depo@ornek.com'));
        $this->assertSame([
            ['to' => 'a@magaza.local', 'subject' => 'Yeni sipariş '.$first->order_no.' — ₺1.500,00'],
            ['to' => 'a@magaza.local', 'subject' => 'Kritik stok uyarısı'],
            ['to' => 'depo@ornek.com', 'subject' => 'Yeni sipariş '.$second->order_no.' — ₺100,00'],
        ], $toAdmins, 'pasif yöneticiye gitmez; kapalı bildirim gönderilmez');

        // Ayar ekranı: adres listesi doğrulanır.
        $this->actingAs(StoreAdmin::firstOrFail(), 'store_admin');
        $this->put(route('store_admin.settings.update'), ['notify_emails' => 'gecersiz, a@b.com'])->assertSessionHasErrors('notify_emails');
        $this->put(route('store_admin.settings.update'), ['notify_emails' => 'A@B.com; c@d.com', 'notify_orders' => '1'])->assertSessionHasNoErrors();
        $this->assertSame(['a@b.com, c@d.com', '1', '0'], [StoreSetting::get('notify_emails'), StoreSetting::get('notify_orders'), StoreSetting::get('notify_returns')]);
    }
}
