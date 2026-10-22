<?php

namespace Tests\Feature\Store;

use App\Domain\Store\Cart\CartService;
use App\Domain\Store\Inventory\StoreStockService;
use App\Domain\Store\Orders\CheckoutService;
use App\Models\Store\District;
use App\Models\Store\StoreAdmin;
use App\Models\Store\StoreCustomer;
use App\Models\Store\StoreLegalDocument;
use App\Models\Store\StoreOrder;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Mağaza M9: yasal sayfalar, çerez bandı, bot koruması, KVKK hesap kapatma, yayın hazırlığı ve ödeme logo bandı. */
class StoreComplianceTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'http://magaza.localhost';

    protected function setUp(): void
    {
        parent::setUp();
        StoreSetting::put(['is_open' => '1', 'company_title' => 'Yapboz Kırtasiye Ltd. Şti.', 'return_days' => '30', 'address' => 'Hamamyolu Cad. No 1 Eskişehir']);
    }

    public function test_legal_pages_footer_and_cookie_banner(): void
    {
        foreach (StoreLegalDocument::KEYS as $key => $title) {
            $this->get(self::HOST.'/yasal/'.$key)->assertOk()->assertSee($title);
        }
        $this->get(self::HOST.'/yasal/iptal-iade')->assertSee('30 gün')->assertSee('Hamamyolu Cad. No 1 Eskişehir')->assertDontSee('{IADE_SURESI}');
        $this->get(self::HOST.'/yasal/gizlilik-cerez')->assertSee('<table>', false)->assertSee('Çerez tercihlerimi değiştir');
        $this->get(self::HOST.'/yasal/kvkk')->assertSee('Yapboz Kırtasiye Ltd. Şti.')->assertDontSee('noindex', false);
        $this->get(self::HOST.'/yasal/mesafeli-satis')->assertSee('noindex', false);
        $this->get(self::HOST.'/yasal/yok')->assertNotFound();

        $this->get(self::HOST.'/')->assertOk()
            ->assertSee('x-data="cookieConsent"', false)->assertSee('Çerez tercihleri')
            ->assertSee(route('store.legal', 'kvkk'))->assertSee(route('store.legal', 'iptal-iade'));
        $this->get(self::HOST.'/uye-ol')->assertSee(route('store.legal', 'kvkk'));

        // Ödemede yalnızca iki sözleşme onaylanır ve siparişe kopyalanır.
        $this->assertSame(['on-bilgilendirme', 'mesafeli-satis'], StoreLegalDocument::CHECKOUT_KEYS);
        $this->actingAs(StoreAdmin::create(['name' => 'Y', 'email' => 'y@m.local', 'password' => self::PASSWORD]), 'store_admin');
        $this->get(route('store_admin.legal.index'))->assertOk()->assertSee('KVKK Aydınlatma Metni')->assertSee('Gizlilik ve Çerez Politikası');
    }

    public function test_bot_guard_honeypot_and_minimum_time(): void
    {
        config(['store.bot_protection' => true]);
        $form = ['first_name' => 'Ayşe', 'last_name' => 'Yılmaz', 'email' => 'ayse@ornek.com', 'phone' => '05321234567',
            'password' => 'GucluParola123', 'password_confirmation' => 'GucluParola123', 'kvkk' => '1'];
        $old = Crypt::encryptString((string) (time() - 10));

        $this->post(self::HOST.'/uye-ol', $form)->assertSessionHasErrors('form'); // zaman alanı yok
        $this->post(self::HOST.'/uye-ol', $form + ['_ft' => 'bozuk'])->assertSessionHasErrors('form');
        $this->post(self::HOST.'/uye-ol', $form + ['_ft' => Crypt::encryptString((string) time())])->assertSessionHasErrors('form'); // çok hızlı
        $this->post(self::HOST.'/uye-ol', $form + ['_ft' => Crypt::encryptString((string) (time() - 13 * 3600))])->assertSessionHasErrors('form'); // çok eski
        $this->post(self::HOST.'/uye-ol', $form + ['_ft' => $old, 'website' => 'http://spam.example'])->assertSessionHasErrors('form'); // tuzak alanı
        $this->assertSame(0, StoreCustomer::count());

        $this->post(self::HOST.'/uye-ol', $form + ['_ft' => $old, 'website' => ''])->assertSessionHasNoErrors();
        $this->assertSame(1, StoreCustomer::count());
        auth('customer')->logout();
        $this->flushSession();

        // Girişte en kısa süre uygulanmaz (parola yöneticileri), tuzak alanı uygulanır.
        $this->post(self::HOST.'/giris', ['email' => 'ayse@ornek.com', 'password' => 'GucluParola123', '_ft' => Crypt::encryptString((string) time()), 'website' => 'x'])
            ->assertSessionHasErrors('form');
        $this->post(self::HOST.'/giris', ['email' => 'ayse@ornek.com', 'password' => 'GucluParola123', '_ft' => Crypt::encryptString((string) time())])
            ->assertSessionHasNoErrors();
        $this->assertAuthenticated('customer');

        auth('customer')->logout();
        $this->flushSession();
        // Formlar tuzak ve zaman alanlarını taşır.
        $this->get(self::HOST.'/giris')->assertSee('name="website"', false)->assertSee('name="_ft"', false);
    }

    public function test_customer_closes_account_kvkk(): void
    {
        StoreSetting::put(['cod_enabled' => '1', 'cod_first_order_approval' => '0']);
        $customer = StoreCustomer::create(['first_name' => 'Ali', 'last_name' => 'Kaya', 'email' => 'ali@ornek.com', 'phone' => '05329998877', 'password' => self::PASSWORD]);
        $district = District::where('province_id', 26)->where('name', 'Tepebaşı')->value('id');
        $customer->addresses()->create(['title' => 'Ev', 'first_name' => 'Ali', 'last_name' => 'Kaya', 'phone' => '05329998877', 'province_id' => 26, 'district_id' => $district, 'address_line' => 'İki Eylül Cad. No 1']);

        $pen = StoreProduct::create(['name' => 'Kalem', 'slug' => 'kalem', 'is_published' => true])->variants()->create(['sku' => 'K-1', 'price' => '10.00', 'vat_rate' => '20']);
        app(StoreStockService::class)->receive($pen, 5);
        $carts = app(CartService::class);
        $cart = $carts->forCustomer($customer);
        $carts->add($cart, $pen->id, 1);
        $checkout = app(CheckoutService::class);
        $order = $checkout->place($checkout->validate(['address_id' => $customer->addresses()->value('id'), 'billing_type' => 'individual', 'billing_same' => '1',
            'delivery_method' => 'local', 'payment_method' => 'cod', 'consent' => '1'], $customer, $carts->summary($cart)), $customer, $cart, null, null);

        $this->actingAs($customer, 'customer');
        $this->delete(self::HOST.'/hesabim', ['close_password' => 'yanlis'])->assertSessionHasErrors('close_password');
        $this->delete(self::HOST.'/hesabim', ['close_password' => self::PASSWORD])->assertSessionHasErrors('close_password'); // açık sipariş
        $this->assertTrue($customer->fresh()->is_active);

        $order->forceFill(['status' => 'delivered', 'delivered_at' => now()->subDays(40), 'payment_status' => 'collected'])->save();
        $this->delete(self::HOST.'/hesabim', ['close_password' => self::PASSWORD])->assertRedirect(route('store.home'));
        $this->assertGuest('customer');

        $closed = $customer->fresh();
        $this->assertSame(['Kapatılmış', 'hesap', "kapali-{$customer->id}@hesap.invalid", null, false], [$closed->first_name, $closed->last_name, $closed->email, $closed->phone, $closed->is_active]);
        $this->assertNotNull($closed->closed_at);
        $this->assertSame(0, $customer->addresses()->count());
        $this->assertSame(['ali@ornek.com', 'Ali Kaya'], [StoreOrder::find($order->id)->email, StoreOrder::find($order->id)->shipping_name], 'sipariş kopyası saklanır');
        $this->assertDatabaseHas('audit_logs', ['action' => 'store.customer_closed', 'entity_id' => $customer->id]);

        // Aynı e-postayla giriş yapılamaz, yeniden üye olunabilir.
        $this->post(self::HOST.'/giris', ['email' => 'ali@ornek.com', 'password' => self::PASSWORD])->assertSessionHasErrors('email');
    }

    public function test_launch_checklist_and_payment_logos(): void
    {
        Storage::fake('local');
        $this->actingAs(StoreAdmin::create(['name' => 'Y', 'email' => 'y@m.local', 'password' => self::PASSWORD]), 'store_admin');

        $this->get(route('store_admin.launch'))->assertOk()->assertSee('iyzico başvurusu')->assertSee('Eksik: vergi dairesi, vergi numarası, telefon, e-posta')
            ->assertSee('Ödeme logo bandı')->assertSee('Yayın aşamasında');

        $this->put(route('store_admin.settings.update'), ['company_title' => 'Yapboz Kırtasiye Ltd. Şti.', 'company_tax_office' => 'Eskişehir', 'company_tax_number' => '1234567890',
            'address' => 'Hamamyolu Cad. No 1', 'phone' => '0222 000 00 00', 'email' => 'info@ornek.com',
            'payment_logos' => UploadedFile::fake()->image('logolar.png', 800, 60)])->assertSessionHasNoErrors();
        $path = StoreSetting::get('payment_logos_path');
        $this->assertNotNull($path);
        Storage::disk('local')->assertExists($path);

        $this->get(route('store_admin.launch'))->assertDontSee('Eksik: ')->assertSee('Unvan, vergi bilgileri');
        $this->get(self::HOST.'/')->assertSee(route('store.media.payment-logos'));
        $this->get(self::HOST.'/gorsel/odeme-logolari')->assertOk();

        $this->put(route('store_admin.settings.update'), ['company_title' => 'Yapboz Kırtasiye Ltd. Şti.', 'remove_payment_logos' => '1'])->assertSessionHasNoErrors();
        $this->assertNull(StoreSetting::get('payment_logos_path'));
        Storage::disk('local')->assertMissing($path);
        $this->get(self::HOST.'/gorsel/odeme-logolari')->assertNotFound();
    }

    public function test_theme_cookie_row_is_added_to_unedited_cookie_policy_as_a_new_version(): void
    {
        $migration = require database_path('migrations/2026_11_08_000100_add_theme_cookie_to_store_cookie_policy.php');
        // Satır sonları kodla yazılır (dosyaya gömülmez): Windows checkout'ta (CRLF) da aynı davranır.
        $row = '| `tema` | Tercih | Seçtiğiniz görünümün (açık/koyu) hatırlanması; yalnızca tema seçtiğinizde yazılır | 1 yıl |'.chr(10);
        $policy = fn () => StoreLegalDocument::where('key', 'gizlilik-cerez')->orderByDesc('version');

        // Eski kurulum: ilk sürüm tema satırı olmadan; yönetimde değiştirilmemiş
        $policy()->first()->update(['body' => str_replace($row, '', str_replace(chr(13).chr(10), chr(10), $policy()->first()->body))]);
        $migration->up();
        $this->assertSame(2, $policy()->first()->version);
        $this->assertStringContainsString('`tema`', $policy()->first()->body);

        // Yönetimde düzenlenmiş metne dokunulmaz
        StoreLegalDocument::where('key', 'gizlilik-cerez')->where('version', 2)->delete();
        $policy()->first()->update(['body' => 'Kendi metnimiz']);
        $migration->up();
        $this->assertSame(1, $policy()->first()->version);
    }
}
