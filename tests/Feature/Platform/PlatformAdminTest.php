<?php

namespace Tests\Feature\Platform;

use App\Http\Middleware\RequirePlatformTwoFactor;
use App\Models\Business;
use App\Models\BusinessUser;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class PlatformAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_login_requires_two_factor_setup_before_panel_access(): void
    {
        $admin = $this->makePlatformAdmin();

        $this->post('/platform/login', ['email' => $admin->email, 'password' => self::PASSWORD])
            ->assertRedirect(route('platform.two-factor.setup'));

        $this->get('/platform')->assertRedirect(route('platform.two-factor.setup'));
        $this->get('/platform/isletmeler')->assertRedirect(route('platform.two-factor.setup'));
    }

    public function test_two_factor_setup_and_challenge(): void
    {
        $admin = $this->makePlatformAdmin();
        $this->post('/platform/login', ['email' => $admin->email, 'password' => self::PASSWORD]);
        $this->get(route('platform.two-factor.setup'))->assertOk();

        $secret = session('platform.two_factor_pending_secret');
        $engine = new Google2FA;

        $this->post(route('platform.two-factor.confirm'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post(route('platform.two-factor.confirm'), ['code' => $engine->getCurrentOtp($secret)])
            ->assertRedirect(route('platform.dashboard'));

        $this->assertNotNull($admin->fresh()->two_factor_confirmed_at);
        $this->get('/platform')->assertOk();

        // Sonraki girişte kod sorulur.
        $this->post('/platform/cikis');
        $this->post('/platform/login', ['email' => $admin->email, 'password' => self::PASSWORD])
            ->assertRedirect(route('platform.two-factor.challenge'));
        $this->get('/platform')->assertRedirect(route('platform.two-factor.challenge'));
    }

    public function test_platform_admin_creates_business_with_active_owner_and_password(): void
    {
        $admin = $this->actingAsVerifiedAdmin();
        $form = [
            'legal_name' => 'Akgün Kırtasiye Ltd. Şti.', 'display_name' => 'Akgün Kırtasiye', 'slug' => 'Akgün Kırtasiye',
            'owner_name' => 'Ahmet Akgün', 'owner_email' => 'Ahmet@Akgun.test', 'plan' => 'Standart', 'starts_on' => now()->toDateString(),
        ];

        $this->post(route('platform.businesses.store'), $form)->assertSessionHasErrors('owner_password');
        $this->post(route('platform.businesses.store'), $form + ['owner_password' => 'ToptanSifre2026'])->assertSessionHasNoErrors()->assertRedirect();

        $business = Business::where('slug', 'akgun-kirtasiye')->firstOrFail();
        $user = BusinessUser::where('business_id', $business->id)->firstOrFail();

        $this->assertSame('ahmet@akgun.test', $user->email);
        $this->assertSame('active', $user->status);
        $this->assertNotNull($business->settings);
        $this->assertCount(1, $business->licenses);
        $this->assertDatabaseMissing('auth_tokens', ['guard' => 'business', 'user_id' => $user->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'platform.business_created', 'actor_type' => 'platform', 'actor_id' => $admin->id]);
        $this->get(route('platform.businesses.show', $business))->assertOk()->assertSee('Ana e-posta (Gmail)');

        // Yetkili hemen giriş yapar.
        $this->post('/toptanci/login', ['email' => 'ahmet@akgun.test', 'password' => 'ToptanSifre2026'])->assertRedirect('/toptanci');
    }

    public function test_second_user_for_same_business_is_impossible(): void
    {
        $business = $this->makeBusiness();

        $this->expectException(QueryException::class);

        BusinessUser::create(['business_id' => $business->id, 'name' => 'İkinci', 'email' => 'ikinci@test.local', 'status' => 'active']);
    }

    public function test_suspending_business_requires_reason_and_is_audited(): void
    {
        $this->actingAsVerifiedAdmin();
        $business = $this->makeBusiness();

        $this->put(route('platform.businesses.status', $business), ['status' => 'suspended'])->assertSessionHasErrors('status_reason');
        $this->put(route('platform.businesses.status', $business), ['status' => 'suspended', 'status_reason' => 'Lisans ödemesi'])->assertRedirect();

        $this->assertSame('suspended', $business->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'platform.business_status_changed', 'business_id' => $business->id]);
    }

    public function test_business_user_cannot_use_platform_routes(): void
    {
        $business = $this->makeBusiness();

        $this->actingAs($business->user, 'business')
            ->post(route('platform.businesses.store'), ['legal_name' => 'X'])
            ->assertRedirect('/platform/login');
    }

    private function actingAsVerifiedAdmin()
    {
        $admin = $this->makePlatformAdmin();
        $admin->forceFill(['two_factor_secret' => (new Google2FA)->generateSecretKey(32), 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($admin, 'platform')->withSession([RequirePlatformTwoFactor::SESSION_KEY => true]);

        return $admin;
    }
}
