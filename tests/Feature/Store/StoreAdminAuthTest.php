<?php

namespace Tests\Feature\Store;

use App\Http\Middleware\RequireStoreTwoFactor;
use App\Models\Store\StoreAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Mağaza yönetimi girişi (M1): kendi alan adı, kendi oturum çerezi, süper admin için zorunlu 2FA,
 * pasif hesap engeli ve B2B ile tam ayrım.
 */
class StoreAdminAuthTest extends TestCase
{
    use RefreshDatabase;

    private function admin(bool $super = false, array $attributes = []): StoreAdmin
    {
        return StoreAdmin::create([
            'name' => 'Mağaza '.Str::random(4), 'email' => Str::lower(Str::random(8)).'@magaza.local',
            'password' => self::PASSWORD, 'is_super' => $super, 'is_active' => true,
        ] + $attributes);
    }

    public function test_admin_panel_lives_on_its_own_domain(): void
    {
        $this->assertSame('yonetim.localhost', parse_url(route('store_admin.login'), PHP_URL_HOST));
        $this->get(route('store_admin.login'))->assertOk()->assertSee('Mağaza yönetimine giriş yapın');

        // Oturumsuz kullanıcı yönetimin kendi giriş sayfasına gider.
        $this->get(route('store_admin.dashboard'))->assertRedirect(route('store_admin.login'));
    }

    public function test_b2b_addresses_do_not_open_on_store_domains(): void
    {
        foreach (['http://yonetim.localhost', 'http://magaza.localhost'] as $host) {
            foreach (['/bayi/login', '/toptanci/login', '/platform/login', '/basvuru'] as $path) {
                $this->get($host.$path)->assertNotFound();
            }
        }

        // B2B'nin kökü bayi girişine gider; mağaza kökü mağazadır.
        $this->get('http://localhost/')->assertRedirect('/bayi/login');
        $this->get('http://magaza.localhost/')->assertStatus(503)->assertSee('Online mağazamız çok yakında açılıyor');
    }

    public function test_admin_without_two_factor_logs_in_directly(): void
    {
        $admin = $this->admin();

        $this->post(route('store_admin.login.store'), ['email' => strtoupper($admin->email), 'password' => self::PASSWORD])
            ->assertRedirect(route('store_admin.dashboard'));
        $this->get(route('store_admin.dashboard'))->assertOk()->assertSee('Hoş geldiniz, '.$admin->name);
        $this->assertNotNull($admin->fresh()->last_login_at);
    }

    public function test_wrong_password_and_b2b_accounts_cannot_log_in(): void
    {
        $admin = $this->admin();
        $this->post(route('store_admin.login.store'), ['email' => $admin->email, 'password' => 'yanlis-parola-1'])->assertSessionHasErrors('email');

        // Bayi veya toptancı hesabı mağaza yönetimine giremez (ayrı tablolar).
        $dealer = $this->makeDealer();
        $this->post(route('store_admin.login.store'), ['email' => $dealer->email, 'password' => self::PASSWORD])->assertSessionHasErrors('email');
        $this->assertGuest('store_admin');
    }

    public function test_super_admin_must_set_up_two_factor(): void
    {
        $admin = $this->admin(super: true);

        $this->post(route('store_admin.login.store'), ['email' => $admin->email, 'password' => self::PASSWORD])
            ->assertRedirect(route('store_admin.two-factor.setup'));
        $this->get(route('store_admin.dashboard'))->assertRedirect(route('store_admin.two-factor.setup'));

        $this->get(route('store_admin.two-factor.setup'))->assertOk();
        $secret = session('store_admin.two_factor_pending_secret');
        $this->post(route('store_admin.two-factor.confirm'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post(route('store_admin.two-factor.confirm'), ['code' => (new Google2FA)->getCurrentOtp($secret)])
            ->assertRedirect(route('store_admin.dashboard'));
        $this->get(route('store_admin.dashboard'))->assertOk();

        // Sonraki girişte kod sorulur.
        $this->post(route('store_admin.logout'));
        $this->post(route('store_admin.login.store'), ['email' => $admin->email, 'password' => self::PASSWORD])
            ->assertRedirect(route('store_admin.two-factor.challenge'));
        $this->get(route('store_admin.dashboard'))->assertRedirect(route('store_admin.two-factor.challenge'));
    }

    public function test_admin_can_enable_and_disable_own_two_factor(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'store_admin');

        $this->get(route('store_admin.two-factor.setup'))->assertOk();
        $secret = session('store_admin.two_factor_pending_secret');
        $this->post(route('store_admin.two-factor.confirm'), ['code' => (new Google2FA)->getCurrentOtp($secret)]);
        $this->assertTrue($admin->fresh()->hasTwoFactorEnabled());

        $this->delete(route('store_admin.two-factor.disable'), ['two_factor_password' => 'yanlis'])->assertSessionHasErrors('two_factor_password');
        $this->delete(route('store_admin.two-factor.disable'), ['two_factor_password' => self::PASSWORD])->assertSessionHasNoErrors();
        $this->assertFalse($admin->fresh()->hasTwoFactorEnabled());
    }

    public function test_super_admin_cannot_disable_two_factor(): void
    {
        $super = $this->admin(super: true);
        $super->forceFill(['two_factor_secret' => (new Google2FA)->generateSecretKey(32), 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($super, 'store_admin')->withSession([RequireStoreTwoFactor::SESSION_KEY => true])
            ->delete(route('store_admin.two-factor.disable'), ['two_factor_password' => self::PASSWORD])->assertForbidden();
        $this->assertTrue($super->fresh()->hasTwoFactorEnabled());
    }

    public function test_deactivated_admin_is_signed_out_and_cannot_log_in(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'store_admin')->get(route('store_admin.dashboard'))->assertOk();

        $admin->forceFill(['is_active' => false])->save();
        $this->get(route('store_admin.dashboard'))->assertRedirect(route('store_admin.login'));
        $this->assertGuest('store_admin');

        $this->post(route('store_admin.login.store'), ['email' => $admin->email, 'password' => self::PASSWORD])->assertSessionHasErrors('email');
    }

    public function test_store_admin_session_does_not_open_b2b_areas(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'store_admin');

        $this->get('/toptanci')->assertRedirect(route('business.login'));
        $this->get('/platform')->assertRedirect(route('platform.login'));
        $this->get('/bayi/hesap-ozeti')->assertRedirect(route('dealer.login'));
    }

    public function test_store_admin_uses_its_own_session_cookie(): void
    {
        $this->get(route('store_admin.login'))->assertCookie('magaza_yonetim');
        $this->get('http://magaza.localhost/')->assertCookie('magaza');
    }

    public function test_profile_and_password_change(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'store_admin');

        $this->get(route('store_admin.account'))->assertOk()->assertSee('İki adımlı doğrulama');
        $this->put(route('store_admin.account.password'), [
            'current_password' => self::PASSWORD, 'password' => 'YeniMagazaSifre2026', 'password_confirmation' => 'YeniMagazaSifre2026',
        ])->assertSessionHasNoErrors();

        $this->post(route('store_admin.logout'));
        $this->post(route('store_admin.login.store'), ['email' => $admin->email, 'password' => 'YeniMagazaSifre2026'])
            ->assertRedirect(route('store_admin.dashboard'));
    }

    public function test_forgot_password_page_works_without_revealing_accounts(): void
    {
        $this->get(route('store_admin.password.request'))->assertOk();
        $this->post(route('store_admin.password.email'), ['email' => 'yok@magaza.local'])
            ->assertSessionHas('status');
    }
}
