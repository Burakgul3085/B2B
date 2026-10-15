<?php

namespace Tests\Feature\Auth;

use App\Domain\Auth\Area;
use App\Domain\Auth\AuthTokenService;
use App\Models\AuthToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Parolalar e-postayla gönderilmez; kullanıcı tek kullanımlık bağlantıyla kendisi belirler. */
class ActivationAndResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_user_activates_account_with_single_use_link(): void
    {
        $business = $this->makeBusiness(userStatus: 'pending_activation');
        $token = app(AuthTokenService::class)->issue(Area::Business, $business->user->id, 'activation');

        $this->get("/toptanci/aktivasyon/{$token}")->assertOk();
        $this->post('/toptanci/aktivasyon', ['token' => $token, 'password' => 'YeniParola2026', 'password_confirmation' => 'YeniParola2026'])
            ->assertRedirect(route('business.login'));

        $user = $business->user->fresh();
        $this->assertSame('active', $user->status);
        $this->assertTrue(Hash::check('YeniParola2026', $user->password));

        // İkinci kullanım reddedilir.
        $this->get("/toptanci/aktivasyon/{$token}")->assertRedirect(route('business.login'));
    }

    public function test_only_token_hash_is_stored(): void
    {
        $business = $this->makeBusiness(userStatus: 'pending_activation');
        $token = app(AuthTokenService::class)->issue(Area::Business, $business->user->id, 'activation');

        $this->assertDatabaseMissing('auth_tokens', ['token_hash' => $token]);
        $this->assertDatabaseHas('auth_tokens', ['token_hash' => hash('sha256', $token)]);
    }

    public function test_activation_token_of_one_area_does_not_work_in_another(): void
    {
        $business = $this->makeBusiness(userStatus: 'pending_activation');
        $token = app(AuthTokenService::class)->issue(Area::Business, $business->user->id, 'activation');

        $this->get("/bayi/aktivasyon/{$token}")->assertRedirect(route('dealer.login'));
    }

    public function test_expired_activation_link_is_rejected(): void
    {
        $business = $this->makeBusiness(userStatus: 'pending_activation');
        $token = app(AuthTokenService::class)->issue(Area::Business, $business->user->id, 'activation');
        AuthToken::query()->update(['expires_at' => now()->subMinute()]);

        $this->post('/toptanci/aktivasyon', ['token' => $token, 'password' => 'YeniParola2026', 'password_confirmation' => 'YeniParola2026'])
            ->assertRedirect(route('business.login'));
        $this->assertSame('pending_activation', $business->user->fresh()->status);
    }

    public function test_weak_password_is_rejected(): void
    {
        $business = $this->makeBusiness(userStatus: 'pending_activation');
        $token = app(AuthTokenService::class)->issue(Area::Business, $business->user->id, 'activation');

        $this->post('/toptanci/aktivasyon', ['token' => $token, 'password' => 'kisa', 'password_confirmation' => 'kisa'])
            ->assertSessionHasErrors('password');
    }

    public function test_forgot_password_response_does_not_reveal_account_existence(): void
    {
        $dealer = $this->makeDealer();

        $this->post('/bayi/parolami-unuttum', ['email' => $dealer->email])->assertSessionHasNoErrors()->assertRedirect();
        $existingMessage = session('status');

        $this->post('/bayi/parolami-unuttum', ['email' => 'yok@bayi.local'])->assertSessionHasNoErrors()->assertRedirect();
        $missingMessage = session('status');

        $this->assertNotNull($existingMessage);
        $this->assertSame($existingMessage, $missingMessage);
        $this->assertSame(1, AuthToken::where('guard', 'dealer')->count());
    }

    public function test_dealer_resets_password(): void
    {
        $dealer = $this->makeDealer();
        $token = app(AuthTokenService::class)->issue(Area::Dealer, $dealer->id, 'password_reset');

        $this->post('/bayi/parola-sifirla', ['token' => $token, 'password' => 'BaskaParola2026', 'password_confirmation' => 'BaskaParola2026'])
            ->assertRedirect(route('dealer.login'));

        $this->post('/bayi/login', ['email' => $dealer->email, 'password' => 'BaskaParola2026'])->assertRedirect();
        $this->assertAuthenticatedAs($dealer->fresh(), 'dealer');
    }

    public function test_password_change_requires_current_password(): void
    {
        $business = $this->makeBusiness();
        $this->actingAs($business->user, 'business');

        $this->put('/toptanci/hesabim/parola', ['current_password' => 'yanlis', 'password' => 'YeniParola2026', 'password_confirmation' => 'YeniParola2026'])
            ->assertSessionHasErrors('current_password');

        $this->put('/toptanci/hesabim/parola', ['current_password' => self::PASSWORD, 'password' => 'YeniParola2026', 'password_confirmation' => 'YeniParola2026'])
            ->assertSessionHasNoErrors()->assertRedirect();

        $this->assertTrue(Hash::check('YeniParola2026', $business->user->fresh()->password));
    }
}
