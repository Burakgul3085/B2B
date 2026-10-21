<?php

namespace Tests\Feature\Store;

use App\Domain\Auth\Area;
use App\Domain\Auth\AuthTokenService;
use App\Models\Store\District;
use App\Models\Store\Province;
use App\Models\Store\StoreCustomer;
use App\Models\Store\StoreCustomerAddress;
use App\Models\Store\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Mağaza müşteri hesapları (M5): üyelik, giriş, parola sıfırlama, hesap bilgileri ve adres defteri. */
class StoreCustomerTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'http://magaza.localhost';

    private const NEW_PASSWORD = 'MusteriSifre2026';

    protected function setUp(): void
    {
        parent::setUp();
        StoreSetting::put(['is_open' => '1']);
    }

    private function customer(array $attrs = []): StoreCustomer
    {
        return StoreCustomer::create($attrs + ['first_name' => 'Ayşe', 'last_name' => 'Yılmaz', 'email' => 'ayse@ornek.com', 'password' => self::PASSWORD]);
    }

    private function district(int $province, string $name): District
    {
        return District::where('province_id', $province)->where('name', $name)->firstOrFail();
    }

    public function test_geography_reference_data_is_loaded(): void
    {
        $this->assertSame(81, Province::count());
        $this->assertSame(973, District::count());
        $this->assertSame('Eskişehir', Province::findOrFail(Province::ESKISEHIR)->name);
        $this->assertSame(14, District::where('province_id', Province::ESKISEHIR)->count());

        $this->getJson(self::HOST.'/ilceler/26')->assertOk()->assertJsonCount(14)->assertJsonFragment(['name' => 'Odunpazarı']);
    }

    public function test_registration_creates_an_active_account_and_signs_in(): void
    {
        $form = ['first_name' => 'Ayşe', 'last_name' => 'Yılmaz', 'email' => 'Ayse@Ornek.com', 'phone' => '+90 532 123 45 67',
            'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD];

        $this->post(self::HOST.'/uye-ol', $form)->assertSessionHasErrors('kvkk');
        $this->post(self::HOST.'/uye-ol', $form + ['kvkk' => '1', 'marketing' => '1'])->assertRedirect(route('store.account'));

        $customer = StoreCustomer::firstOrFail();
        $this->assertSame(['ayse@ornek.com', '05321234567'], [$customer->email, $customer->phone]);
        $this->assertNotNull($customer->kvkk_accepted_at);
        $this->assertNotNull($customer->marketing_consent_at);
        $this->assertAuthenticatedAs($customer, 'customer');
        $this->assertDatabaseHas('audit_logs', ['action' => 'store.customer_registered', 'actor_type' => 'customer', 'actor_id' => $customer->id]);

        auth('customer')->logout();
        $this->post(self::HOST.'/uye-ol', $form + ['kvkk' => '1'])->assertSessionHasErrors('email');
    }

    public function test_login_logout_and_area_separation(): void
    {
        $customer = $this->customer();

        $this->get(self::HOST.'/hesabim')->assertRedirect(route('store.login'));
        $this->post(self::HOST.'/giris', ['email' => 'ayse@ornek.com', 'password' => 'yanlis-parola1'])->assertSessionHasErrors('email');
        $this->post(self::HOST.'/giris', ['email' => 'AYSE@ornek.com', 'password' => self::PASSWORD])->assertRedirect(route('store.account'));
        $this->get(self::HOST.'/hesabim')->assertOk()->assertSee('Merhaba, Ayşe');
        $this->get(self::HOST.'/giris')->assertRedirect(route('store.account'));

        // Müşteri oturumu mağaza yönetimini ve B2B'yi açmaz.
        $this->get(route('store_admin.dashboard'))->assertRedirect(route('store_admin.login'));
        $this->get('http://localhost/bayi/hesap-ozeti')->assertRedirect(route('dealer.login'));

        $this->post(self::HOST.'/cikis')->assertRedirect(route('store.home'));
        $this->assertGuest('customer');

        // Bayi hesabı mağazaya giremez (ayrı tablolar).
        $dealer = $this->makeDealer();
        $this->post(self::HOST.'/giris', ['email' => $dealer->email, 'password' => self::PASSWORD])->assertSessionHasErrors('email');

        $customer->forceFill(['is_active' => false])->save();
        $this->post(self::HOST.'/giris', ['email' => 'ayse@ornek.com', 'password' => self::PASSWORD])->assertSessionHasErrors('email');
    }

    public function test_password_reset_flow(): void
    {
        $customer = $this->customer();
        $this->post(self::HOST.'/parolami-unuttum', ['email' => 'ayse@ornek.com'])->assertSessionHas('status');
        $this->post(self::HOST.'/parolami-unuttum', ['email' => 'yok@ornek.com'])->assertSessionHas('status'); // aynı yanıt
        $this->assertDatabaseHas('auth_tokens', ['guard' => 'customer', 'user_id' => $customer->id, 'purpose' => 'password_reset']);

        $tokens = app(AuthTokenService::class);
        $plain = $tokens->issue(Area::Customer, $customer->id, 'password_reset');
        $this->assertSame('http://magaza.localhost/parola-sifirla/'.$plain, $tokens->url(Area::Customer, 'password_reset', $plain));

        $this->get(self::HOST.'/parola-sifirla/gecersiz')->assertRedirect(route('store.password.request'));
        $this->get(self::HOST.'/parola-sifirla/'.$plain)->assertOk();
        $this->post(self::HOST.'/parola-sifirla', ['token' => $plain, 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD])
            ->assertRedirect(route('store.login'));
        $this->post(self::HOST.'/parola-sifirla', ['token' => $plain, 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD])
            ->assertRedirect(route('store.password.request')); // tek kullanımlık

        $this->post(self::HOST.'/giris', ['email' => 'ayse@ornek.com', 'password' => self::NEW_PASSWORD])->assertRedirect(route('store.account'));
    }

    public function test_profile_email_and_password_changes(): void
    {
        $customer = $this->customer();
        $this->customer(['email' => 'baska@ornek.com']);
        $this->actingAs($customer, 'customer');

        $this->put(self::HOST.'/hesabim', ['first_name' => 'Ayşe Nur', 'last_name' => 'Yılmaz', 'phone' => '0532 1'])->assertSessionHasErrors('phone');
        $this->put(self::HOST.'/hesabim', ['first_name' => 'Ayşe Nur', 'last_name' => 'Yılmaz', 'phone' => '0532 765 43 21'])->assertSessionHasNoErrors();
        $this->assertSame(['Ayşe Nur', '05327654321'], [$customer->fresh()->first_name, $customer->fresh()->phone]);

        $this->put(self::HOST.'/hesabim/e-posta', ['email' => 'baska@ornek.com', 'email_password' => self::PASSWORD])->assertSessionHasErrors('email');
        $this->put(self::HOST.'/hesabim/e-posta', ['email' => 'yeni@ornek.com', 'email_password' => 'yanlis'])->assertSessionHasErrors('email_password');
        $this->put(self::HOST.'/hesabim/e-posta', ['email' => 'Yeni@Ornek.com', 'email_password' => self::PASSWORD])->assertSessionHasNoErrors();
        $this->assertSame('yeni@ornek.com', $customer->fresh()->email);

        $this->put(self::HOST.'/hesabim/parola', ['current_password' => 'yanlis', 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD])
            ->assertSessionHasErrors('current_password');
        $this->put(self::HOST.'/hesabim/parola', ['current_password' => self::PASSWORD, 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD])
            ->assertSessionHasNoErrors();
        $this->assertTrue(password_verify(self::NEW_PASSWORD, $customer->fresh()->password));
    }

    public function test_address_book(): void
    {
        $customer = $this->customer();
        $other = $this->customer(['email' => 'baska@ornek.com']);
        $foreign = new StoreCustomerAddress(['title' => 'Ev', 'first_name' => 'X', 'last_name' => 'Y', 'phone' => '05320000000', 'province_id' => 6,
            'district_id' => $this->district(6, 'Çankaya')->id, 'address_line' => 'Atatürk Bulvarı No 1']);
        $foreign->customer_id = $other->id;
        $foreign->save();
        $this->actingAs($customer, 'customer');

        $form = ['title' => 'Ev', 'first_name' => 'Ayşe', 'last_name' => 'Yılmaz', 'phone' => '0532 123 45 67', 'province_id' => 26,
            'district_id' => $this->district(26, 'Odunpazarı')->id, 'neighborhood' => 'Arifiye', 'address_line' => 'Hamamyolu Cad. No 12 D 3'];

        // İlçe ile uyuşmayan il reddedilir.
        $this->post(self::HOST.'/hesabim/adres', ['province_id' => 6] + $form)->assertSessionHasErrors('district_id');
        $this->post(self::HOST.'/hesabim/adres', $form)->assertSessionHasNoErrors();
        $this->post(self::HOST.'/hesabim/adres', ['title' => 'İş', 'district_id' => $this->district(26, 'Tepebaşı')->id] + $form)->assertSessionHasNoErrors();

        [$home, $work] = $customer->addresses()->with(['province', 'district'])->get()->all();
        $this->assertTrue($home->is_default, 'ilk adres varsayılan olur');
        $this->assertSame('Arifiye, Hamamyolu Cad. No 12 D 3 — Odunpazarı / Eskişehir', $home->oneLine());

        $this->put(self::HOST."/hesabim/adres/{$work->id}/varsayilan")->assertSessionHasNoErrors();
        $this->assertSame([false, true], [$home->fresh()->is_default, $work->fresh()->is_default]);
        $this->delete(self::HOST."/hesabim/adres/{$work->id}");
        $this->assertTrue($home->fresh()->is_default, 'varsayılan silinince kalan adres varsayılan olur');

        // Başkasının adresi görülemez, değiştirilemez, silinemez.
        $this->get(self::HOST."/hesabim/adres/{$foreign->id}")->assertNotFound();
        $this->put(self::HOST."/hesabim/adres/{$foreign->id}", $form)->assertNotFound();
        $this->delete(self::HOST."/hesabim/adres/{$foreign->id}")->assertNotFound();
        $this->assertTrue(StoreCustomerAddress::whereKey($foreign->id)->exists());

        $this->get(self::HOST.'/hesabim')->assertOk()->assertSee('Odunpazarı / Eskişehir')->assertDontSee('Atatürk Bulvarı');
        $this->get(self::HOST.'/hesabim/adres/yeni')->assertOk();
        $this->get(self::HOST."/hesabim/adres/{$home->id}")->assertOk();
    }
}
