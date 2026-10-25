<?php

namespace Tests\Feature\Store;

use App\Domain\Store\Cart\CartService;
use App\Domain\Store\Discounts\DiscountService;
use App\Domain\Store\Inventory\StoreStockService;
use App\Domain\Store\Mail\StoreMailer;
use App\Domain\Store\Orders\CheckoutService;
use App\Http\Controllers\Store\Front\Concerns\CurrentCart;
use App\Models\Store\District;
use App\Models\Store\StoreAdmin;
use App\Models\Store\StoreCart;
use App\Models\Store\StoreCategory;
use App\Models\Store\StoreCustomer;
use App\Models\Store\StoreDiscount;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreProductVariant;
use App\Models\Store\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Kampanyalar sayfası, hedef kitle (üyeler / yeni üyeler), ürün rozetleri, sepet uyarısı ve gelişmiş kampanya formu. */
class StoreCampaignTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'http://magaza.localhost';

    private StoreCategory $pens;

    private StoreProductVariant $pen;

    private StoreProductVariant $book;

    protected function setUp(): void
    {
        parent::setUp();
        StoreSetting::put(['is_open' => '1', 'cod_enabled' => '1', 'cod_max' => '5000.00', 'cod_max_open' => '10', 'cod_first_order_approval' => '0']);
        $this->pens = StoreCategory::create(['name' => 'Kalemler', 'slug' => 'kalemler']);
        $this->pen = StoreProduct::create(['name' => 'Jel Kalem', 'slug' => 'jel-kalem', 'category_id' => $this->pens->id, 'is_published' => true])
            ->variants()->create(['sku' => 'JEL-1', 'price' => '100.00', 'vat_rate' => '20']);
        $this->book = StoreProduct::create(['name' => 'Roman Kitap', 'slug' => 'roman-kitap', 'is_published' => true])
            ->variants()->create(['sku' => 'KIT-1', 'price' => '50.00', 'vat_rate' => '10']);
        app(StoreStockService::class)->receive($this->pen, 50);
        app(StoreStockService::class)->receive($this->book, 50);
        $this->instance(StoreMailer::class, new class extends StoreMailer
        {
            public function __construct() {}

            public function send(string $to, string $subject, array $content): bool
            {
                return true;
            }
        });
    }

    private function customer(int $daysAgo = 0, string $email = 'ayse@ornek.com'): StoreCustomer
    {
        $customer = StoreCustomer::create(['first_name' => 'Ayşe', 'last_name' => 'Kaya', 'email' => $email, 'password' => self::PASSWORD]);
        $customer->forceFill(['created_at' => now()->subDays($daysAgo)])->save();

        return $customer;
    }

    private function cart(int $pens, ?StoreCustomer $customer = null): StoreCart
    {
        $carts = app(CartService::class);
        $cart = $carts->createGuest();
        $carts->add($cart, $this->pen->id, $pens);
        $customer && $cart->update(['customer_id' => $customer->id]);

        return $cart;
    }

    private function evaluate(StoreCart $cart, ?StoreCustomer $customer, ?string $code = null): array
    {
        return app(DiscountService::class)->evaluate(app(CartService::class)->summary($cart), $code, $customer?->email, $customer?->phone, $customer);
    }

    private function input(): array
    {
        return [
            'email' => 'misafir@ornek.com',
            'address' => ['first_name' => 'Ayşe', 'last_name' => 'Kaya', 'phone' => '05321234567', 'province_id' => 26,
                'district_id' => District::where('province_id', 26)->where('name', 'Tepebaşı')->value('id'), 'address_line' => 'Atatürk Bulvarı No 5 D 2'],
            'billing_type' => 'individual', 'billing_same' => '1', 'delivery_method' => 'local', 'payment_method' => 'cod', 'consent' => '1',
        ];
    }

    public function test_new_member_campaign_applies_once_to_recent_members_without_code(): void
    {
        StoreDiscount::create(['name' => 'Hoş geldin', 'type' => 'percent', 'value' => '10', 'audience' => 'new_members', 'new_member_days' => 30]);

        $this->assertNull($this->evaluate($this->cart(2), null)['applied'], 'misafire uygulanmaz');
        $this->assertNull($this->evaluate($this->cart(2), $old = $this->customer(40, 'eski@ornek.com'))['applied'], '30 günden eski üye');

        $member = $this->customer(3);
        $cart = $this->cart(2, $member);
        $this->assertSame('20.00', $this->evaluate($cart, $member)['applied']->amount);

        // İlk siparişte uygulanır, ikincide uygulanmaz.
        $checkout = app(CheckoutService::class);
        $order = $checkout->place($checkout->validate($this->input(), $member, app(CartService::class)->summary($cart), null), $member, $cart, '127.0.0.1', 'test');
        $this->assertSame(['Hoş geldin', '20.00'], [$order->discount_name, $order->discount_total]);
        app(CartService::class)->add($cart, $this->pen->id, 2);
        $this->assertNull($this->evaluate($cart, $member)['applied']);
    }

    public function test_members_only_coupon_requires_login(): void
    {
        StoreDiscount::create(['name' => 'Üye kuponu', 'code' => 'UYE15', 'type' => 'percent', 'value' => '15', 'audience' => 'members']);

        $this->assertStringContainsString('üyelerimize özel', $this->evaluate($this->cart(1), null, 'UYE15')['couponError']);
        $member = $this->customer(400);
        $this->assertSame('15.00', $this->evaluate($this->cart(1, $member), $member, 'UYE15')['applied']->amount);
    }

    public function test_campaigns_page_header_link_and_campaign_product_list(): void
    {
        $this->get(self::HOST.'/')->assertOk()->assertDontSee('href="'.route('store.campaigns').'"', false);

        $shown = StoreDiscount::create(['name' => 'Kalem haftası', 'type' => 'percent', 'value' => '10', 'category_ids' => [$this->pens->id],
            'show_on_page' => true, 'description' => 'Tüm kalemlerde geçerli.']);
        StoreDiscount::create(['name' => 'Gizli kampanya', 'type' => 'fixed', 'value' => '5']);
        StoreDiscount::create(['name' => 'Bitti', 'type' => 'fixed', 'value' => '5', 'show_on_page' => true, 'ends_at' => now()->subDay()]);
        StoreDiscount::create(['name' => 'Toplu kod', 'code' => 'TOPLU', 'codes_only' => true, 'type' => 'fixed', 'value' => '5', 'show_on_page' => true]);
        StoreDiscount::create(['name' => 'Bin lira üzeri', 'code' => 'BIN5', 'type' => 'percent', 'value' => '5', 'min_subtotal' => '1000', 'show_on_page' => true]);

        $this->get(self::HOST.'/')->assertOk()->assertSee('href="'.route('store.campaigns').'"', false);
        $this->get(self::HOST.'/kampanyalar')->assertOk()
            ->assertSee('Kalem haftası')->assertSee('Tüm kalemlerde geçerli.')->assertSee('Seçili ürünlerde %10 indirim')
            ->assertSee('Bin lira üzeri')->assertSee('BIN5')->assertSee('₺1.000,00 ve üzeri alışverişlerde %5 indirim', false)
            ->assertDontSee('Gizli kampanya')->assertDontSee('Bitti')->assertDontSee('TOPLU')
            ->assertSee('/urunler?kampanya='.$shown->id, false);

        $this->get(self::HOST.'/urunler?kampanya='.$shown->id)->assertOk()
            ->assertSee('Kalem haftası')->assertSee('Jel Kalem')->assertDontSee('Roman Kitap');
        $this->get(self::HOST.'/urunler?kampanya=99999')->assertOk()->assertSee('Bu kampanya sona erdi.')->assertDontSee('Jel Kalem');
    }

    public function test_product_badge_and_cart_nudge(): void
    {
        StoreDiscount::create(['name' => 'Kalem haftası', 'type' => 'percent', 'value' => '10', 'category_ids' => [$this->pens->id]]);
        StoreDiscount::create(['name' => 'Büyük sepet', 'type' => 'percent', 'value' => '20', 'min_subtotal' => '1500']);

        $this->get(self::HOST.'/urunler')->assertOk()->assertSee('Sepette %10 indirim');
        $this->get(self::HOST.'/urun/jel-kalem')->assertOk()->assertSee('data-campaign-badge', false)->assertSee('Sepette %10 indirim · Kalem haftası');
        $this->get(self::HOST.'/urun/roman-kitap')->assertOk()->assertDontSee('data-campaign-badge', false);

        // Sepet 1.000 ₺: %10 kampanya uygulanır, 1.500 ₺ koşulu için 500 ₺ daha ekleme uyarısı çıkar.
        $this->post(self::HOST.'/sepet', ['variant_id' => $this->pen->id, 'quantity' => 10])->assertSessionHasNoErrors();
        $this->withCookie(CurrentCart::COOKIE, StoreCart::whereNull('customer_id')->value('token'));
        $this->get(self::HOST.'/sepet')->assertOk()->assertSee('−₺100,00', false)
            ->assertSee('₺500,00', false)->assertSee('Büyük sepet');
    }

    public function test_admin_form_saves_audience_products_and_page_fields_and_copies(): void
    {
        $this->actingAs(StoreAdmin::create(['name' => 'Y', 'email' => 'y@m.local', 'password' => self::PASSWORD]), 'store_admin');

        $this->get(route('store_admin.discounts.create'))->assertOk()->assertSee('Ne tür bir kampanya?')->assertSee('Yeni üyeye hoş geldin');
        $this->getJson(route('store_admin.discounts.lookup', ['q' => 'jel']))->assertOk()->assertJsonFragment(['name' => 'Jel Kalem', 'sku' => 'JEL-1']);

        $this->post(route('store_admin.discounts.store'), ['kind' => 'campaign', 'name' => 'Hoş geldin', 'type' => 'percent', 'value' => '10',
            'audience' => 'new_members', 'new_member_days' => '0', 'is_active' => '1'])->assertSessionHasErrors('new_member_days');
        $this->post(route('store_admin.discounts.store'), ['kind' => 'campaign', 'name' => 'Hoş geldin', 'type' => 'percent', 'value' => '10',
            'audience' => 'new_members', 'new_member_days' => '14', 'product_ids' => [$this->pen->product_id], 'product_skus' => 'KIT-1',
            'show_on_page' => '1', 'description' => 'Yeni üyelere', 'is_active' => '1'])->assertSessionHasNoErrors();

        $discount = StoreDiscount::firstWhere('name', 'Hoş geldin');
        $this->assertSame(['new_members', 14, true, 'Yeni üyelere'], [$discount->audience, $discount->new_member_days, $discount->show_on_page, $discount->description]);
        $this->assertEqualsCanonicalizing([$this->pen->product_id, $this->book->product_id], $discount->product_ids);

        $this->get(route('store_admin.discounts.edit', $discount))->assertOk()->assertSee('Jel Kalem')->assertSee('Roman Kitap');
        $this->get(route('store_admin.discounts.create', ['kopya' => $discount->id]))->assertOk()->assertSee('Hoş geldin (kopya)');
        $this->get(route('store_admin.discounts.index', ['durum' => 'yayinda']))->assertOk()->assertSee('Hoş geldin');
        $this->get(route('store_admin.discounts.index', ['durum' => 'pasif']))->assertOk()->assertDontSee('Hoş geldin</a>', false);
    }
}
