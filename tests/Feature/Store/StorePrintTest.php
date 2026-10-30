<?php

namespace Tests\Feature\Store;

use App\Domain\Store\Cart\CartService;
use App\Domain\Store\Inventory\StoreStockService;
use App\Domain\Store\Print\DocumentTools;
use App\Domain\Store\Print\PrintOptions;
use App\Domain\Store\Print\PrintPricing;
use App\Domain\Store\Print\PrintSettings;
use App\Domain\Store\Returns\ReturnService;
use App\Domain\Store\Sms\SmsGateway;
use App\Http\Controllers\Store\Front\Concerns\CurrentCart;
use App\Models\Store\District;
use App\Models\Store\StoreAdmin;
use App\Models\Store\StoreCart;
use App\Models\Store\StoreOrder;
use App\Models\Store\StorePrintFile;
use App\Models\Store\StorePrintJob;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreSetting;
use App\Models\Store\StoreStockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Fotokopi: parçalı yükleme, sayfa sayımı, sunucuda fiyat, sepet ve ödeme, iade dışı tutma, saklama süresi. */
class StorePrintTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'http://magaza.localhost';

    private StoreCart $cart;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['store.print.chunk_bytes' => 1024]);
        StoreSetting::put(['is_open' => '1', 'company_title' => 'Yapboz Kırtasiye Ltd. Şti.']);
        app(PrintSettings::class)->save(['enabled' => '1'] + $this->settingsForm());

        // Belge araçları sahte: Word → PDF çevrimi ve sayfa sayısı sunucudaki LibreOffice / poppler olmadan.
        $this->instance(DocumentTools::class, new class extends DocumentTools
        {
            public function toPdf(string $source, string $outDir): string
            {
                file_put_contents($outDir.'/cevrildi.pdf', self::pdf(3));

                return $outDir.'/cevrildi.pdf';
            }

            public function pageCount(string $pdf): int
            {
                return $this->pageCountFromContent($pdf);
            }

            public function preview(string $pdf, string $outBase): ?string
            {
                return null;
            }

            public static function pdf(int $pages): string
            {
                return "%PDF-1.4\n1 0 obj << /Type /Pages /Count {$pages} >> endobj\n".str_repeat("2 0 obj << /Type /Page /Parent 1 0 R >> endobj\n", $pages)."%%EOF\n";
            }
        });

        $this->cart = app(CartService::class)->createGuest();
        $this->withCookie(CurrentCart::COOKIE, $this->cart->token)->withCredentials(); // JSON isteklerinde de çerez gitsin
    }

    private function settingsForm(array $override = []): array
    {
        $s = PrintSettings::DEFAULTS;

        return array_replace(['enabled' => $s['enabled'] ? '1' : '0', 'tiers' => $s['tiers'], 'a3_enabled' => '0', 'a3_multiplier' => '2,00',
            'staple' => '5,00', 'staple_max_sheets' => 50, 'spiral' => $s['spiral'], 'min_total' => '0', 'vat_rate' => '20', 'max_mb' => 200,
            'max_copies' => 100, 'unordered_days' => 7, 'ordered_days' => 30, 'prep_text' => 'Baskılar 1 iş günü içinde hazırlanır.'], $override);
    }

    /** En az üç yükleme parçası tutan basit PDF. */
    private static function pdf(int $pages): string
    {
        return "%PDF-1.4\n1 0 obj << /Type /Pages /Count {$pages} >> endobj\n".str_repeat("2 0 obj << /Type /Page /Parent 1 0 R >> endobj\n", $pages)
            .str_repeat("% dolgu\n", 300)."%%EOF\n";
    }

    /** Belgeyi tarayıcı gibi parça parça yükler; belge kaydını döner. */
    private function upload(string $name, string $content): StorePrintFile
    {
        $start = $this->postJson(self::HOST.'/fotokopi/dosya', ['name' => $name, 'size' => strlen($content), 'consent' => '1'])->assertOk();
        $token = $start->json('token');
        foreach (str_split($content, 1024) as $i => $part) {
            $this->post(self::HOST."/fotokopi/dosya/{$token}/parca", ['offset' => $i * 1024, 'chunk' => UploadedFile::fake()->createWithContent('parca', $part)],
                ['Accept' => 'application/json'])->assertOk();
        }

        return StorePrintFile::where('token', $token)->firstOrFail();
    }

    public function test_pricing_is_per_sheet_with_tiers_layout_and_binding(): void
    {
        $pricing = app(PrintPricing::class);
        $settings = app(PrintSettings::class);
        $opts = fn (array $in, int $pages = 40) => PrintOptions::fromInput($in, $pages, $settings);

        // 240 sayfa: yaprak = sayfa ÷ (bir yüzdeki sayfa × önlü arkalıysa 2); ücret yalnızca yaprağa göre.
        foreach ([[[], 240, '360.00'], [['duplex' => '1'], 120, '180.00'], [['duplex' => '1', 'layout' => 2], 60, '120.00'],
            [['duplex' => '1', 'layout' => 4], 30, '60.00'], [['duplex' => '1', 'layout' => 8], 15, '30.00']] as [$in, $sheets, $total]) {
            $q = $pricing->quote(240, $opts($in, 240));
            $this->assertSame([$sheets, $total], [$q['sheets'], $q['total']], json_encode($in));
        }

        // 30 sayfa, S-B tek yüz, 2 kopya = 60 yaprak × 2,00; önlü arkalı ipucu
        $q = $pricing->quote(30, $opts(['copies' => 2], 30));
        $this->assertSame([30, 60, '120.00'], [$q['sheets'], $q['all_sheets'], $q['total']]);
        $this->assertSame('Önlü arkalı seçersen 30 yaprak olur, ₺60,00 daha az ödersin.', $q['tip']);

        // Önlü arkalı + bir yüze 2 sayfa: 10 yaprak; 3 kopya = 30 yaprak × 2,00 + zımba 3 × 5
        $q = $pricing->quote(40, $opts(['duplex' => '1', 'layout' => 2, 'copies' => 3, 'binding' => 'staple']));
        $this->assertSame([20, 10, '75.00', null], [$q['sides'], $q['sheets'], $q['total'], $q['tip']]);

        // 101+ yaprak kademesi: 120 yaprak renkli × 5,00; spiral (120 yaprak → 200'e kadar 55,00)
        $q = $pricing->quote(120, $opts(['color' => 'color', 'binding' => 'spiral'], 120));
        $this->assertSame('655.00', $q['total']);

        // Ters fiyat koruması: 100 yaprak × 2,00 = 200 yerine 101 yaprak × 1,50 = 151,50
        $q = $pricing->quote(100, $opts([], 100));
        $this->assertSame('151.50', $q['total']);
        $this->assertSame(['100 yaprak (üst kademe tutarı: 101 yaprak × ₺1,50)', '₺151,50'], $q['lines'][0]);

        // Sayfa aralığı yalnızca seçilen sayfaları basar.
        $this->assertSame(7, $pricing->quote(40, $opts(['range' => '1-5, 9, 9, 12']))['pages']);

        // Zımba sınırı aşılırsa seçilemez; geçersiz seçenekler reddedilir.
        $this->expectException(\DomainException::class);
        $pricing->quote(120, $opts(['binding' => 'staple'], 120));
    }

    public function test_invalid_options_are_rejected(): void
    {
        $settings = app(PrintSettings::class);
        foreach ([['range' => '5-50'], ['range' => 'abc'], ['copies' => 0], ['copies' => 101], ['size' => 'A3'], ['layout' => 3], ['color' => 'gold']] as $input) {
            try {
                PrintOptions::fromInput($input, 40, $settings);
                $this->fail('Geçersiz seçenek kabul edildi: '.json_encode($input));
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_chunked_upload_counts_pages_and_resumes(): void
    {
        $this->get(self::HOST.'/fotokopi')->assertOk()->assertSee('Belgeni yükle,')->assertSee('Baskılar 1 iş günü içinde hazırlanır.');
        $this->postJson(self::HOST.'/fotokopi/dosya', ['name' => 'a.pdf', 'size' => 10])->assertStatus(422); // telif onayı yok
        $this->postJson(self::HOST.'/fotokopi/dosya', ['name' => 'virus.exe', 'size' => 10, 'consent' => '1'])->assertStatus(422);
        $this->postJson(self::HOST.'/fotokopi/dosya', ['name' => 'buyuk.pdf', 'size' => 201 * 1024 * 1024, 'consent' => '1'])->assertStatus(422);

        $content = self::pdf(12);
        $token = $this->postJson(self::HOST.'/fotokopi/dosya', ['name' => 'Ödev.pdf', 'size' => strlen($content), 'consent' => '1'])->json('token');
        $parts = str_split($content, 1024);
        $send = fn (int $offset, string $part) => $this->post(self::HOST."/fotokopi/dosya/{$token}/parca",
            ['offset' => $offset, 'chunk' => UploadedFile::fake()->createWithContent('parca', $part)], ['Accept' => 'application/json']);

        $send(0, $parts[0])->assertOk()->assertJson(['received' => 1024, 'status' => 'uploading']);
        // Aynı parça yeniden gelirse eklenmez; tarayıcı sunucunun aldığı konumdan sürer.
        $send(0, $parts[0])->assertStatus(409)->assertJson(['received' => 1024]);
        $offset = 1024;
        foreach (array_slice($parts, 1) as $part) {
            $send($offset, $part)->assertOk();
            $offset += strlen($part);
        }

        $this->getJson(self::HOST."/fotokopi/dosya/{$token}")->assertOk()->assertJson(['status' => 'ready', 'pages' => 12, 'name' => 'Ödev.pdf']);
        $this->assertSame($content, Storage::disk('local')->get("store/print/{$token}/original.pdf"));

        // Fiyat sunucuda: 12 yüz × 2,00
        $this->postJson(self::HOST."/fotokopi/dosya/{$token}/fiyat", ['copies' => 1, 'total' => '1'])->assertOk()->assertJson(['quote' => ['total' => '24.00'], 'total' => '₺24,00']);
        $this->postJson(self::HOST."/fotokopi/dosya/{$token}/fiyat", ['range' => '1-40'])->assertStatus(422);

        // Başka sepet bu belgeye erişemez.
        $other = app(CartService::class)->createGuest();
        $this->withCookie(CurrentCart::COOKIE, $other->token)->getJson(self::HOST."/fotokopi/dosya/{$token}")->assertNotFound();
    }

    public function test_office_documents_are_converted_and_mismatched_content_is_rejected(): void
    {
        // Gerçek bir ZIP (docx) içeriği: finfo application/zip veya docx olarak tanır.
        $zip = tempnam(sys_get_temp_dir(), 'docx');
        $archive = new \ZipArchive;
        $archive->open($zip, \ZipArchive::OVERWRITE);
        $archive->addFromString('[Content_Types].xml', '<Types/>');
        $archive->addFromString('word/document.xml', '<w:document/>');
        $archive->close();
        $file = $this->upload('Rapor.docx', file_get_contents($zip));
        unlink($zip);
        $this->assertSame(['ready', 3], [$file->status, $file->pages]);
        $this->assertNotNull($file->pdf_path);

        $fake = $this->upload('sahte.png', self::pdf(1));
        $this->assertSame('failed', $fake->status);
        $this->assertStringContainsString('uyuşmuyor', $fake->error);
        $this->assertFalse(Storage::disk('local')->exists($fake->path));

        $encrypted = $this->upload('sifreli.pdf', "%PDF-1.4\n/Encrypt 5 0 R\n/Type /Page\n%%EOF");
        $this->assertSame('failed', $encrypted->status);
        $this->assertStringContainsString('şifreli', $encrypted->error);
    }

    public function test_print_job_goes_through_cart_and_checkout_with_products(): void
    {
        $product = StoreProduct::create(['name' => 'Jel Kalem', 'slug' => 'jel-kalem', 'is_published' => true]);
        $pen = $product->variants()->create(['sku' => 'JEL-1', 'price' => '100.00', 'vat_rate' => '20']);
        app(StoreStockService::class)->receive($pen, 10);
        app(CartService::class)->add($this->cart, $pen->id, 1);

        $file = $this->upload('Tez.pdf', self::pdf(30));
        $this->postJson(self::HOST."/fotokopi/dosya/{$file->token}/sepet", ['color' => 'bw', 'duplex' => '1', 'copies' => 2, 'binding' => 'spiral', 'note' => 'Kapak renkli'])
            ->assertOk()->assertJson(['added' => true, 'count' => 2]);
        $job = StorePrintJob::firstOrFail();
        // 15 yaprak × 2 kopya = 30 yaprak × 2,00 = 60 + spiral 2 × 30 = 120
        $this->assertSame(['120.00', 15, 30], [$job->price, $job->sheets, $job->sides]);

        $this->get(self::HOST.'/sepet')->assertOk()->assertSee('Fotokopi: Tez.pdf')->assertSee('Önlü arkalı')->assertSee('toplam 30 yaprak')->assertSee('₺220,00');
        $this->put(self::HOST.'/sepet/'.$this->cart->items()->whereNotNull('print_job_id')->value('id'), ['quantity' => 3]); // adet değişmez
        $this->assertSame(1, $this->cart->items()->whereNotNull('print_job_id')->value('quantity'));

        // Yönetim fiyatı değiştirirse sepet yeni fiyatla hesaplanır.
        $tiers = PrintSettings::DEFAULTS['tiers'];
        $tiers[0]['bw'] = '2.50';
        app(PrintSettings::class)->save($this->settingsForm(['enabled' => '1', 'tiers' => $tiers]));
        $this->get(self::HOST.'/sepet')->assertSee('₺235,00');

        StoreSetting::put(['cod_enabled' => '1', 'cod_max' => '3000.00', 'cod_fee' => '0.00', 'local_delivery_fee' => '0.00']);
        $this->get(self::HOST.'/odeme')->assertOk()->assertSee('Fotokopi');
        $sms = [];
        $this->instance(SmsGateway::class, new class($sms) extends SmsGateway
        {
            public static string $last = '';

            public function __construct(array $unused) {}

            public function send(string $phone, string $body, string $purpose, ?string $maskedBody = null): bool
            {
                self::$last = $body;

                return true;
            }
        });
        $gateway = app(SmsGateway::class);
        $this->post(self::HOST.'/odeme', [
            'email' => 'ayse@ornek.com',
            'address' => ['first_name' => 'Ayşe', 'last_name' => 'Yılmaz', 'phone' => '0532 123 45 67', 'province_id' => 26,
                'district_id' => District::where('province_id', 26)->where('name', 'Odunpazarı')->value('id'), 'neighborhood' => 'Arifiye', 'address_line' => 'Hamamyolu Cad. No 12'],
            'billing_type' => 'individual', 'billing_same' => '1', 'delivery_method' => 'local', 'payment_method' => 'cod', 'consent' => '1',
        ])->assertRedirect(route('store.checkout.verify'));
        preg_match('/\b(\d{6})\b/', $gateway::$last, $m);
        $this->post(self::HOST.'/odeme/dogrulama', ['code' => $m[1]]);

        $order = StoreOrder::firstOrFail();
        $this->assertSame('235.00', $order->subtotal);
        $printItem = $order->items()->whereNotNull('print_job_id')->firstOrFail();
        $this->assertSame([null, 'FOTOKOPI-'.$job->id, '135.00', 1], [$printItem->variant_id, $printItem->sku, $printItem->line_total, $printItem->quantity]);
        $this->assertSame($order->id, $job->fresh()->order_id);
        $this->assertTrue($file->fresh()->expires_at->isAfter(now()->addDays(29)), 'siparişteki belge daha uzun saklanır');
        $this->assertSame(1, StoreStockMovement::where('type', 'sale')->where('reference_id', $order->id)->count(), 'baskı stoktan düşmez');
        $this->assertSame(9, $pen->fresh()->stock_quantity);

        // Baskı iade edilemez (kişiye özel üretim).
        $order->update(['status' => 'delivered']);
        $this->assertSame([$order->items()->whereNull('print_job_id')->value('id')], array_keys(app(ReturnService::class)->returnableQuantities($order)));

        // Yönetim baskı dosyasını indirir; başka siparişin adresiyle indiremez.
        $admin = StoreAdmin::create(['name' => 'Yönetici', 'email' => 'yonetici@ornek.com', 'password' => 'Gizli-Parola-123', 'is_super' => true]);
        $this->actingAs($admin, 'store_admin');
        $this->get(route('store_admin.orders.show', $order))->assertOk()->assertSee('Baskı dosyası (30 sayfa)')->assertSee('Müşteri notu: Kapak renkli');
        $this->get(route('store_admin.orders.print-file', [$order, $job, 'pdf']))->assertOk()->assertDownload($order->order_no.'-'.$job->id.'-Tez.pdf');
    }

    public function test_disabled_feature_and_minimum_total_and_settings_page(): void
    {
        $file = $this->upload('Not.pdf', self::pdf(2));
        $this->postJson(self::HOST."/fotokopi/dosya/{$file->token}/sepet", [])->assertOk();

        app(PrintSettings::class)->save($this->settingsForm(['enabled' => '1', 'min_total' => '10,00']));
        $this->get(self::HOST.'/odeme')->assertRedirect(route('store.cart')); // 4,00 < 10,00
        $this->get(self::HOST.'/sepet')->assertSee('Fotokopi siparişi en az ₺10,00 olmalıdır.');

        app(PrintSettings::class)->save($this->settingsForm(['enabled' => '0']));
        $this->get(self::HOST.'/fotokopi')->assertNotFound();
        $this->get(self::HOST.'/sepet')->assertSee('Bu baskı şu anda sipariş edilemiyor');
        $this->get(self::HOST.'/robots.txt')->assertSee('Disallow: /fotokopi/');

        $admin = StoreAdmin::create(['name' => 'Yönetici', 'email' => 'yonetici@ornek.com', 'password' => 'Gizli-Parola-123', 'is_super' => true]);
        $this->actingAs($admin, 'store_admin');
        $this->get(route('store_admin.print.edit'))->assertOk()->assertSee('Baskı fiyatları');
        $this->put(route('store_admin.print.update'), $this->settingsForm(['tiers' => [['from' => 5, 'bw' => '1', 'color' => '1']]]))
            ->assertSessionHasErrors('tiers');
        $this->put(route('store_admin.print.update'), $this->settingsForm(['enabled' => '1', 'staple' => '7,50']))->assertSessionHas('status');
        $this->assertSame('7.50', app(PrintSettings::class)->get('staple'));
    }

    public function test_admin_trial_quote_warnings_and_legacy_tiers(): void
    {
        $admin = StoreAdmin::create(['name' => 'Yönetici', 'email' => 'yonetici@ornek.com', 'password' => 'Gizli-Parola-123', 'is_super' => true]);
        $this->actingAs($admin, 'store_admin');

        $this->get(route('store_admin.print.edit', ['deneme' => ['pages' => 240, 'color' => 'bw', 'duplex' => '1', 'layout' => 2, 'copies' => 1, 'binding' => 'none']]))
            ->assertOk()->assertSee('toplam <strong>60 yaprak</strong>', false)->assertSee('Toplam: ₺120,00');
        $this->get(route('store_admin.print.edit', ['deneme' => ['pages' => 240, 'binding' => 'staple']]))->assertSee('Zımba en fazla 50 yaprakta');

        // Üst kademe daha pahalıysa uyarı gösterilir, kayıt engellenmez.
        $tiers = PrintSettings::DEFAULTS['tiers'];
        $tiers[1]['color'] = '7.00';
        $this->put(route('store_admin.print.update'), $this->settingsForm(['enabled' => '1', 'tiers' => $tiers]))->assertSessionHas('status');
        $this->get(route('store_admin.print.edit'))->assertSee('Renkli: 101 yapraktan başlayan kademenin yaprak fiyatı (₺7,00) bir önceki kademeden (₺6,00) yüksek.');

        // Yüz başı fiyatlı eski kayıt: tek yüz fiyatları yaprak fiyatı olur.
        StoreSetting::put(['print' => json_encode(['enabled' => true, 'tiers' => [['from' => 1, 'bw' => '3.00', 'bw_duplex' => '2.50', 'color' => '8.00', 'color_duplex' => '7.00']]])]);
        $this->assertSame([['from' => 1, 'bw' => '3.00', 'color' => '8.00']], app(PrintSettings::class)->get('tiers'));
        $this->assertSame('30.00', app(PrintPricing::class)->quote(20, PrintOptions::fromInput(['duplex' => '1'], 20, app(PrintSettings::class)))['total']);
    }

    public function test_expired_files_are_pruned(): void
    {
        $file = $this->upload('Eski.pdf', self::pdf(1));
        $keep = $this->upload('Yeni.pdf', self::pdf(1));
        $this->postJson(self::HOST."/fotokopi/dosya/{$file->token}/sepet", [])->assertOk();
        $file->update(['expires_at' => now()->subMinute()]);

        $this->artisan('store:prune-print-files')->expectsOutput('1 fotokopi belgesi silindi.')->assertSuccessful();
        $this->assertSame('deleted', $file->fresh()->status, 'işi olan belgenin kaydı kalır');
        $this->assertFalse(Storage::disk('local')->exists($file->path));
        $this->assertTrue(Storage::disk('local')->exists($keep->path));
        $this->get(self::HOST.'/sepet')->assertSee('Bu baskı şu anda sipariş edilemiyor');

        // Müşteri kaldırırsa işi olmayan belgenin kaydı da silinir.
        $this->deleteJson(self::HOST."/fotokopi/dosya/{$keep->token}")->assertOk();
        $this->assertNull(StorePrintFile::find($keep->id));
    }
}
