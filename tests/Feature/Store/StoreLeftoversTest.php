<?php

namespace Tests\Feature\Store;

use App\Domain\Store\Cart\CartService;
use App\Domain\Store\Inventory\StoreStockService;
use App\Domain\Store\Orders\CheckoutService;
use App\Domain\Store\Returns\ReturnService;
use App\Models\Store\District;
use App\Models\Store\StoreAdmin;
use App\Models\Store\StoreCart;
use App\Models\Store\StoreCategory;
use App\Models\Store\StoreDiscount;
use App\Models\Store\StoreDiscountCode;
use App\Models\Store\StoreOrder;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreProductVariant;
use App\Models\Store\StoreRefund;
use App\Models\Store\StoreReturn;
use App\Models\Store\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Mağaza küçük eksikleri: müşteri adına iade, iadesiz para iadesi, iade faturası, kapsamlı kupon, toplu kodlar. */
class StoreLeftoversTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'http://magaza.localhost';

    private StoreProductVariant $pen;

    private StoreProductVariant $book;

    private StoreCategory $child;

    protected function setUp(): void
    {
        parent::setUp();
        StoreSetting::put(['is_open' => '1', 'cod_enabled' => '1', 'cod_max' => '5000.00', 'cod_max_open' => '10', 'cod_first_order_approval' => '0']);
        $parent = StoreCategory::create(['name' => 'Yazı Gereçleri', 'slug' => 'yazi-gerecleri']);
        $this->child = StoreCategory::create(['name' => 'Kalemler', 'slug' => 'kalemler', 'parent_id' => $parent->id]);
        $this->pen = StoreProduct::create(['name' => 'Jel Kalem', 'slug' => 'jel-kalem', 'is_published' => true, 'category_id' => $this->child->id])
            ->variants()->create(['sku' => 'JEL-1', 'price' => '100.00', 'vat_rate' => '20']);
        $this->book = StoreProduct::create(['name' => 'Kitap', 'slug' => 'kitap', 'is_published' => true])
            ->variants()->create(['sku' => 'KIT-1', 'price' => '50.00', 'vat_rate' => '10']);
        app(StoreStockService::class)->receive($this->pen, 50);
        app(StoreStockService::class)->receive($this->book, 50);
        $this->actingAs(StoreAdmin::create(['name' => 'Yönetici', 'email' => 'y@m.local', 'password' => self::PASSWORD]), 'store_admin');
    }

    private function cart(int $pens, int $books, ?string $coupon = null): StoreCart
    {
        $carts = app(CartService::class);
        $cart = $carts->createGuest();
        $pens && $carts->add($cart, $this->pen->id, $pens);
        $books && $carts->add($cart, $this->book->id, $books);
        $coupon && $cart->update(['coupon_code' => $coupon]);

        return $cart;
    }

    private function codOrder(StoreCart $cart, string $phone = '05321234567', string $email = 'ayse@ornek.com'): StoreOrder
    {
        $checkout = app(CheckoutService::class);

        return $checkout->place($checkout->validate([
            'email' => $email,
            'address' => ['first_name' => 'Ayşe', 'last_name' => 'Yılmaz', 'phone' => $phone, 'province_id' => 26,
                'district_id' => District::where('province_id', 26)->where('name', 'Tepebaşı')->value('id'), 'address_line' => 'Atatürk Bulvarı No 5 D 2'],
            'billing_type' => 'individual', 'billing_same' => '1', 'delivery_method' => 'local', 'payment_method' => 'cod', 'consent' => '1',
        ], null, app(CartService::class)->summary($cart), $cart->coupon_code), null, $cart, '127.0.0.1', 'test');
    }

    private function delivered(StoreOrder $order, int $daysAgo = 1): StoreOrder
    {
        $order->forceFill(['status' => 'delivered', 'delivered_at' => now()->subDays($daysAgo), 'payment_status' => 'collected', 'collected_method' => 'cash'])->save();

        return $order;
    }

    public function test_admin_opens_return_on_behalf_of_customer_even_after_deadline(): void
    {
        $order = $this->delivered($this->codOrder($this->cart(2, 0)), 40);
        $item = $order->items()->firstOrFail();
        $input = ['items' => [$item->id => 1], 'reason' => 'damaged', 'iban' => 'TR330006100519786457841326', 'account_name' => 'Ayşe Yılmaz'];

        $this->assertNotNull(app(ReturnService::class)->refusal($order), 'müşteri için süre doldu');
        $this->get(route('store_admin.orders.show', $order))->assertOk()->assertSee('Müşteri adına iade talebi aç');
        $this->post(route('store_admin.orders.return', $order), ['iban' => ''] + $input)->assertSessionHasErrors('iban');
        $this->post(route('store_admin.orders.return', $order), $input)->assertRedirect();

        $return = StoreReturn::sole();
        $this->assertSame(['requested', 'store_admin'], [$return->status, $return->created_by_type]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'store.return_requested', 'entity_id' => $return->id]);

        // Teslim edilmemiş siparişte açılamaz.
        $open = $this->codOrder($this->cart(1, 0), '05329990000', 'b@ornek.com');
        $this->post(route('store_admin.orders.return', $open), ['items' => [$open->items()->value('id') => 1], 'reason' => 'damaged', 'iban' => 'TR330006100519786457841326', 'account_name' => 'B'])
            ->assertSessionHasErrors('order');
    }

    public function test_refund_without_return_cod_and_card(): void
    {
        $order = $this->delivered($this->codOrder($this->cart(2, 0)));
        $this->post(route('store_admin.orders.refund', $order), ['amount' => '20', 'refund_reason' => ''])->assertSessionHasErrors('refund_reason');
        $this->post(route('store_admin.orders.refund', $order), ['amount' => '500', 'refund_reason' => 'Eksik'])->assertSessionHasErrors('order');
        $this->post(route('store_admin.orders.refund', $order), ['amount' => '20,50', 'refund_reason' => '1 kalem eksik gönderildi', 'method' => 'iyzico'])->assertSessionHasNoErrors();
        $this->assertSame(['manual', '20.50'], [StoreRefund::sole()->method, StoreRefund::sole()->amount], 'kapıda ödemede her zaman elle');
        $this->assertSame(['partially_refunded', '20.50'], [$order->fresh()->payment_status, $order->fresh()->refunded_total]);
        $this->assertSame(48, $this->pen->fresh()->stock_quantity, 'ürün iadesi olmadığı için stok değişmez');

        // Kart: iyzico'da işlemlere dağıtılır.
        StoreSetting::putSecret('iyzico_api_key', 'k');
        StoreSetting::putSecret('iyzico_secret_key', 's');
        $card = $this->codOrder($this->cart(1, 1), '05329990000', 'b@ornek.com');
        [$p, $b] = $card->items()->get()->all();
        $card->forceFill(['payment_method' => 'card', 'payment_status' => 'paid', 'status' => 'delivered', 'delivered_at' => now()])->save();
        $card->payments()->create(['driver' => 'iyzico', 'status' => 'succeeded', 'amount' => $card->total, 'item_transactions' => [
            ['item_id' => (string) $p->id, 'transaction_id' => 'tx-p', 'paid_price' => '100.00', 'refunded' => '0.00'],
            ['item_id' => (string) $b->id, 'transaction_id' => 'tx-b', 'paid_price' => '50.00', 'refunded' => '0.00'],
        ]]);
        Http::fake(['*/payment/refund' => fn (HttpRequest $r) => Http::response(['status' => 'success'])]);
        $this->post(route('store_admin.orders.refund', $card), ['amount' => '120', 'refund_reason' => 'Gönül alma', 'method' => 'iyzico'])->assertSessionHasNoErrors();
        $sent = collect(Http::recorded())->map(fn ($pair) => [$pair[0]['paymentTransactionId'], $pair[0]['price']])->all();
        $this->assertSame([['tx-p', '100.00'], ['tx-b', '20.00']], $sent);
        $this->assertSame('120.00', $card->fresh()->refunded_total);
    }

    public function test_credit_notes_require_invoice_and_refund_and_are_downloadable(): void
    {
        Storage::fake('local');
        $pdf = fn () => UploadedFile::fake()->createWithContent('iade.pdf', "%PDF-1.4\n%%EOF");
        $order = $this->delivered($this->codOrder($this->cart(2, 0)));

        $this->post(route('store_admin.orders.credit-note.store', $order), ['number' => 'IAD001', 'issued_on' => now('Europe/Istanbul')->toDateString(), 'file' => $pdf()])->assertSessionHasErrors('order'); // satış faturası yok
        $this->post(route('store_admin.orders.invoice.store', $order), ['invoice_no' => 'YBZ001', 'issued_on' => now('Europe/Istanbul')->toDateString(), 'file' => $pdf()])->assertSessionHasNoErrors();
        $this->post(route('store_admin.orders.credit-note.store', $order), ['number' => 'IAD001', 'issued_on' => now('Europe/Istanbul')->toDateString(), 'file' => $pdf()])->assertSessionHasErrors('order'); // iade yok
        $this->post(route('store_admin.orders.refund', $order), ['amount' => '100', 'refund_reason' => 'Hasarlı'])->assertSessionHasNoErrors();
        $this->post(route('store_admin.orders.credit-note.store', $order), ['number' => 'YBZ001', 'issued_on' => now('Europe/Istanbul')->toDateString(), 'file' => $pdf()])->assertSessionHasErrors('number'); // satış faturası numarası
        $this->post(route('store_admin.orders.credit-note.store', $order), ['number' => 'iad001', 'issued_on' => now('Europe/Istanbul')->toDateString(), 'amount' => '100', 'file' => $pdf()])->assertSessionHasNoErrors();

        $note = $order->creditNotes()->firstOrFail();
        $this->assertSame(['IAD001', '100.00'], [$note->number, $note->amount]);
        $params = ['orderNo' => $order->order_no, 'token' => $order->access_token];
        $this->get(route('store.order', $params))->assertSee('İade faturası (IAD001)');
        $this->get(route('store.order.credit-note', $params + ['note' => $note->id]))->assertOk()->assertHeader('content-type', 'application/pdf');
        $other = $this->codOrder($this->cart(1, 0), '05329990000', 'b@ornek.com');
        $this->get(route('store.order.credit-note', ['orderNo' => $other->order_no, 'token' => $other->access_token, 'note' => $note->id]))->assertNotFound();
        $this->get(route('store_admin.orders.credit-note', [$order, $note->id]))->assertOk();
    }

    public function test_coupon_scoped_to_category_with_descendants_and_products(): void
    {
        $parentId = $this->child->parent_id;
        $base = ['name' => 'Kalemlere %10', 'kind' => 'coupon', 'code' => 'KALEM10', 'type' => 'percent', 'value' => '10', 'is_active' => '1', 'min_subtotal' => '150'];
        $this->post(route('store_admin.discounts.store'), $base + ['product_skus' => 'YOK-1'])->assertSessionHasErrors('product_skus');
        $this->post(route('store_admin.discounts.store'), $base + ['category_ids' => [$parentId]])->assertSessionHasNoErrors();
        $coupon = StoreDiscount::where('code', 'KALEM10')->firstOrFail();
        $this->assertSame([$parentId], $coupon->category_ids);
        $this->assertStringContainsString('seçili ürünlerde', $coupon->summary());

        // Üst kategori seçili → alt kategorideki kalem kapsamda; kitap değil. En düşük tutar kapsamdaki ürünlere göre.
        try {
            $this->codOrder($this->cart(1, 5, 'KALEM10')); // kalem 100 (< 150), kitaplar 250
            $this->fail('kapsamdaki tutar yetersiz');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('150,00', $e->errors()['coupon'][0]);
        }
        $order = $this->codOrder($this->cart(2, 2, 'KALEM10')); // kalemler 200 → %10 = 20
        $this->assertSame('20.00', $order->discount_total);
        $this->assertSame(['20.00', '0.00'], $order->items()->orderBy('id')->pluck('discount_amount')->all());

        try {
            $this->codOrder($this->cart(0, 4, 'KALEM10'), '05329990000', 'b@ornek.com');
            $this->fail('uygun ürün yok');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('uygun ürün yok', $e->errors()['coupon'][0]);
        }

        // Ürüne (SKU) özel
        $this->put(route('store_admin.discounts.update', $coupon), ['category_ids' => [], 'product_skus' => 'kit-1', 'min_subtotal' => ''] + $base)->assertSessionHasNoErrors();
        $this->assertSame([$this->book->product_id], $coupon->fresh()->product_ids);
        $this->assertSame('10.00', $this->codOrder($this->cart(1, 2, 'KALEM10'), '05329990001', 'c@ornek.com')->discount_total);
    }

    public function test_bulk_codes_are_single_use_and_main_code_can_be_disabled(): void
    {
        $this->post(route('store_admin.discounts.store'), ['name' => 'Fuar', 'kind' => 'coupon', 'code' => 'FUAR', 'type' => 'fixed', 'value' => '15', 'is_active' => '1', 'codes_only' => '1'])
            ->assertSessionHasNoErrors();
        $coupon = StoreDiscount::where('code', 'FUAR')->firstOrFail();
        $this->post(route('store_admin.discounts.codes', $coupon), ['count' => '0'])->assertSessionHasErrors('count');
        $this->post(route('store_admin.discounts.codes', $coupon), ['count' => '5', 'prefix' => 'fuar-26'])->assertSessionHasErrors('prefix');
        $this->post(route('store_admin.discounts.codes', $coupon), ['count' => '5', 'prefix' => 'fuar26'])->assertSessionHasNoErrors();
        $codes = StoreDiscountCode::where('discount_id', $coupon->id)->pluck('code')->all();
        $this->assertCount(5, $codes);
        $this->assertMatchesRegularExpression('/^FUAR26-[A-HJ-NP-Z2-9]{8}$/', $codes[0]);

        try {
            $this->codOrder($this->cart(1, 0, 'FUAR'));
            $this->fail('ana kod kapalı');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('geçerli değil', $e->errors()['coupon'][0]);
        }

        $first = $this->codOrder($this->cart(1, 0, strtolower($codes[0])));
        $this->assertSame(['15.00', 'Kupon ('.$codes[0].')'], [$first->discount_total, $first->discount_name]);
        try {
            $this->codOrder($this->cart(1, 0, $codes[0]), '05329990000', 'b@ornek.com');
            $this->fail('kod tek kullanımlık');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('daha önce kullanılmış', $e->errors()['coupon'][0]);
        }
        $this->assertSame('15.00', $this->codOrder($this->cart(1, 0, $codes[1]), '05329990000', 'b@ornek.com')->discount_total);

        $csv = $this->get(route('store_admin.discounts.codes.export', $coupon))->assertOk()->streamedContent();
        $this->assertStringContainsString($codes[0].';Evet', $csv);
        $this->assertStringContainsString($codes[2].';Hayır', $csv);
        $this->get(route('store_admin.discounts.edit', $coupon))->assertOk()->assertSee('Üretilen: 5 · kullanılan: 2');
    }
}
