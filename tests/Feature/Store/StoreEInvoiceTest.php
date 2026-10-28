<?php

namespace Tests\Feature\Store;

use App\Domain\Store\Cart\CartService;
use App\Domain\Store\Inventory\StoreStockService;
use App\Domain\Store\Invoicing\EInvoiceService;
use App\Domain\Store\Invoicing\InvoiceMath;
use App\Domain\Store\Invoicing\Providers\DenemeProvider;
use App\Domain\Store\Invoicing\Providers\EInvoiceUncertain;
use App\Domain\Store\Invoicing\UblBuilder;
use App\Http\Controllers\Store\Front\Concerns\CurrentCart;
use App\Models\Store\District;
use App\Models\Store\StoreAdmin;
use App\Models\Store\StoreCreditNote;
use App\Models\Store\StoreEInvoice;
use App\Models\Store\StoreInvoice;
use App\Models\Store\StoreOrder;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreProductVariant;
use App\Models\Store\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

/** Faturalar: ödemede otomatik fatura yok; taslak düzenlenip elle gönderilir (deneme sürücüsü ve Uyumsoft). */
class StoreEInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'http://magaza.localhost';

    private const ADMIN = 'http://yonetim.localhost';

    private StoreProductVariant $pen;

    private StoreProductVariant $notebook;

    /** @var list<string> Uyumsoft'a giden SOAP işlemleri */
    private array $soap = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        StoreSetting::put(['is_open' => '1', 'card_enabled' => '1', 'iyzico_mode' => 'sandbox', 'shipping_cargo_fee' => '50.00',
            'company_title' => 'Ebru Birbilen – Yapboz Kırtasiye', 'company_tax_office' => 'Eskişehir Vergi Dairesi', 'company_tax_number' => '1770247133',
            'address' => 'Kurtuluş Mah. Ziya Paşa Cad. No 26 B ODUNPAZARI / ESKİŞEHİR']);
        StoreSetting::putSecret('iyzico_api_key', 'sandbox-api-key');
        StoreSetting::putSecret('iyzico_secret_key', 'sandbox-secret-key');

        $product = StoreProduct::create(['name' => 'Jel Kalem', 'slug' => 'jel-kalem', 'is_published' => true]);
        $this->pen = $product->variants()->create(['sku' => 'JEL-1', 'price' => '100.00', 'vat_rate' => '20']);
        $other = StoreProduct::create(['name' => 'Defter', 'slug' => 'defter', 'is_published' => true]);
        $this->notebook = $other->variants()->create(['sku' => 'DEF-1', 'price' => '30.00', 'vat_rate' => '10']);
        app(StoreStockService::class)->receive($this->pen, 10);
        app(StoreStockService::class)->receive($this->notebook, 10);

        Http::fake(['*' => fn (HttpRequest $r) => $this->fakeHttp($r)]);
        $this->actingAs(StoreAdmin::create(['name' => 'Süper', 'email' => 'super@magaza.local', 'password' => 'Gizli-Parola-123', 'is_super' => true]), 'store_admin');
    }

    private function fakeHttp(HttpRequest $r)
    {
        if (str_contains($r->url(), 'uyumsoft')) {
            preg_match('#<tem:(\w+)>#', $r->body(), $m);
            $this->soap[] = $m[1] ?? '?';

            return Http::response(match ($m[1] ?? '') {
                'WhoAmI' => $this->soapResult('WhoAmI', '<Value Title="EBRU BİRBİLEN"/>'),
                'IsEInvoiceUser' => $this->soapResult('IsEInvoiceUser', '', 'Value="false"'),
                'SendInvoice' => $this->soapResult('SendInvoice', '<Value Id="11111111-2222-3333-4444-555555555555" Number="YAP2026000000001"/>'),
                'GetOutboxInvoicePdf' => $this->soapResult('GetOutboxInvoicePdf', '<Value><Data>'.base64_encode('%PDF-1.4 uyumsoft').'</Data></Value>'),
                default => $this->soapResult($m[1] ?? 'X', '', 'IsSucceded="false" Message="Bilinmeyen"'),
            });
        }
        $order = StoreOrder::latest('id')->first();
        if (str_contains($r->url(), 'initialize')) {
            return Http::response(['status' => 'success', 'token' => 'tok-'.$order->id, 'paymentPageUrl' => 'https://sandbox.iyzico.test/odeme']);
        }
        if (str_contains($r->url(), 'detail')) {
            return Http::response(['status' => 'success', 'paymentStatus' => 'SUCCESS', 'paymentId' => 'p-'.$order->id, 'basketId' => $order->order_no,
                'price' => (float) $order->total, 'paidPrice' => (float) $order->total, 'fraudStatus' => 1, 'itemTransactions' => []]);
        }

        return Http::response([], 404);
    }

    private function soapResult(string $method, string $inner, string $attrs = 'IsSucceded="true"'): string
    {
        return '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body><'.$method.'Response xmlns="http://tempuri.org/">'
            .'<'.$method.'Result '.$attrs.'>'.$inner.'</'.$method.'Result></'.$method.'Response></s:Body></s:Envelope>';
    }

    /** Kartla ödenmiş sipariş: 2 × 100 (KDV %20) + 1 × 30 (KDV %10) + 50 kargo = 280. */
    private function paidOrder(array $billing = ['billing_type' => 'individual']): StoreOrder
    {
        $carts = app(CartService::class);
        $cart = $carts->createGuest();
        $carts->add($cart, $this->pen->id, 2);
        $carts->add($cart, $this->notebook->id, 1);
        $this->withCookie(CurrentCart::COOKIE, $cart->token);
        $this->post(self::HOST.'/odeme', $billing + [
            'email' => 'ayse@ornek.com',
            'address' => ['first_name' => 'Ayşe', 'last_name' => 'Yılmaz', 'phone' => '05321234567', 'province_id' => 6,
                'district_id' => District::where('province_id', 6)->where('name', 'Çankaya')->value('id'), 'address_line' => 'Atatürk Bulvarı No 5 D 2'],
            'billing_same' => '1', 'delivery_method' => 'cargo', 'payment_method' => 'card', 'consent' => '1',
        ])->assertRedirect('https://sandbox.iyzico.test/odeme');
        $order = StoreOrder::latest('id')->firstOrFail();
        $this->post(self::HOST.'/odeme/iyzico/donus', ['token' => 'tok-'.$order->id]);

        return tap($order->refresh(), fn ($o) => $this->assertSame(['paid', '280.00'], [$o->status, $o->total]));
    }

    /** Taslağın formdaki hâli (kalemler Türkçe sayı biçimiyle). */
    private function form(StoreEInvoice $invoice, array $override = []): array
    {
        return array_replace([
            'buyer_type' => $invoice->buyer_type, 'buyer_name' => $invoice->buyer_name, 'buyer_tax_number' => $invoice->buyer_tax_number,
            'buyer_tax_office' => $invoice->buyer_tax_office, 'buyer_address' => $invoice->buyer_address, 'buyer_district' => $invoice->buyer_district,
            'buyer_city' => $invoice->buyer_city, 'buyer_email' => $invoice->buyer_email, 'buyer_phone' => $invoice->buyer_phone,
            'issue_date' => now('Europe/Istanbul')->toDateString(), 'note' => null, 'notify_customer' => '0',
            'lines' => $invoice->lines->map(fn ($l) => [
                'order_item_id' => $l->order_item_id, 'name' => $l->name, 'sku' => $l->sku, 'quantity' => (string) (float) $l->quantity,
                'unit_price' => str_replace('.', ',', (string) $l->unit_price), 'discount' => '', 'vat_rate' => (string) (int) $l->vat_rate,
            ])->all(),
        ], $override);
    }

    public function test_line_math_keeps_vat_inclusive_totals(): void
    {
        $this->assertSame(['net_amount' => '166.67', 'vat_amount' => '33.33', 'line_total' => '200.00', 'gross' => '200.00'], InvoiceMath::line('2', '100.00', '0', '20'));
        $this->assertSame('90.00', InvoiceMath::line('3', '33.33', '9.99', '10')['line_total']);
        $totals = InvoiceMath::totals([
            ['net_amount' => '166.67', 'vat_amount' => '33.33', 'line_total' => '200.00', 'vat_rate' => '20.00'],
            ['net_amount' => '27.27', 'vat_amount' => '2.73', 'line_total' => '30.00', 'vat_rate' => '10.00'],
        ]);
        $this->assertSame(['193.94', '36.06', '230.00'], [$totals['subtotal'], $totals['vat_total'], $totals['total']]);
        $this->assertSame(['20', '10'], array_map('strval', array_keys($totals['by_rate'])));
    }

    public function test_payment_does_not_issue_invoice_order_waits_in_list(): void
    {
        $order = $this->paidOrder();
        $this->assertFalse($order->invoice()->exists());
        $this->assertSame(0, StoreEInvoice::count());

        $this->get(self::ADMIN.'/faturalar')->assertOk()->assertSee('Fatura bekleyen')->assertSee($order->order_no)->assertSee('Deneme sürücüsü açık');
        $this->get(self::ADMIN.'/siparisler/'.$order->id)->assertOk()->assertSee(route('store_admin.invoices.create-sale', $order));
    }

    public function test_admin_edits_quantities_and_prices_then_sends_with_difference_confirmation(): void
    {
        $order = $this->paidOrder();
        $this->post(self::ADMIN."/siparisler/{$order->id}/fatura-taslagi")->assertRedirect();
        $invoice = StoreEInvoice::with('lines')->sole();
        $this->assertSame(['draft', 'sale', '280.00', '280.00', 3], [$invoice->status, $invoice->kind, $invoice->total, $invoice->expected_total, $invoice->lines->count()]);
        $this->assertSame(['Jel Kalem', 'Defter', 'Kargo hizmeti'], $invoice->lines->pluck('name')->all());
        $this->assertSame('Ayşe Yılmaz', $invoice->buyer_name);
        // Aynı sipariş için ikinci taslak açılmaz, mevcut taslak açılır.
        $this->post(self::ADMIN."/siparisler/{$order->id}/fatura-taslagi")->assertRedirect(route('store_admin.invoices.edit', $invoice));
        $this->get(self::ADMIN.'/faturalar/'.$invoice->id)->assertOk()->assertSee('x-data="invoiceEditor(', false)->assertSee('Kaydet ve faturayı kes');

        // Kalemler değiştirilir: kalem 1 × 90, defter silinir, yeni kalem eklenir.
        $form = $this->form($invoice);
        $form['lines'][0]['quantity'] = '1';
        $form['lines'][0]['unit_price'] = '90,00';
        unset($form['lines'][1]);
        $form['lines'][] = ['name' => 'Hediye paketi', 'quantity' => '1', 'unit_price' => '10', 'discount' => '', 'vat_rate' => '20'];
        $this->put(self::ADMIN.'/faturalar/'.$invoice->id, $form + ['action' => 'save'])->assertSessionHasNoErrors();
        $invoice->refresh();
        $this->assertSame(['150.00', '-130.00', 3], [$invoice->total, $invoice->difference(), $invoice->lines()->count()]);

        // Fark onaylanmadan gönderilmez.
        $this->put(self::ADMIN.'/faturalar/'.$invoice->id, $form + ['action' => 'send'])->assertSessionHasErrors('invoice');
        $this->assertSame('draft', $invoice->fresh()->status);

        $this->put(self::ADMIN.'/faturalar/'.$invoice->id, $form + ['action' => 'send', 'confirm_difference' => '1'])->assertSessionHasNoErrors();
        $invoice->refresh();
        $this->assertSame(['sent', 'deneme', 'e_arsiv'], [$invoice->status, $invoice->provider, $invoice->document_type]);
        $this->assertMatchesRegularExpression('/^DNM\d{4}0{8}1$/', $invoice->invoice_no);
        Storage::disk('local')->assertExists($invoice->pdf_path);

        // Müşteri tarafındaki fatura kaydı ve PDF; sipariş tutarı ve stok değişmedi.
        $record = StoreInvoice::where('order_id', $order->id)->sole();
        $this->assertSame([$invoice->invoice_no, 'deneme', $invoice->pdf_path, $invoice->ettn], [$record->invoice_no, $record->provider, $record->file_path, $record->provider_uuid]);
        $this->assertSame(['280.00', 8], [$order->fresh()->total, $this->pen->fresh()->stock_quantity]);
        $this->assertTrue($order->events()->where('note', 'like', 'Fatura kesildi (e-Arşiv)%')->exists());

        // Kesilen fatura değişmez, elle fatura yüklenemez.
        $this->put(self::ADMIN.'/faturalar/'.$invoice->id, $form + ['action' => 'save'])->assertSessionHasErrors('invoice');
        $this->get(self::ADMIN.'/faturalar/'.$invoice->id)->assertOk()->assertSee($invoice->invoice_no)->assertSee('Faturayı iptal et')->assertDontSee('Kaydet ve faturayı kes');
        $this->get(self::ADMIN.'/faturalar/'.$invoice->id.'/pdf')->assertOk();
        $this->assertNotContains($order->id, EInvoiceService::awaitingSale()->pluck('id')->all());
    }

    public function test_validation_of_buyer_and_lines(): void
    {
        $order = $this->paidOrder();
        $invoice = app(EInvoiceService::class)->createSale($order)->load('lines');

        $this->put(self::ADMIN.'/faturalar/'.$invoice->id, $this->form($invoice, ['buyer_type' => 'corporate', 'buyer_tax_number' => '123', 'buyer_tax_office' => '']) + ['action' => 'save'])
            ->assertSessionHasErrors(['buyer_tax_office', 'buyer_tax_number']);

        $form = $this->form($invoice);
        $form['lines'][0]['quantity'] = '0';
        $form['lines'][1]['discount'] = '999';
        $form['lines'][2]['vat_rate'] = '18';
        $this->put(self::ADMIN.'/faturalar/'.$invoice->id, $form + ['action' => 'save'])->assertSessionHasErrors(['lines.0.quantity', 'lines.1.discount', 'lines.2.vat_rate']);

        $this->put(self::ADMIN.'/faturalar/'.$invoice->id, $this->form($invoice, ['lines' => []]) + ['action' => 'save'])->assertSessionHasErrors('lines');
        $this->assertSame('280.00', $invoice->fresh()->total);
    }

    public function test_e_fatura_buyer_detected_and_e_arsiv_cancel_allows_new_draft(): void
    {
        // Deneme sürücüsünde VKN 1111111111 e-Fatura mükellefi sayılır.
        $order = $this->paidOrder();
        $service = app(EInvoiceService::class);
        $invoice = $service->createSale($order)->load('lines');
        $this->put(self::ADMIN.'/faturalar/'.$invoice->id, $this->form($invoice, ['buyer_type' => 'corporate', 'buyer_name' => 'Örnek Ltd. Şti.',
            'buyer_tax_number' => DenemeProvider::EFATURA_TEST_VKN, 'buyer_tax_office' => 'Çankaya']) + ['action' => 'send'])->assertSessionHasNoErrors();
        $invoice->refresh();
        $this->assertSame(['sent', 'e_fatura'], [$invoice->status, $invoice->document_type]);
        $this->post(self::ADMIN.'/faturalar/'.$invoice->id.'/iptal', ['cancel_reason' => 'Hatalı'])->assertSessionHasErrors('invoice');

        // e-Arşiv iptal edilir; müşteri tarafındaki fatura kalkar, yeni taslak hazırlanabilir.
        $second = $this->paidOrder();
        $doc = $service->send($service->createSale($second));
        $this->assertSame('e_arsiv', $doc->document_type);
        $this->post(self::ADMIN.'/faturalar/'.$doc->id.'/iptal', ['cancel_reason' => ''])->assertSessionHasErrors('cancel_reason');
        $this->post(self::ADMIN.'/faturalar/'.$doc->id.'/iptal', ['cancel_reason' => 'Alıcı bilgisi hatalı'])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $doc->fresh()->status);
        $this->assertFalse($second->invoice()->exists());
        $this->assertContains($second->id, EInvoiceService::awaitingSale()->pluck('id')->all());
        $this->assertNotSame($doc->id, $service->createSale($second->fresh())->id);
    }

    public function test_uncertain_send_is_not_resent_until_checked(): void
    {
        $order = $this->paidOrder();
        $service = app(EInvoiceService::class);
        $invoice = $service->createSale($order);

        $provider = Mockery::mock(DenemeProvider::class)->makePartial();
        $provider->shouldReceive('send')->once()->andThrow(new EInvoiceUncertain('Bağlantı koptu.'));
        $provider->shouldReceive('find')->once()->andReturn(null);
        $this->app->instance(DenemeProvider::class, $provider);

        $this->put(self::ADMIN.'/faturalar/'.$invoice->id, $this->form($invoice->load('lines')) + ['action' => 'send'])->assertSessionHasErrors('invoice');
        $invoice->refresh();
        $this->assertSame('unknown', $invoice->status);
        $this->assertNotNull($invoice->ettn);
        $this->get(self::ADMIN.'/faturalar?tab=sorunlu')->assertOk()->assertSee('Sonuç belirsiz');
        // Belirsizken yeniden gönderilemez.
        $this->put(self::ADMIN.'/faturalar/'.$invoice->id, $this->form($invoice) + ['action' => 'send'])->assertSessionHasErrors('invoice');

        $this->post(self::ADMIN.'/faturalar/'.$invoice->id.'/sorgula')->assertSessionHasNoErrors();
        $this->assertSame('draft', $invoice->fresh()->status);
        $this->assertFalse($order->invoice()->exists());
    }

    public function test_refund_invoice_from_refunded_amount(): void
    {
        $order = $this->paidOrder();
        $service = app(EInvoiceService::class);
        $this->post(self::ADMIN."/siparisler/{$order->id}/iade-faturasi-taslagi")->assertSessionHasErrors('invoice'); // satış faturası yok
        $sale = $service->send($service->createSale($order));

        $order->update(['refunded_total' => '100.00', 'payment_status' => 'partially_refunded']);
        $this->assertContains($order->id, EInvoiceService::awaitingRefund()->pluck('id')->all());
        $this->post(self::ADMIN."/siparisler/{$order->id}/iade-faturasi-taslagi")->assertRedirect();
        $refund = StoreEInvoice::where('kind', 'refund')->with('lines')->sole();
        $this->assertSame(['100.00', '280.00'], [$refund->expected_total, $refund->total]);

        // Yalnızca iade edilen kalem bırakılır: 1 kalem × 100.
        $form = $this->form($refund);
        $form['lines'] = [array_replace($form['lines'][0], ['quantity' => '1'])];
        $this->put(self::ADMIN.'/faturalar/'.$refund->id, $form + ['action' => 'send'])->assertSessionHasNoErrors();
        $refund->refresh();
        $this->assertSame(['sent', '100.00'], [$refund->status, $refund->total]);
        $note = StoreCreditNote::where('order_id', $order->id)->sole();
        $this->assertSame([$refund->invoice_no, '100.00'], [$note->number, $note->amount]);
        Storage::disk('local')->assertExists($note->file_path);
        $this->assertStringContainsString('IADE', app(UblBuilder::class)->xml($refund, 'e_arsiv', ['invoice_no' => $sale->invoice_no, 'issue_date' => $sale->issue_date->format('Y-m-d')]));

        // İade faturası varken satış faturası iptal edilemez.
        $this->post(self::ADMIN.'/faturalar/'.$sale->id.'/iptal', ['cancel_reason' => 'Deneme'])->assertSessionHasErrors('invoice');
    }

    public function test_bulk_drafts_and_bulk_send_skip_unconfirmed_difference(): void
    {
        $a = $this->paidOrder();
        $b = $this->paidOrder();
        $this->post(self::ADMIN.'/faturalar/toplu-taslak', ['orders' => [$a->id, $b->id]])->assertRedirect();
        $this->assertSame(2, StoreEInvoice::where('status', 'draft')->count());

        $second = StoreEInvoice::where('order_id', $b->id)->with('lines')->sole();
        $form = $this->form($second);
        $form['lines'][0]['unit_price'] = '80';
        $this->put(self::ADMIN.'/faturalar/'.$second->id, $form + ['action' => 'save'])->assertSessionHasNoErrors();

        $this->post(self::ADMIN.'/faturalar/toplu-gonder', ['invoices' => StoreEInvoice::pluck('id')->all()])->assertSessionHasErrors('invoice');
        $this->assertSame('sent', StoreEInvoice::where('order_id', $a->id)->value('status'));
        $this->assertSame('draft', $second->fresh()->status);
    }

    public function test_settings_are_super_admin_only_and_uyumsoft_flow(): void
    {
        $this->get(self::ADMIN.'/ayarlar/fatura')->assertOk()->assertSee('Uyumsoft kullanıcı adı');
        $this->put(self::ADMIN.'/ayarlar/fatura', ['invoice_driver' => 'uyumsoft', 'uyumsoft_mode' => 'test', 'invoice_shipping_vat' => '20'])->assertSessionHasErrors('uyumsoft_username');
        $this->put(self::ADMIN.'/ayarlar/fatura', ['invoice_driver' => 'uyumsoft', 'uyumsoft_mode' => 'test', 'uyumsoft_username' => 'Uyumsoft', 'uyumsoft_password' => 'gizli',
            'invoice_sender_id' => '18508621764', 'invoice_shipping_vat' => '20', 'invoice_notify' => '1'])->assertSessionHasNoErrors();
        $this->assertNotSame('gizli', StoreSetting::get('uyumsoft_password'));
        $this->assertSame('gizli', StoreSetting::secret('uyumsoft_password'));
        $this->get(self::ADMIN.'/ayarlar/fatura')->assertDontSee('gizli');

        $this->post(self::ADMIN.'/ayarlar/fatura/dene')->assertSessionHas('status', fn ($s) => str_contains($s, 'EBRU BİRBİLEN'));

        // Uyumsoft ile gönderim: UBL içinde satıcı TCKN'si ve e-Arşiv profili, numara ve PDF Uyumsoft'tan gelir.
        $order = $this->paidOrder(['billing_type' => 'individual']);
        $service = app(EInvoiceService::class);
        $invoice = $service->send($service->createSale($order));
        $this->assertSame(['sent', 'uyumsoft', 'e_arsiv', 'YAP2026000000001', '11111111-2222-3333-4444-555555555555'],
            [$invoice->status, $invoice->provider, $invoice->document_type, $invoice->invoice_no, $invoice->ettn]);
        $this->assertSame('%PDF-1.4 uyumsoft', Storage::disk('local')->get($invoice->pdf_path));
        $this->assertSame(['WhoAmI', 'SendInvoice', 'GetOutboxInvoicePdf'], $this->soap);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'efatura-test.uyumsoft.com.tr') && str_contains($r->body(), '<cbc:ProfileID>EARSIVFATURA</cbc:ProfileID>')
            && str_contains($r->body(), 'schemeID="TCKN">18508621764') && str_contains($r->body(), '<tem:Scenario>eArchive</tem:Scenario>')
            && str_contains($r->body(), 'PaymentType="KREDIKARTI/BANKAKARTI"') && ! str_contains($r->body(), 'gizli"/><tem:userInfo'));

        // Süper admin olmayan yönetici ayarları göremez, faturaları görür.
        $this->flushSession();
        $this->actingAs(StoreAdmin::create(['name' => 'Yönetici', 'email' => 'y@magaza.local', 'password' => 'Gizli-Parola-123']), 'store_admin');
        $this->get(self::ADMIN.'/ayarlar/fatura')->assertForbidden();
        $this->get(self::ADMIN.'/faturalar?tab=gonderilen')->assertOk()->assertSee('YAP2026000000001');
    }

    public function test_ubl_document_structure(): void
    {
        $order = $this->paidOrder();
        $invoice = app(EInvoiceService::class)->createSale($order);
        $invoice->ettn = 'aaaa-bbbb';
        $xml = app(UblBuilder::class)->xml($invoice, 'e_fatura');
        $doc = new \DOMDocument;
        $this->assertTrue($doc->loadXML($xml));
        $x = new \DOMXPath($doc);
        $x->registerNamespace('cbc', UblBuilder::NS_CBC);
        $x->registerNamespace('cac', UblBuilder::NS_CAC);
        $this->assertSame('TEMELFATURA', $x->evaluate('string(//cbc:ProfileID)'));
        $this->assertSame('SATIS', $x->evaluate('string(//cbc:InvoiceTypeCode)'));
        $this->assertSame('3', $x->evaluate('string(//cbc:LineCountNumeric)'));
        $this->assertSame('280.00', $x->evaluate('string(//cac:LegalMonetaryTotal/cbc:PayableAmount)'));
        $this->assertSame('1770247133', $x->evaluate('string(//cac:AccountingSupplierParty//cac:PartyIdentification/cbc:ID)'));
        $this->assertSame('ESKİŞEHİR', $x->evaluate('string(//cac:AccountingSupplierParty//cbc:CityName)'));
        $this->assertSame('11111111111', $x->evaluate('string(//cac:AccountingCustomerParty//cac:PartyIdentification/cbc:ID)'));
        $this->assertSame('Yılmaz', $x->evaluate('string(//cac:AccountingCustomerParty//cac:Person/cbc:FamilyName)'));
        $this->assertSame(2.0, $x->evaluate('count(/*/cac:TaxTotal/cac:TaxSubtotal)'));
    }

    public function test_launch_checklist_shows_invoice_item(): void
    {
        $this->get(self::ADMIN.'/ayarlar/yayin-hazirligi')->assertOk()->assertSee('e-Fatura / e-Arşiv (Uyumsoft)')->assertSee('Deneme sürücüsü açık');
    }
}
