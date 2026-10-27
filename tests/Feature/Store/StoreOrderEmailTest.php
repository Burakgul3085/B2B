<?php

namespace Tests\Feature\Store;

use App\Domain\Store\Cart\CartService;
use App\Domain\Store\Inventory\StoreStockService;
use App\Domain\Store\Mail\StoreEmailRenderer;
use App\Domain\Store\Mail\StoreMailer;
use App\Domain\Store\Orders\CheckoutService;
use App\Models\Store\District;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Sipariş alındı e-postası onaylanan sözleşmeleri içerir (kalıcı veri saklayıcısı: Mesafeli Sözleşmeler Yönetmeliği). */
class StoreOrderEmailTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array{to: string, subject: string, content: array}> */
    public array $mails = [];

    public function test_order_placed_email_contains_the_accepted_contracts(): void
    {
        $test = $this;
        $this->instance(StoreMailer::class, new class($test) extends StoreMailer
        {
            public function __construct(private $test) {}

            public function send(string $to, string $subject, array $content): bool
            {
                $this->test->mails[] = compact('to', 'subject', 'content');

                return true;
            }
        });
        StoreSetting::put(['is_open' => '1', 'cod_enabled' => '1', 'cod_first_order_approval' => '0', 'company_title' => 'Yapboz Kırtasiye Ltd. Şti.']);
        $variant = StoreProduct::create(['name' => 'Jel Kalem', 'slug' => 'jel-kalem', 'is_published' => true])
            ->variants()->create(['sku' => 'JEL-1', 'price' => '50.00', 'vat_rate' => '20']);
        app(StoreStockService::class)->receive($variant, 5);
        $carts = app(CartService::class);
        $cart = $carts->createGuest();
        $carts->add($cart, $variant->id, 2);
        $checkout = app(CheckoutService::class);
        $order = $checkout->place($checkout->validate([
            'email' => 'ayse@ornek.com',
            'address' => ['first_name' => 'Ayşe', 'last_name' => 'Yılmaz', 'phone' => '05321234567', 'province_id' => 26,
                'district_id' => District::where('province_id', 26)->where('name', 'Tepebaşı')->value('id'), 'address_line' => 'Atatürk Bulvarı No 5 D 2'],
            'billing_type' => 'individual', 'billing_same' => '1', 'delivery_method' => 'local', 'payment_method' => 'cod', 'consent' => '1',
        ], null, $carts->summary($cart)), null, $cart, '127.0.0.1', 'test');
        $this->app->terminate();

        $mail = collect($this->mails)->firstWhere('to', 'ayse@ornek.com');
        $this->assertNotNull($mail, 'sipariş e-postası gönderildi');
        $titles = array_column($mail['content']['documents'], 'title');
        $this->assertSame(['Ön Bilgilendirme Formu (sürüm 2)', 'Mesafeli Satış Sözleşmesi (sürüm 2)'], $titles);
        // Siparişte saklanan doldurulmuş metin gönderilir (alıcı adı, ürün, satıcı unvanı yerleşmiş).
        $this->assertStringContainsString('Ayşe Yılmaz', $mail['content']['documents'][1]['html']);
        $this->assertStringContainsString('Jel Kalem', $mail['content']['documents'][1]['html']);
        $this->assertStringContainsString('Yapboz Kırtasiye Ltd. Şti.', $mail['content']['documents'][1]['html']);
        $this->assertStringContainsString(config('store.domain'), $mail['content']['documents'][1]['html']);
        $this->assertStringNotContainsString('{SATICI_', $mail['content']['documents'][0]['html'].$mail['content']['documents'][1]['html']);

        $html = app(StoreEmailRenderer::class)->render($mail['subject'], $mail['content'])['html'];
        $this->assertStringContainsString('Mesafeli Satış Sözleşmesi (sürüm 2)', $html);
        $this->assertStringContainsString('<h2', $html);
        $this->assertStringContainsString($order->order_no, $html);
    }
}
