<?php

namespace Tests\Feature\Store;

use App\Domain\Store\Cart\CartService;
use App\Domain\Store\Inventory\StoreStockService;
use App\Domain\Store\Orders\DeliveryRules;
use App\Domain\Store\Orders\OrderService;
use App\Domain\Store\PaymentCodes\PaymentCodeService;
use App\Domain\Store\PaymentCodes\PaymentCodeSettings;
use App\Domain\Store\Returns\ReturnService;
use App\Http\Controllers\Store\Front\Concerns\CurrentCart;
use App\Models\AuditLog;
use App\Models\Store\District;
use App\Models\Store\StoreAdmin;
use App\Models\Store\StoreCart;
use App\Models\Store\StoreOrder;
use App\Models\Store\StorePaymentCode;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreSetting;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Özel ödeme kodu: yönetici tutarı belirler, müşteri kodu arar, sepete ekler ve öder; tek kullanımlık; "Bulamadınız mı?" kutusu. */
class StorePaymentCodeTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'http://magaza.localhost';

    private StoreCart $cart;

    private StoreAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        StoreSetting::put(['is_open' => '1', 'local_hide_cargo' => '0', 'local_min_total' => null, 'local_free_threshold' => null, 'company_title' => 'Yapboz Kırtasiye Ltd. Şti.', 'phone' => '0222 111 22 33', 'whatsapp' => '0532 000 00 00']);
        $this->admin = StoreAdmin::create(['name' => 'Yönetici', 'email' => 'yonetici@ornek.com', 'password' => 'Gizli-Parola-123', 'is_super' => true]);
        $this->cart = app(CartService::class)->createGuest();
        $this->withCookie(CurrentCart::COOKIE, $this->cart->token)->withCredentials();
    }

    private function code(string $amount = '450.00', array $extra = []): StorePaymentCode
    {
        return app(PaymentCodeService::class)->create(['title' => '500 sayfa fotokopi, özel fiyat', 'amount' => $amount, 'vat_rate' => '20', 'days' => '7'] + $extra, $this->admin->id);
    }

    public function test_admin_creates_validates_and_cancels_codes(): void
    {
        $this->actingAs($this->admin, 'store_admin');
        $this->get(route('store_admin.payment-codes.index'))->assertOk()->assertSee('Henüz ödeme kodu yok');

        $this->post(route('store_admin.payment-codes.store'), ['title' => '', 'amount' => '0', 'vat_rate' => '20'])->assertSessionHasErrors(['title', 'amount']);
        $this->post(route('store_admin.payment-codes.store'), ['title' => 'Test', 'amount' => '-5', 'vat_rate' => '18'])->assertSessionHasErrors(['amount', 'vat_rate']);
        $this->assertSame(0, StorePaymentCode::count());

        $this->post(route('store_admin.payment-codes.store'), ['title' => 'Ahmet Bey defter', 'amount' => '1.250,50', 'vat_rate' => '10', 'days' => '3', 'note' => 'Telefonla görüşüldü'])
            ->assertRedirect(route('store_admin.payment-codes.index'));
        $code = StorePaymentCode::firstOrFail();
        $this->assertSame(['1250.50', '10.00', 'active', $this->admin->id], [$code->amount, $code->vat_rate, $code->state(), $code->created_by]);
        $this->assertMatchesRegularExpression('/^['.PaymentCodeService::ALPHABET.']{8}$/', $code->code);
        $this->assertTrue($code->expires_at->isSameDay(now()->addDays(3)));
        $this->get(route('store_admin.payment-codes.index'))->assertSee($code->display())->assertSee('Kod hazır', false)->assertSee('Telefonla görüşüldü')->assertSee('₺1.250,50');

        $this->post(route('store_admin.payment-codes.cancel', $code))->assertSessionHas('status');
        $this->assertSame('cancelled', $code->fresh()->state());
        $this->get(route('store_admin.payment-codes.index', ['durum' => 'cancelled']))->assertSee($code->display());
        $this->get(route('store_admin.payment-codes.index', ['durum' => 'active']))->assertDontSee($code->display());
    }

    public function test_admin_can_deactivate_reactivate_and_delete_unused_codes(): void
    {
        $this->actingAs($this->admin, 'store_admin');
        $code = $this->code();
        app(PaymentCodeService::class)->addToCart($this->cart, $code->code);
        $this->get(route('store_admin.payment-codes.index'))->assertSee('Pasif et')->assertSee('Sil')->assertDontSee('Yeniden aç');

        $this->post(route('store_admin.payment-codes.cancel', $code))->assertSessionHas('status');
        $this->assertSame('cancelled', $code->fresh()->state());
        $this->get(route('store_admin.payment-codes.index'))->assertSee('Pasif')->assertSee('Yeniden aç');
        $this->assertThrows(fn () => app(PaymentCodeService::class)->addToCart(app(CartService::class)->createGuest(), $code->code), DomainException::class, 'kullanıma kapalı');

        $this->post(route('store_admin.payment-codes.reactivate', $code))->assertSessionHas('status');
        $this->assertSame('active', $code->fresh()->state());

        // Pasif etmeden doğrudan silinir; müşterinin sepetinden de kalkar.
        $this->delete(route('store_admin.payment-codes.destroy', $code))->assertSessionHas('status');
        $this->assertModelMissing($code);
        $this->assertSame(0, $this->cart->items()->count());
        $this->assertSame(['store.payment_code_cancelled', 'store.payment_code_reactivated', 'store.payment_code_deleted'],
            AuditLog::where('action', 'like', 'store.payment_code_%')->where('action', '!=', 'store.payment_code_created')->orderBy('id')->pluck('action')->all());
        $this->get(route('store_admin.activity'))->assertSee('Ödeme kodu silindi')->assertSee('Kod '.$code->code);
    }

    public function test_search_finds_code_and_cart_uses_server_amount(): void
    {
        $code = $this->code();
        $this->assertSame($code->code, PaymentCodeService::normalize(' '.strtolower($code->display()).' '));
        $this->assertNull(PaymentCodeService::normalize('ABCDEFG0')); // 0 alfabede yok

        $this->get(self::HOST.'/ara?q='.urlencode(strtolower($code->display())))->assertOk()
            ->assertSee('500 sayfa fotokopi, özel fiyat')->assertSee('₺450,00')->assertSee('Sepete ekle');
        $this->get(self::HOST.'/ara?q=HHHHJJJJ')->assertOk()->assertSee('ödeme koduna benziyor ama bulunamadı');

        // Tarayıcıdan gelen tutar yok sayılır; aynı kod iki kez eklenmez; adet değiştirilemez.
        $this->post(self::HOST.'/odeme-kodu', ['code' => $code->display(), 'amount' => '1', 'price' => '1'])->assertRedirect(route('store.cart'));
        $this->post(self::HOST.'/odeme-kodu', ['code' => $code->code])->assertRedirect(route('store.cart'));
        $this->assertSame(1, $this->cart->items()->count());
        $item = $this->cart->items()->firstOrFail();
        $this->put(self::HOST.'/sepet/'.$item->id, ['quantity' => 5]);
        $this->assertSame(1, $item->fresh()->quantity);

        $summary = app(CartService::class)->summary($this->cart->fresh());
        $this->assertSame(['450.00', '450.00', '0.00', '75.00'], [$summary->subtotal(), $summary->codesSubtotal(), $summary->productsSubtotal(), $summary->vatBreakdown()['20']]);
        $this->get(self::HOST.'/sepet')->assertOk()->assertSee('Özel ödeme: 500 sayfa fotokopi, özel fiyat')->assertSee($code->display())->assertSee('₺450,00');

        $this->post(self::HOST.'/odeme-kodu', ['code' => 'HHHHJJJJ'])->assertSessionHasErrors('payment_code');
    }

    public function test_free_shipping_threshold_counts_the_code_amount(): void
    {
        StoreSetting::put(['shipping_free_threshold' => '1000.00', 'shipping_cargo_fee' => '60.00']);
        $delivery = app(DeliveryRules::class);
        $district = District::where('province_id', 26)->value('id');

        $under = $this->code('999.99');
        app(PaymentCodeService::class)->addToCart($this->cart, $under->code);
        $this->assertSame('60.00', $delivery->options(26, $district, app(CartService::class)->summary($this->cart->fresh())->subtotal())['cargo']);

        $this->cart->items()->delete();
        app(PaymentCodeService::class)->addToCart($this->cart, $this->code('1000.00')->code);
        $this->assertSame('0.00', $delivery->options(26, $district, app(CartService::class)->summary($this->cart->fresh())->subtotal())['cargo']);
    }

    public function test_checkout_claims_code_once_and_failed_order_releases_it(): void
    {
        $product = StoreProduct::create(['name' => 'Jel Kalem', 'slug' => 'jel-kalem', 'is_published' => true]);
        $pen = $product->variants()->create(['sku' => 'JEL-1', 'price' => '100.00', 'vat_rate' => '20']);
        app(StoreStockService::class)->receive($pen, 10);
        app(CartService::class)->add($this->cart, $pen->id, 1);
        $code = $this->code();
        app(PaymentCodeService::class)->addToCart($this->cart, $code->code);

        StoreSetting::put(['cod_enabled' => '1', 'cod_max' => '3000.00', 'cod_fee' => '0.00', 'local_delivery_enabled' => '1', 'local_delivery_fee' => '0.00']);
        $this->get(self::HOST.'/odeme')->assertOk()->assertSee('Özel ödeme: 500 sayfa fotokopi, özel fiyat')->assertSee('₺450,00');
        $this->post(self::HOST.'/odeme', [
            'email' => 'ayse@ornek.com',
            'address' => ['first_name' => 'Ayşe', 'last_name' => 'Yılmaz', 'phone' => '0532 123 45 67', 'province_id' => 26,
                'district_id' => District::where('province_id', 26)->where('name', 'Odunpazarı')->value('id'), 'neighborhood' => 'Arifiye', 'address_line' => 'Hamamyolu Cad. No 12'],
            'billing_type' => 'individual', 'billing_tckn' => '10000000146', 'billing_same' => '1', 'delivery_method' => 'local', 'payment_method' => 'cod', 'consent' => '1',
        ])->assertRedirect();

        $order = StoreOrder::firstOrFail();
        $this->assertSame(['550.00', '550.00'], [$order->subtotal, $order->total]);
        $item = $order->items()->whereNotNull('payment_code_id')->firstOrFail();
        $this->assertSame([null, 'ODEME-'.$code->code, '450.00', '20.00', 'Özel ödeme: 500 sayfa fotokopi, özel fiyat'],
            [$item->variant_id, $item->sku, $item->line_total, (string) $item->vat_rate, $item->product_name]);
        $code->refresh();
        $this->assertSame([$order->id, 'used'], [$code->order_id, $code->state()]);
        $this->assertSame(9, $pen->fresh()->stock_quantity);

        // Kullanılmış kod başka sepete eklenemez, yönetimde iptal edilemez.
        $other = app(CartService::class)->createGuest();
        $this->assertThrows(fn () => app(PaymentCodeService::class)->addToCart($other, $code->code), DomainException::class, 'Bu ödeme kodu kullanıldı.');
        $this->assertThrows(fn () => app(PaymentCodeService::class)->cancel($code), DomainException::class);
        $this->assertThrows(fn () => app(PaymentCodeService::class)->delete($code), DomainException::class, 'siparişte kullanıldığı için silinemez');

        // Ödeme kodu kalemi iade talebine girmez; yalnızca kod içeren siparişte müşteriye mağazayla iletişim söylenir.
        $order->update(['status' => 'delivered', 'delivered_at' => now()]);
        $this->assertNotContains($item->id, array_keys(app(ReturnService::class)->returnableQuantities($order)));
        $order->items()->whereNull('payment_code_id')->delete();
        $this->assertSame(ReturnService::CODE_ONLY, app(ReturnService::class)->refusal($order->fresh()));

        // Sipariş iptal edilirse kod yeniden kullanılabilir.
        $order->update(['status' => 'cancelled']);
        $this->assertSame('active', $code->fresh()->state());
        // Siparişte geçmiş kod iptal edilmiş siparişte de kayıt için kalır.
        $this->assertFalse(PaymentCodeService::deletable($code->fresh()));
        $this->assertThrows(fn () => app(PaymentCodeService::class)->delete($code), DomainException::class);

        $this->actingAs($this->admin, 'store_admin');
        $this->get(route('store_admin.orders.show', $order))->assertOk()->assertSee('Ödeme kodu: tutarı yönetimde belirlendi');
    }

    public function test_claim_refuses_used_cancelled_or_changed_codes(): void
    {
        $service = app(PaymentCodeService::class);
        $code = $this->code();
        $order = fn () => StoreOrder::forceCreate(['order_no' => 'T'.uniqid(), 'access_token' => Str::random(40), 'email' => 'a@b.c', 'phone' => '05320000000',
            'status' => 'pending_payment', 'payment_method' => 'card', 'payment_status' => 'pending', 'delivery_method' => 'cargo',
            'shipping_name' => 'A B', 'shipping_phone' => '05320000000', 'shipping_province_id' => 26, 'shipping_district_id' => District::where('province_id', 26)->value('id'), 'shipping_province' => 'Eskişehir', 'shipping_district' => 'Odunpazarı',
            'shipping_address' => 'Adres', 'billing_type' => 'individual', 'billing_name' => 'A B', 'billing_address' => 'Adres',
            'subtotal' => '450.00', 'shipping_fee' => '0.00', 'cod_fee' => '0.00', 'total' => '450.00', 'vat_total' => '75.00']);

        try {
            $service->claim([$code->id], $order(), [$code->id => '1.00']); // tutar uyuşmuyor
            $this->fail('Tutarı uyuşmayan kod kabul edildi.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('ödeme kodu artık geçerli değil', $e->getMessage());
        }
        $first = $order();
        $service->claim([$code->id], $first, [$code->id => '450.00']);
        $this->assertThrows(fn () => $service->claim([$code->id], $order(), [$code->id => '450.00']), DomainException::class); // ikinci sipariş

        // Ödeme başarısız olursa kod serbest kalır; sepetteki satır yeniden sipariş edilebilir.
        app(OrderService::class)->markPaymentFailed($first, 'Kart reddedildi');
        $this->assertSame('active', $code->fresh()->state());

        $service->addToCart($this->cart, $code->code);
        $code->forceFill(['is_cancelled' => true])->save();
        $summary = app(CartService::class)->summary($this->cart->fresh());
        $this->assertTrue($summary->hasIssues());
        $this->assertSame('0.00', $summary->subtotal());
        $this->get(self::HOST.'/odeme')->assertRedirect(route('store.cart'));
        $this->get(self::HOST.'/sepet')->assertSee('Bu ödeme kodu artık geçerli değil');

        $expired = $this->code('10.00', ['days' => '1']);
        $expired->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->assertSame('expired', $expired->fresh()->state());
        $this->assertThrows(fn () => $service->addToCart($this->cart, $expired->code), DomainException::class, 'süresi doldu');
    }

    public function test_help_box_on_listings_and_settings_are_editable(): void
    {
        $product = StoreProduct::create(['name' => 'Jel Kalem', 'slug' => 'jel-kalem', 'is_published' => true]);
        $product->variants()->create(['sku' => 'JEL-1', 'price' => '100.00', 'vat_rate' => '20']);

        $this->get(self::HOST.'/urunler')->assertOk()->assertSee('Aradığınızı bulamadınız mı?')->assertSee('0222 111 22 33')->assertSee('wa.me/905320000000', false)
            ->assertSee('Ödeme kodunuz mu var?');
        $this->get(self::HOST.'/ara?q=pergel')->assertOk()->assertSee('Aradığınız kriterlere uygun ürün bulunamadı.')->assertSee('Aradığınızı bulamadınız mı?');

        $this->actingAs($this->admin, 'store_admin');
        $this->put(route('store_admin.payment-codes.settings'), ['enabled' => '1', 'item_name' => '', 'box_enabled' => '1', 'box_title' => 'X', 'box_text' => 'Y', 'code_hint' => 'Z'])
            ->assertSessionHasErrors('item_name');
        $this->put(route('store_admin.payment-codes.settings'), ['enabled' => '1', 'item_name' => 'Özel sipariş', 'box_enabled' => '1',
            'box_title' => 'Ürünü bulamadın mı?', 'box_text' => 'Dükkânımızda olabilir, bize yaz.', 'code_hint' => 'Kodu yaz.'])->assertSessionHas('status');
        $this->get(self::HOST.'/urunler')->assertSee('Ürünü bulamadın mı?')->assertSee('Dükkânımızda olabilir, bize yaz.');

        // Kodla ödeme kapalıyken arama kodu tanımaz ve sepetteki kod sipariş edilemez; kutu kapatılabilir.
        $code = $this->code();
        app(PaymentCodeService::class)->addToCart($this->cart, $code->code);
        app(PaymentCodeSettings::class)->save(['enabled' => '0', 'box_enabled' => '0'] + PaymentCodeSettings::DEFAULTS);
        $this->get(self::HOST.'/ara?q='.$code->code)->assertOk()->assertDontSee('₺450,00')->assertDontSee('Aradığınızı bulamadınız mı?');
        $this->assertTrue(app(CartService::class)->summary($this->cart->fresh())->hasIssues());
        $this->assertThrows(fn () => app(PaymentCodeSettings::class)->save(['box_text' => str_repeat('a', 401)] + PaymentCodeSettings::DEFAULTS), ValidationException::class);
    }
}
