<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Kabul koşulu 1–2: üç ayrı giriş alanı ve doğru panele yönlendirme. */
class AreaSeparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_area_has_its_own_login_page(): void
    {
        $this->get('/platform/login')->assertOk()->assertSee('Platform Yönetimi');
        $this->get('/toptanci/login')->assertOk()->assertSee('Toptancı Paneli');
        $this->get('/bayi/login')->assertOk()->assertSee('Bayi Paneli');
    }

    public function test_each_area_uses_its_own_session_cookie_scoped_to_its_path(): void
    {
        foreach (['platform' => 'b2b_platform', 'toptanci' => 'b2b_business', 'bayi' => 'b2b_dealer'] as $prefix => $cookie) {
            $response = $this->get("/{$prefix}/login");
            $set = collect($response->headers->getCookies())->firstWhere(fn ($c) => $c->getName() === $cookie);

            $this->assertNotNull($set, "{$prefix} alanı {$cookie} çerezini kullanmalı");
            $this->assertSame("/{$prefix}", $set->getPath());
            $this->assertTrue($set->isHttpOnly());
        }
    }

    public function test_guests_are_sent_to_the_login_of_the_area_they_requested(): void
    {
        $this->get('/toptanci')->assertRedirect('/toptanci/login');
        $this->get('/bayi')->assertRedirect('/bayi/login');
        $this->get('/platform')->assertRedirect('/platform/login');
    }

    public function test_business_user_logs_in_to_business_panel(): void
    {
        $business = $this->makeBusiness();

        $this->post('/toptanci/login', ['email' => $business->user->email, 'password' => self::PASSWORD])
            ->assertRedirect(route('business.dashboard'));

        $this->assertAuthenticatedAs($business->user, 'business');
        $this->get('/toptanci')->assertOk()->assertSee($business->display_name);
    }

    public function test_business_credentials_do_not_work_on_dealer_or_platform_login(): void
    {
        $business = $this->makeBusiness();
        $credentials = ['email' => $business->user->email, 'password' => self::PASSWORD];

        $this->post('/bayi/login', $credentials)->assertSessionHasErrors('email');
        $this->post('/platform/login', $credentials)->assertSessionHasErrors('email');
        $this->assertGuest('dealer');
        $this->assertGuest('platform');
    }

    public function test_dealer_credentials_do_not_work_on_business_login(): void
    {
        $dealer = $this->makeDealer();

        $this->post('/toptanci/login', ['email' => $dealer->email, 'password' => self::PASSWORD])->assertSessionHasErrors('email');
        $this->assertGuest('business');
    }

    public function test_authenticated_business_user_cannot_reach_other_panels_by_url(): void
    {
        $business = $this->makeBusiness();
        $this->actingAs($business->user, 'business');

        $this->get('/bayi')->assertRedirect('/bayi/login');
        $this->get('/platform')->assertRedirect('/platform/login');
        $this->get('/platform/isletmeler')->assertRedirect('/platform/login');
    }

    public function test_authenticated_dealer_cannot_reach_business_or_platform_panel(): void
    {
        $business = $this->makeBusiness();
        $dealer = $this->makeDealer();
        $this->relate($business, $dealer);
        $this->actingAs($dealer, 'dealer');

        $this->get('/bayi')->assertOk();
        $this->get('/toptanci')->assertRedirect('/toptanci/login');
        $this->get('/toptanci/ayarlar')->assertRedirect('/toptanci/login');
        $this->get('/platform')->assertRedirect('/platform/login');
    }

    public function test_login_ignores_intended_url_outside_the_area(): void
    {
        $business = $this->makeBusiness();

        $this->withSession(['url.intended' => 'https://kotu-site.example/phish'])
            ->post('/toptanci/login', ['email' => $business->user->email, 'password' => self::PASSWORD])
            ->assertRedirect(route('business.dashboard'));

        $this->post('/toptanci/cikis');

        $this->withSession(['url.intended' => url('/bayi')])
            ->post('/toptanci/login', ['email' => $business->user->email, 'password' => self::PASSWORD])
            ->assertRedirect(route('business.dashboard'));
    }

    public function test_wrong_password_returns_generic_message(): void
    {
        $business = $this->makeBusiness();

        $this->post('/toptanci/login', ['email' => $business->user->email, 'password' => 'yanlis-parola'])
            ->assertSessionHasErrors(['email' => 'E-posta adresi veya parola hatalı.']);
        $this->post('/toptanci/login', ['email' => 'olmayan@test.local', 'password' => 'yanlis-parola'])
            ->assertSessionHasErrors(['email' => 'E-posta adresi veya parola hatalı.']);
    }

    public function test_login_is_rate_limited_after_five_failures(): void
    {
        $business = $this->makeBusiness();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/toptanci/login', ['email' => $business->user->email, 'password' => 'yanlis-parola']);
        }

        // Doğru parola bile kilit süresince kabul edilmez.
        $this->post('/toptanci/login', ['email' => $business->user->email, 'password' => self::PASSWORD])
            ->assertSessionHasErrors('email');
        $this->assertGuest('business');
    }

    public function test_failed_and_successful_logins_are_audited(): void
    {
        $business = $this->makeBusiness();

        $this->post('/toptanci/login', ['email' => $business->user->email, 'password' => 'yanlis-parola']);
        $this->post('/toptanci/login', ['email' => $business->user->email, 'password' => self::PASSWORD]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login_failed', 'actor_type' => 'business']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login', 'actor_type' => 'business', 'actor_id' => $business->user->id, 'business_id' => $business->id]);
    }
}
