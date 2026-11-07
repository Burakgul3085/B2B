<?php

namespace Tests\Feature\Invoicing;

use App\Domain\Invoicing\InvoiceService;
use App\Domain\Invoicing\InvoiceSettings;
use App\Domain\Invoicing\UblBuilder;
use App\Domain\Ordering\CartService;
use App\Domain\Ordering\LineCalculator;
use App\Domain\Ordering\OrderService;
use App\Domain\Ordering\ReturnService;
use App\Domain\Support\LocalTime;
use App\Http\Controllers\Business\InvoiceController;
use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\BusinessSetting;
use App\Models\DealerRelationship;
use App\Models\EmailLog;
use App\Models\Invoice;
use App\Models\InvoiceSetting;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\OrderReturn;
use App\Models\Product;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesOrders;
use Tests\TestCase;

/**
 * B2B faturaları (toptancı → bayi): tetikleyiciler (ödeme / sevk), tutarların siparişle birebir aynı olması, çift fatura
 * engeli, iade ve iptal kuralları, otomatik kesim, Uyumsoft (seri, internet satışı, belirsiz sonuç, durum), elle kesilen
 * fatura, işletme / bayi yalıtımı ve mağaza faturalarından ayrılık.
 */
class B2bInvoiceTest extends TestCase
{
    use CreatesOrders, RefreshDatabase;

    private Business $business;

    private DealerRelationship $rel;

    private Product $product;

    /** @var list<string> */
    private array $soap = [];

    private int $sentCount = 0;

    private bool $eInvoiceUser = false;

    /** @var array{0: string, 1: string} */
    private array $statusReply = ['Approved', ''];

    private bool $sendTimesOut = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['b2b.pay_later_enabled' => true]); // eski sonra öde siparişlerinin faturası da sınanır
        Storage::fake('local');
        $this->business = $this->makeBusiness();
        $this->business->update(['tax_number' => '1234567890', 'tax_office' => 'Eskişehir', 'address' => 'Hoşnudiye Mah. 1', 'city' => 'Eskişehir', 'district' => 'Tepebaşı']);
        $this->rel = $this->orderingDealer($this->business);
        $this->rel->dealer->update(['legal_name' => 'Kırtasiye Dünyası Ltd.', 'tax_number' => '9876543210', 'tax_office' => 'Kadıköy', 'address' => 'Moda Cd. 1', 'city' => 'İstanbul', 'district' => 'Kadıköy']);
        $this->product = $this->makeProduct($this->business, ['dealer_price' => '10.00', 'name' => 'Defter A4']);
        $this->stockUp($this->business, $this->product, '100');
        BusinessSetting::where('business_id', $this->business->id)->update([
            'payments_enabled' => true, 'transfer_enabled' => true, 'delivery_fee' => '50.00',
            'transfer_accounts' => json_encode([['bank' => 'Akbank', 'holder' => 'Yapboz', 'iban' => 'TR280004609876543210987654']]),
        ]);
        Http::fake(['*' => fn (HttpRequest $r) => $this->fakeHttp($r)]);
        $this->actingAs($this->business->user, 'business');
    }

    /* ---------------- Yardımcılar ---------------- */

    private function fakeHttp(HttpRequest $r)
    {
        preg_match('#<tem:(\w+)>#', $r->body(), $m);
        $method = $m[1] ?? '?';
        $this->soap[] = $method;
        if ($method === 'SendInvoice' && $this->sendTimesOut) {
            return Http::response('<html>Gateway Timeout</html>', 504);
        }

        return Http::response(match ($method) {
            'WhoAmI' => $this->soapResult('WhoAmI', '<Value><Customer><Name>YAPBOZ</Name><VkTckNo>1234567890</VkTckNo></Customer></Value>'),
            'IsEInvoiceUser' => $this->soapResult('IsEInvoiceUser', '', 'IsSucceded="true" Value="'.($this->eInvoiceUser ? 'true' : 'false').'"'),
            'GetUserAliasses' => $this->soapResult('GetUserAliasses', '<Value><ReceiverboxAliases Alias="urn:mail:defaultpk@bayi.com" Enabled="true"/></Value>'),
            'SendInvoice' => $this->soapResult('SendInvoice', sprintf('<Value Id="aaaaaaaa-bbbb-cccc-dddd-%012d" Number="BYA%d"/>', ++$this->sentCount, 2026000000000 + $this->sentCount)),
            'GetOutboxInvoice' => $this->soapResult('GetOutboxInvoice', '<Value InvoiceId="BYA2026000000099"/>'),
            'QueryOutboxInvoiceStatus' => $this->soapResult('QueryOutboxInvoiceStatus', '<Value Status="'.$this->statusReply[0].'" Message="'.e($this->statusReply[1]).'"/>'),
            'GetOutboxInvoicePdf' => $this->soapResult('GetOutboxInvoicePdf', '<Value><Data>'.base64_encode('%PDF-1.4 uyumsoft').'</Data></Value>'),
            'CancelEArchiveInvoice' => $this->soapResult('CancelEArchiveInvoice', '', 'IsSucceded="true" Value="true"'),
            default => $this->soapResult($method, '', 'IsSucceded="false" Message="Bilinmeyen"'),
        });
    }

    private function soapResult(string $method, string $inner, string $attrs = 'IsSucceded="true"'): string
    {
        return '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body><'.$method.'Response xmlns="http://tempuri.org/">'
            .'<'.$method.'Result '.$attrs.'>'.$inner.'</'.$method.'Result></'.$method.'Response></s:Body></s:Envelope>';
    }

    private function useUyumsoft(array $overrides = []): void
    {
        $this->inTenant($this->business, fn () => app(InvoiceSettings::class)->save($overrides + [
            'driver' => 'uyumsoft', 'uyumsoft_mode' => 'test', 'uyumsoft_username' => 'ws-kullanici', 'uyumsoft_password' => 'ws-sifre-123',
            'series_earsiv' => 'BYA', 'series_efatura' => 'BYF', 'delivery_vat' => '20', 'notify_dealer' => '1',
        ]));
    }

    private function inBusiness(callable $callback): mixed
    {
        return $this->inTenant($this->business, $callback);
    }

    /** Sipariş: 10 × 10 ₺ (KDV %20) = 120 + 50 teslimat = 170 ₺; onaylanır. */
    private function approvedOrder(string $method = 'transfer', string $qty = '10', string $approved = '10'): Order
    {
        $order = $this->inBusiness(function () use ($method, $qty) {
            app(CartService::class)->add($this->rel, $this->product->id, $qty);

            return app(OrderService::class)->submit($this->rel, $this->rel->addresses()->value('id'), null, (string) Str::uuid(), $method)->fresh('items');
        });
        $this->post(route('business.orders.approve', $order), ['warehouse_id' => $this->defaultWarehouse($this->business)->id, 'qty' => [$order->items->first()->id => $approved]])
            ->assertSessionHasNoErrors();

        return $this->inBusiness(fn () => $order->fresh('items'));
    }

    private function paidOrder(string $approved = '10'): Order
    {
        $order = $this->approvedOrder('transfer', '10', $approved);
        $this->post(route('business.orders.payments.store', $order), ['method' => 'transfer', 'paid_on' => now('Europe/Istanbul')->toDateString()])->assertSessionHasNoErrors();

        return $this->inBusiness(fn () => $order->fresh('items'));
    }

    /** Fiyat listesi indirimi (% olarak) uygulanmış sipariş: kalem ve sipariş tutarları sipariş kuralıyla yeniden hesaplanır. */
    private function withDiscount(Order $order, string $percent): Order
    {
        return $this->inBusiness(function () use ($order, $percent) {
            foreach ($order->items as $item) {
                $item->fill(['discount_percent' => $percent] + LineCalculator::line((string) $item->unit_price, (string) $item->quantity_approved, $percent, (string) $item->vat_rate))->save();
            }
            $totals = LineCalculator::totals($order->items()->get()->map->only(['line_subtotal', 'line_discount', 'order_discount', 'line_net', 'line_vat', 'line_total']));
            $order->forceFill(['grand_total' => bcadd($totals['grand_total'], (string) $order->delivery_fee, 2)] + $totals)->save();

            return $order->fresh('items');
        });
    }

    private function completedOrder(): Order
    {
        $order = $this->paidOrder();
        foreach (['prepare', 'ship', 'complete'] as $step) {
            $this->post(route("business.orders.{$step}", $order))->assertSessionHasNoErrors();
        }

        return $this->inBusiness(fn () => $order->fresh('items'));
    }

    private function service(): InvoiceService
    {
        return app(InvoiceService::class);
    }

    private function sentSale(Order $order): Invoice
    {
        return $this->inBusiness(fn () => $this->service()->send($this->service()->createSale($order)));
    }

    /* ---------------- Tetikleyiciler ve tutarlar ---------------- */

    public function test_paid_order_becomes_billable_and_invoice_matches_order_amounts_exactly(): void
    {
        $approved = $this->approvedOrder();
        $this->assertFalse(InvoiceService::billable($approved), 'Ödemesi alınmamış havale siparişi faturalanmaz.');
        $this->assertSame(0, $this->inBusiness(fn () => InvoiceService::awaitingSale()->count()));

        $this->post(route('business.orders.payments.store', $approved), ['method' => 'transfer', 'paid_on' => now('Europe/Istanbul')->toDateString()]);
        $order = $this->inBusiness(fn () => $approved->fresh('items'));
        $this->assertSame([$order->id], $this->inBusiness(fn () => InvoiceService::awaitingSale()->pluck('id')->all()));

        $this->post(route('business.orders.invoices.sale', $order))->assertRedirect();
        $invoice = $this->inBusiness(fn () => Invoice::with('lines')->sole());
        $this->assertSame(['sale', 'draft', 'system'], [$invoice->kind, $invoice->status, $invoice->source]);
        $this->assertSame(['170.00', '170.00', '170.00'], [(string) $invoice->total, (string) $invoice->expected_total, (string) $order->grand_total]);
        $this->assertSame(['Kırtasiye Dünyası Ltd.', '9876543210', 'Kadıköy', 'İstanbul'], [$invoice->buyer_name, $invoice->buyer_tax_number, $invoice->buyer_tax_office, $invoice->buyer_city]);

        [$item, $fee] = $invoice->lines->all();
        $this->assertSame(['10.000', '10.0000', '0.00', '100.00', '20.00', '120.00'], [(string) $item->quantity, (string) $item->unit_price, (string) $item->discount, (string) $item->net_amount, (string) $item->vat_amount, (string) $item->line_total]);
        $this->assertSame($order->items->first()->id, $item->order_item_id);
        // Teslimat ücreti KDV dahildir: 50 = 41,67 + 8,33 (%20).
        $this->assertSame(['Teslimat hizmeti', null, '41.67', '8.33', '50.00'], [$fee->name, $fee->order_item_id, (string) $fee->net_amount, (string) $fee->vat_amount, (string) $fee->line_total]);
        $this->assertSame(['141.67', '28.33'], [(string) $invoice->subtotal, (string) $invoice->vat_total]);
    }

    public function test_partial_approval_and_discount_are_invoiced_as_approved(): void
    {
        $order = $this->withDiscount($this->paidOrder('4'), '15');
        $invoice = $this->inBusiness(fn () => $this->service()->createSale($order)->load('lines'));
        $item = $invoice->lines->first();
        $orderItem = $order->items->first();

        $this->assertSame((string) $orderItem->quantity_approved, (string) $item->quantity);
        $this->assertSame(bcadd((string) $orderItem->line_discount, (string) $orderItem->order_discount, 2), (string) $item->discount);
        $this->assertSame([(string) $orderItem->line_net, (string) $orderItem->line_vat, (string) $orderItem->line_total], [(string) $item->net_amount, (string) $item->vat_amount, (string) $item->line_total]);
        $this->assertSame((string) $order->grand_total, (string) $invoice->total);
    }

    public function test_pay_later_order_is_billable_only_after_shipment(): void
    {
        $this->inBusiness(fn () => $this->rel->update(['allow_pay_later' => true, 'payment_term_days' => 30]));
        $order = $this->approvedOrder('pay_later');
        $this->assertFalse(InvoiceService::billable($order));
        $this->post(route('business.orders.prepare', $order))->assertSessionHasNoErrors();
        $this->assertFalse(InvoiceService::billable($this->inBusiness(fn () => $order->fresh())));
        $this->assertSame(0, $this->inBusiness(fn () => InvoiceService::awaitingSale()->count()));

        $this->post(route('business.orders.ship', $order))->assertSessionHasNoErrors();
        $order = $this->inBusiness(fn () => $order->fresh());
        $this->assertTrue(InvoiceService::billable($order));
        $this->assertEquals($order->shipped_at, InvoiceService::triggeredAt($order));
        $this->assertSame([$order->id], $this->inBusiness(fn () => InvoiceService::awaitingSale()->pluck('id')->all()));

        // Ödemesi alınmamış, yola çıkmamış siparişe taslak açılamaz.
        $other = $this->approvedOrder('pay_later', '2', '2');
        $this->expectException(DomainException::class);
        $this->inBusiness(fn () => $this->service()->createSale($other));
    }

    /* ---------------- Çift fatura ve bütünlük ---------------- */

    public function test_one_open_sale_document_per_order_even_at_database_level(): void
    {
        $order = $this->paidOrder();
        $first = $this->inBusiness(fn () => $this->service()->createSale($order));
        $again = $this->inBusiness(fn () => $this->service()->createSale($order));
        $this->assertSame($first->id, $again->id);

        // Servisi atlayan ikinci açık satış belgesi veritabanında reddedilir.
        $this->expectException(QueryException::class);
        $this->inBusiness(fn () => Invoice::create($first->only([
            'order_id', 'kind', 'buyer_name', 'buyer_address', 'buyer_city', 'issue_date', 'expected_total',
        ]) + ['status' => 'draft', 'local_id' => 'X-'.Str::random(8)]));
    }

    public function test_draft_amounts_cannot_be_edited_and_send_rechecks_order_total(): void
    {
        $order = $this->paidOrder();
        $invoice = $this->inBusiness(fn () => $this->service()->createSale($order));

        $this->put(route('business.invoices.update', $invoice), [
            'buyer_name' => 'Kırtasiye Dünyası A.Ş.', 'buyer_tax_number' => '9876543210', 'buyer_tax_office' => 'Kadıköy', 'buyer_address' => 'Moda Cd. 2',
            'buyer_city' => 'İstanbul', 'issue_date' => now('Europe/Istanbul')->toDateString(), 'notify_dealer' => '1',
            'total' => '1.00', 'expected_total' => '1.00', 'lines' => [['unit_price' => '0.01']],
        ])->assertSessionHasNoErrors();
        $invoice = $this->inBusiness(fn () => $invoice->fresh('lines'));
        $this->assertSame(['Kırtasiye Dünyası A.Ş.', 'Moda Cd. 2', '170.00', '170.00'], [$invoice->buyer_name, $invoice->buyer_address, (string) $invoice->total, (string) $invoice->expected_total]);
        $this->assertSame('10.0000', (string) $invoice->lines->first()->unit_price);

        // Tarih en fazla 7 gün geriye; VKN'de vergi dairesi zorunlu.
        $this->put(route('business.invoices.update', $invoice), ['buyer_name' => 'X', 'buyer_tax_number' => '9876543210', 'buyer_address' => 'A', 'buyer_city' => 'B',
            'issue_date' => now('Europe/Istanbul')->subDays(8)->toDateString()])->assertSessionHasErrors(['issue_date', 'buyer_tax_office']);

        // Taslaktan sonra siparişin tutarı değişirse (ör. teslimat ücreti düzeltmesi) fatura gönderilmez.
        $this->inBusiness(fn () => DB::table('orders')->where('id', $order->id)->update(['delivery_fee' => '40.00', 'grand_total' => '160.00']));
        $this->post(route('business.invoices.send', $invoice))->assertSessionHasErrors('invoice');
        $invoice = $this->inBusiness(fn () => $invoice->fresh());
        $this->assertSame('draft', $invoice->status);
        $this->assertStringContainsString('siparişin tutarı değişmiş', $invoice->error_message);
        $this->assertNull($invoice->invoice_no);
    }

    public function test_send_with_deneme_driver_issues_invoice_notifies_dealer_and_never_touches_store(): void
    {
        $order = $this->paidOrder();
        $invoice = $this->inBusiness(fn () => $this->service()->createSale($order));
        $this->post(route('business.invoices.send', $invoice))->assertRedirect()->assertSessionHasNoErrors();

        $invoice = $this->inBusiness(fn () => $invoice->fresh());
        $this->assertSame(['sent', 'deneme', 'e_arsiv'], [$invoice->status, $invoice->provider, $invoice->document_type]);
        $this->assertMatchesRegularExpression('/^DNM2026\d{9}$|^DNM\d{13}$/', $invoice->invoice_no);
        $this->assertNotNull($invoice->pdf_path);
        $this->assertStringStartsWith("businesses/{$this->business->id}/invoices/{$order->id}/", $invoice->pdf_path);
        Storage::disk('local')->assertExists($invoice->pdf_path);
        $this->assertNotNull($invoice->dealer_notified_at);

        $this->assertTrue($this->inBusiness(fn () => EmailLog::where('template_key', 'invoice_ready')->where('related_id', $invoice->id)->exists()));
        $this->assertTrue($this->inBusiness(fn () => AppNotification::where('dealer_relationship_id', $this->rel->id)->where('title', 'like', 'Faturanız hazır%')->exists()));
        $this->assertTrue($this->inBusiness(fn () => AuditLog::where('action', 'invoice.sent')->where('entity_id', $invoice->id)->exists()));

        // Gönderilen fatura değişmez, yeniden gönderilemez; sipariş "bekleyen"den çıkar.
        $this->post(route('business.invoices.send', $invoice))->assertSessionHasErrors('invoice');
        $this->put(route('business.invoices.update', $invoice), ['buyer_name' => 'Başka'])->assertSessionHasErrors('invoice');
        $this->assertSame(0, $this->inBusiness(fn () => InvoiceService::awaitingSale()->count()));

        // Mağaza dünyası etkilenmez.
        $this->assertSame(0, DB::table('store_e_invoices')->count());
        $this->assertSame(0, DB::table('store_invoices')->count());
    }

    public function test_send_blocks_missing_seller_or_buyer_tax_details(): void
    {
        $order = $this->paidOrder();
        $this->business->update(['tax_number' => null]);
        $invoice = $this->inBusiness(fn () => $this->service()->createSale($order));
        $this->post(route('business.invoices.send', $invoice))->assertSessionHasErrors('invoice');
        $this->assertStringContainsString('satıcı bilgisi eksik', $this->inBusiness(fn () => $invoice->fresh()->error_message));

        $this->business->update(['tax_number' => '1234567890']);
        $this->inBusiness(fn () => $invoice->update(['buyer_tax_number' => null]));
        $this->post(route('business.invoices.send', $invoice))->assertSessionHasErrors('invoice');
        $this->assertSame('draft', $this->inBusiness(fn () => $invoice->fresh()->status));
    }

    /* ---------------- Uyumsoft ---------------- */

    public function test_uyumsoft_sends_from_dealer_series_with_internet_sale_info_and_notifies_after_approval(): void
    {
        $this->useUyumsoft();
        $order = $this->paidOrder();
        $invoice = $this->sentSale($order);

        $this->assertSame(['sent', 'uyumsoft', 'e_arsiv', 'BYA2026000000001'], [$invoice->status, $invoice->provider, $invoice->document_type, $invoice->invoice_no]);
        $this->assertSame(['IsEInvoiceUser', 'SendInvoice', 'GetOutboxInvoicePdf'], $this->soap);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'efatura-test.uyumsoft.com.tr') && str_contains($r->body(), '<tem:SendInvoice>')
            && str_contains($r->body(), '<cbc:ID>BYA</cbc:ID>') && str_contains($r->body(), '<cbc:ProfileID>EARSIVFATURA</cbc:ProfileID>')
            && str_contains($r->body(), '<cbc:InvoiceTypeCode>SATIS</cbc:InvoiceTypeCode>') && str_contains($r->body(), '<tem:PaymentType>EFT/HAVALE</tem:PaymentType>')
            && str_contains($r->body(), 'schemeID="VKN">9876543210') && str_contains($r->body(), '<cbc:PayableAmount currencyID="TRY">170.00</cbc:PayableAmount>')
            && str_contains($r->body(), '<wsse:Username>ws-kullanici</wsse:Username>'));
        Storage::disk('local')->assertExists($invoice->pdf_path);

        // Uyumsoft numarayı verdi ama GİB onayı gelmeden bayiye bildirim gitmez; zamanlanmış komut onayı alınca gönderir.
        $this->assertNull($invoice->dealer_notified_at);
        $this->artisan('b2b:invoices')->assertSuccessful();
        $invoice = $this->inBusiness(fn () => $invoice->fresh());
        $this->assertSame('Approved', $invoice->provider_status);
        $this->assertNotNull($invoice->dealer_notified_at);
        $this->assertSame(1, $this->inBusiness(fn () => EmailLog::where('template_key', 'invoice_ready')->count()));
    }

    public function test_uyumsoft_rejection_reopens_order_for_invoicing(): void
    {
        $this->useUyumsoft();
        $invoice = $this->sentSale($this->paidOrder());
        $this->statusReply = ['Error', 'Alıcı VKN hatalı'];
        $this->post(route('business.invoices.status', $invoice))->assertSessionHasNoErrors();

        $invoice = $this->inBusiness(fn () => $invoice->fresh());
        $this->assertSame('failed', $invoice->status);
        $this->assertStringContainsString('Alıcı VKN hatalı', $invoice->error_message);
        $this->assertNull($invoice->dealer_notified_at);
        $this->assertSame(1, $this->inBusiness(fn () => InvoiceService::awaitingSale()->count()), 'Reddedilen faturanın siparişi yeniden faturalanabilir.');
    }

    public function test_uncertain_send_is_never_resent_blindly_and_is_resolved_from_provider(): void
    {
        $this->useUyumsoft();
        $order = $this->paidOrder();
        $invoice = $this->inBusiness(fn () => $this->service()->createSale($order));
        $this->sendTimesOut = true;
        $this->post(route('business.invoices.send', $invoice))->assertSessionHasErrors('invoice');
        $invoice = $this->inBusiness(fn () => $invoice->fresh());
        $this->assertSame('unknown', $invoice->status);
        $this->assertNotNull($invoice->ettn);

        // Sonucu belirsiz belge yeniden gönderilemez, siparişe ikinci taslak açılamaz.
        $this->post(route('business.invoices.send', $invoice))->assertSessionHasErrors('invoice');
        $this->assertSame($invoice->id, $this->inBusiness(fn () => $this->service()->createSale($order)->id));
        $this->assertSame(0, $this->inBusiness(fn () => InvoiceService::awaitingSale()->count()));

        // Sorgu: Uyumsoft'ta bulundu → kesildi olarak tamamlanır (ikinci gönderim yok).
        $sendCalls = count(array_filter($this->soap, fn ($m) => $m === 'SendInvoice'));
        $this->post(route('business.invoices.check', $invoice))->assertSessionHasNoErrors();
        $invoice = $this->inBusiness(fn () => $invoice->fresh());
        $this->assertSame(['sent', 'BYA2026000000099'], [$invoice->status, $invoice->invoice_no]);
        $this->assertSame($sendCalls, count(array_filter($this->soap, fn ($m) => $m === 'SendInvoice')));
    }

    public function test_e_fatura_dealer_gets_e_fatura_and_refund_is_left_to_dealer(): void
    {
        $this->useUyumsoft();
        $this->eInvoiceUser = true;
        $order = $this->completedOrder();
        $invoice = $this->sentSale($order);
        $this->assertSame(['e_fatura', 'BYA2026000000001'], [$invoice->document_type, $invoice->invoice_no]);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->body(), '<cbc:ID>BYF</cbc:ID>') && str_contains($r->body(), '<cbc:ProfileID>TEMELFATURA</cbc:ProfileID>')
            && str_contains($r->body(), 'Alias="urn:mail:defaultpk@bayi.com"'));

        $this->inBusiness(fn () => app(ReturnService::class)->create($order, [$order->items->first()->id => '2'], null, $this->business->user->id));
        $this->assertSame(0, $this->inBusiness(fn () => InvoiceService::awaitingRefund()->count()), 'e-Fatura satışında iade faturasını bayi keser.');
        $this->expectException(DomainException::class);
        $this->inBusiness(fn () => $this->service()->createRefund($order->fresh(), OrderReturn::first()));
    }

    public function test_uyumsoft_requires_separate_series_and_keeps_password_encrypted_and_hidden(): void
    {
        $this->assertThrows(fn () => $this->useUyumsoft(['series_earsiv' => '', 'series_efatura' => '']), ValidationException::class);
        $this->assertThrows(fn () => $this->useUyumsoft(['series_earsiv' => 'BYA', 'series_efatura' => 'BYA']), ValidationException::class);
        $this->useUyumsoft(['series_earsiv' => 'bya']);

        $record = $this->inBusiness(fn () => InvoiceSetting::sole());
        $this->assertSame('BYA', $record->series_earsiv);
        $raw = $record->getRawOriginal('uyumsoft_password');
        $this->assertNotSame('ws-sifre-123', $raw);
        $this->assertSame('ws-sifre-123', Crypt::decryptString($raw));
        $this->assertArrayNotHasKey('uyumsoft_password', $record->toArray());

        $this->get(route('business.invoice-settings.edit'))->assertOk()->assertDontSee('ws-sifre-123')->assertSee('Kayıtlı. Değiştirmek için');
        $this->assertFalse($this->inBusiness(fn () => AuditLog::where('action', 'invoice.settings_updated')->get()->contains(fn ($l) => str_contains(json_encode($l->new_values), 'ws-sifre'))));

        // Boş şifre kayıtlı şifreyi korur.
        $this->put(route('business.invoice-settings.update'), ['driver' => 'uyumsoft', 'uyumsoft_mode' => 'test', 'uyumsoft_username' => 'ws-kullanici', 'uyumsoft_password' => '',
            'series_earsiv' => 'BYA', 'series_efatura' => 'BYF', 'delivery_vat' => '20'])->assertSessionHasNoErrors();
        $this->assertSame('ws-sifre-123', $this->inBusiness(fn () => app(InvoiceSettings::class)->password()));

        $this->post(route('business.invoice-settings.test'))->assertSessionHasNoErrors()->assertSessionHas('status', fn ($s) => str_contains($s, 'Uyumsoft bağlantısı başarılı'));
    }

    /* ---------------- Otomatik kesim ---------------- */

    public function test_auto_issue_applies_only_to_orders_paid_after_it_was_enabled(): void
    {
        $before = $this->paidOrder('3');
        $this->travel(1)->minutes();
        $this->put(route('business.invoice-settings.update'), ['driver' => 'deneme', 'uyumsoft_mode' => 'test', 'delivery_vat' => '20', 'auto_issue' => '1', 'notify_dealer' => '1'])
            ->assertSessionHasNoErrors();
        $this->travel(1)->minutes();
        $after = $this->paidOrder('5');

        $this->artisan('b2b:invoices')->assertSuccessful();
        $this->artisan('b2b:invoices')->assertSuccessful();

        $this->assertSame([$after->id], $this->inBusiness(fn () => Invoice::where('status', 'sent')->pluck('order_id')->all()));
        $this->assertSame([$before->id], $this->inBusiness(fn () => InvoiceService::awaitingSale()->pluck('id')->all()));
        $this->assertSame(1, $this->inBusiness(fn () => Invoice::count()), 'Komut ikinci kez çalışınca yeni belge açılmaz.');
    }

    /* ---------------- İade ve iptal ---------------- */

    public function test_partial_return_gets_refund_invoice_referencing_sale(): void
    {
        $this->useUyumsoft();
        $order = $this->completedOrder();
        $sale = $this->sentSale($order);
        $this->travel(8)->days(); // 7 günlük iptal süresi geçti
        $return = $this->inBusiness(fn () => app(ReturnService::class)->create($order, [$order->items->first()->id => '3'], 'Hasarlı', $this->business->user->id));

        $rows = $this->inBusiness(fn () => InvoiceService::awaitingRefund());
        $this->assertCount(1, $rows);
        $this->assertSame([$return->id, false], [$rows->first()['return']->id, $rows->first()['cancel_instead']]);

        $this->post(route('business.orders.invoices.refund', $order), ['order_return_id' => $return->id])->assertRedirect()->assertSessionHasNoErrors();
        $refund = $this->inBusiness(fn () => Invoice::where('kind', 'refund')->with('lines')->sole());
        $this->assertSame([$return->id, '36.00', '36.00'], [$refund->order_return_id, (string) $refund->total, (string) $return->grand_total]);
        $this->assertCount(1, $refund->lines, 'Kısmi iadede teslimat ücreti iade edilmez.');

        $this->post(route('business.invoices.send', $refund))->assertSessionHasNoErrors();
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->body(), '<cbc:InvoiceTypeCode>IADE</cbc:InvoiceTypeCode>')
            && str_contains($r->body(), '<cbc:ID>'.$sale->invoice_no.'</cbc:ID>') && str_contains($r->body(), '<cbc:DocumentTypeCode>IADE</cbc:DocumentTypeCode>'));
        $this->assertSame(0, $this->inBusiness(fn () => InvoiceService::awaitingRefund()->count()));

        // İade faturası varken satış faturası iptal edilemez.
        $this->post(route('business.invoices.cancel', $sale), ['cancel_reason' => 'Hata'])->assertSessionHasErrors('invoice');
    }

    public function test_full_return_within_seven_days_cancels_sale_instead_of_refund_invoice(): void
    {
        $this->useUyumsoft();
        $order = $this->completedOrder();
        $sale = $this->sentSale($order);
        $this->inBusiness(fn () => app(ReturnService::class)->create($order, [$order->items->first()->id => '10'], null, $this->business->user->id));
        $this->assertSame('cancelled', $this->inBusiness(fn () => $order->fresh()->status));

        $rows = $this->inBusiness(fn () => InvoiceService::awaitingRefund());
        $this->assertTrue($rows->first()['cancel_instead']);
        $this->post(route('business.orders.invoices.refund', $order), ['order_return_id' => $rows->first()['return']->id])->assertSessionHasErrors('invoice');

        $this->post(route('business.invoices.cancel', $sale), ['cancel_reason' => 'Sipariş tamamen iade edildi'])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $this->inBusiness(fn () => $sale->fresh()->status));
        $this->assertContains('CancelEArchiveInvoice', $this->soap);
        $this->assertSame(0, $this->inBusiness(fn () => InvoiceService::awaitingRefund()->count()));
        $this->assertSame(0, $this->inBusiness(fn () => InvoiceService::awaitingSale()->count()), 'İptal edilen sipariş yeniden faturalanmaz.');
    }

    public function test_full_return_after_seven_days_refund_includes_delivery_fee(): void
    {
        $order = $this->completedOrder();
        $this->sentSale($order);
        $this->travel(8)->days();
        $return = $this->inBusiness(fn () => app(ReturnService::class)->create($order, [$order->items->first()->id => '10'], null, $this->business->user->id));

        $refund = $this->inBusiness(fn () => $this->service()->createRefund($order->fresh(), $return)->load('lines'));
        $this->assertSame(['170.00', '170.00'], [(string) $refund->total, (string) $refund->expected_total]);
        $this->assertSame('Teslimat hizmeti', $refund->lines->last()->name);

        // Süresi geçmiş e-Arşiv iptal edilemez.
        $sale = $this->inBusiness(fn () => Invoice::where('kind', 'sale')->sole());
        $this->post(route('business.invoices.cancel', $sale), ['cancel_reason' => 'Geç'])->assertSessionHasErrors('invoice');
    }

    public function test_cancelled_paid_order_without_return_needs_cancel_or_full_refund(): void
    {
        $order = $this->paidOrder();
        $sale = $this->sentSale($order);
        $this->post(route('business.orders.cancel', $order), ['reason' => 'Stok yok'])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $this->inBusiness(fn () => $order->fresh()->status));

        $rows = $this->inBusiness(fn () => InvoiceService::awaitingRefund());
        $this->assertSame([null, true], [$rows->first()['return'], $rows->first()['cancel_instead']]);

        $this->travel(8)->days();
        $refund = $this->inBusiness(fn () => $this->service()->createRefund($order->fresh())->load('lines'));
        $this->assertSame([(string) $sale->total, 2], [(string) $refund->total, $refund->lines->count()]);
        $this->assertNull($refund->order_return_id);
    }

    /* ---------------- Elle kesilen fatura ---------------- */

    public function test_manual_invoice_is_recorded_and_prevents_a_second_invoice(): void
    {
        $order = $this->paidOrder();
        $draft = $this->inBusiness(fn () => $this->service()->createSale($order));

        $this->post(route('business.orders.invoices.manual', $order), ['kind' => 'sale', 'invoice_no' => 'ABC123', 'issue_date' => LocalTime::today(), 'document_type' => 'e_arsiv', 'total' => '170'])
            ->assertSessionHasErrors('invoice_no');
        $this->post(route('business.orders.invoices.manual', $order), ['kind' => 'sale', 'invoice_no' => 'BYA2026000000500', 'issue_date' => LocalTime::today(), 'document_type' => 'e_arsiv', 'total' => '150'])
            ->assertSessionHasErrors('total');
        $this->post(route('business.orders.invoices.manual', $order), ['kind' => 'sale', 'invoice_no' => 'BYA2026000000500', 'issue_date' => LocalTime::today(), 'document_type' => 'e_arsiv', 'total' => '170',
            'pdf' => UploadedFile::fake()->createWithContent('fatura.pdf', 'merhaba')])->assertSessionHasErrors('pdf');

        $this->post(route('business.orders.invoices.manual', $order), ['kind' => 'sale', 'invoice_no' => 'BYA2026000000500', 'issue_date' => LocalTime::today(), 'document_type' => 'e_arsiv', 'total' => '170,00',
            'pdf' => UploadedFile::fake()->createWithContent('fatura.pdf', '%PDF-1.4 elle')])->assertRedirect()->assertSessionHasNoErrors();

        $manual = $this->inBusiness(fn () => Invoice::where('source', 'manual')->sole());
        $this->assertSame(['sent', 'manual', 'BYA2026000000500', '170.00'], [$manual->status, $manual->provider, $manual->invoice_no, (string) $manual->total]);
        $this->assertNull($this->inBusiness(fn () => Invoice::find($draft->id)), 'Açık taslak elle kesilen faturayla değişir.');
        $this->assertSame(0, $this->inBusiness(fn () => InvoiceService::awaitingSale()->count()));
        $this->expectException(DomainException::class);
        $this->inBusiness(fn () => $this->service()->attachManual($order, 'sale', null, ['invoice_no' => 'BYA2026000000501', 'issue_date' => LocalTime::today(), 'document_type' => 'e_arsiv', 'total' => '170'], null));
    }

    /* ---------------- Yalıtım ve sayfalar ---------------- */

    public function test_pages_render_and_dealer_sees_only_own_sent_invoices(): void
    {
        $order = $this->paidOrder();
        $draft = $this->inBusiness(fn () => $this->service()->createSale($order));
        foreach (array_keys(InvoiceController::TABS) as $tab) {
            $this->get(route('business.invoices.index', ['tab' => $tab]))->assertOk();
        }
        $this->get(route('business.invoices.show', $draft))->assertOk()->assertSee('Faturayı kes ve gönder')->assertSee('Teslimat hizmeti');
        $this->get(route('business.invoices.preview', $draft))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('business.orders.show', $order))->assertOk()->assertSee('Fatura');

        // Bayi taslağı göremez.
        $dealer = $this->rel->dealer->user;
        $this->actingAs($dealer, 'dealer')->get(route('dealer.invoices.index'))->assertOk()->assertSee('Henüz faturanız yok');
        $this->get(route('dealer.invoices.pdf', $draft))->assertNotFound();

        $invoice = $this->inBusiness(fn () => $this->service()->send($draft));
        $this->get(route('dealer.invoices.index'))->assertOk()->assertSee($invoice->invoice_no);
        $this->get(route('dealer.orders.show', $order))->assertOk()->assertSee($invoice->invoice_no);
        $this->get(route('dealer.invoices.pdf', $invoice))->assertOk()->assertHeader('Content-Type', 'application/pdf');

        // Aynı toptancının başka bayisi bu faturayı indiremez.
        $otherRel = $this->orderingDealer($this->business);
        $this->flushSession();
        $this->actingAs($otherRel->dealer->user, 'dealer')->get(route('dealer.invoices.pdf', $invoice))->assertNotFound();
        $this->get(route('dealer.invoices.index'))->assertDontSee($invoice->invoice_no);

        // Başka işletme faturaya / siparişe ulaşamaz.
        $other = $this->makeBusiness();
        $this->flushSession();
        $this->actingAs($other->user, 'business');
        $this->get(route('business.invoices.show', $invoice))->assertNotFound();
        $this->get(route('business.invoices.pdf', $invoice))->assertNotFound();
        $this->post(route('business.invoices.cancel', $invoice), ['cancel_reason' => 'x'])->assertNotFound();
        $this->post(route('business.orders.invoices.sale', $order))->assertNotFound();
        $this->get(route('business.invoices.index', ['tab' => 'kesilen']))->assertOk()->assertDontSee($invoice->invoice_no);
    }

    public function test_ubl_type_codes_and_unit_codes(): void
    {
        $this->assertSame('ISTISNA', UblBuilder::typeCode('sale', ['20.00', '0.00']));
        $this->assertSame('SATIS', UblBuilder::typeCode('sale', ['20.00', '10.00']));
        $this->assertSame('IADE', UblBuilder::typeCode('refund', ['0.00']));
        $this->assertSame(['C62', 'KGM', 'C62'], [UblBuilder::unitCode('Adet'), UblBuilder::unitCode('kg'), UblBuilder::unitCode('bilinmeyen')]);
        $this->assertSame(['12.50', '0.8333'], [UblBuilder::price('12.5000'), UblBuilder::price('0.8333')]);

        // Kalem iskontosu UBL'de AllowanceCharge olarak gider; satır tutarı iskonto düşülmüş KDV hariç tutardır.
        $order = $this->withDiscount($this->paidOrder(), '15');
        $invoice = $this->inBusiness(fn () => $this->service()->createSale($order));
        $xml = $this->inBusiness(fn () => app(UblBuilder::class)->xml($invoice->fresh(['lines', 'order']), 'e_arsiv'));
        $item = $invoice->lines()->first();
        $this->assertSame(1, bccomp((string) $item->discount, '0', 2), 'İndirim kuralı kalemde iskonto olarak görünür.');
        $this->assertStringContainsString('<cbc:ChargeIndicator>false</cbc:ChargeIndicator>', $xml);
        $this->assertStringContainsString('<cbc:LineExtensionAmount currencyID="TRY">'.$item->net_amount.'</cbc:LineExtensionAmount>', $xml);
        $this->assertStringContainsString('Ödeme şekli: Havale / EFT', $xml);
    }

    public function test_card_payment_is_reported_as_card_internet_sale(): void
    {
        $this->useUyumsoft();
        // Kart ödemesi (iyzico): sipariş kartla ödenmiş olarak işaretlenir.
        $order = $this->approvedOrder('transfer');
        $this->inBusiness(function () use ($order) {
            OrderPayment::create(['order_id' => $order->id, 'driver' => 'iyzico', 'status' => 'succeeded', 'amount' => $order->grand_total, 'card_last_four' => '4242']);
            $order->forceFill(['payment_method' => 'card', 'payment_status' => 'paid', 'paid_at' => now()])->save();
        });
        $this->sentSale($order->fresh());
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->body(), '<tem:PaymentType>KREDIKARTI/BANKAKARTI</tem:PaymentType>')
            && str_contains($r->body(), '<tem:PaymentMidierName>iyzico</tem:PaymentMidierName>') && str_contains($r->body(), 'Ödeme şekli: Kredi / banka kartı'));
    }
}
