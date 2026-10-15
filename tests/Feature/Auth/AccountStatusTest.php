<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Askıya alınmış işletme / bayi ve etkinleştirilmemiş hesaplar. */
class AccountStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_of_suspended_business_cannot_log_in(): void
    {
        $business = $this->makeBusiness(status: 'suspended');

        $this->post('/toptanci/login', ['email' => $business->user->email, 'password' => self::PASSWORD])
            ->assertSessionHasErrors('email');
        $this->assertGuest('business');
    }

    public function test_suspending_business_ends_existing_session_on_next_request(): void
    {
        $business = $this->makeBusiness();
        $this->post('/toptanci/login', ['email' => $business->user->email, 'password' => self::PASSWORD]);
        $this->get('/toptanci')->assertOk();

        $business->update(['status' => 'suspended']);
        // Gerçek istekte kullanıcı her seferinde veritabanından yüklenir; testte guard önbelleğini boşalt.
        $this->app['auth']->forgetGuards();

        $this->get('/toptanci')->assertRedirect('/toptanci/login');
        $this->assertGuest('business');
    }

    public function test_pending_activation_account_cannot_log_in(): void
    {
        $business = $this->makeBusiness(userStatus: 'pending_activation');

        $this->post('/toptanci/login', ['email' => $business->user->email, 'password' => self::PASSWORD])
            ->assertSessionHasErrors('email');
    }

    public function test_dealer_loses_access_when_relationship_is_suspended(): void
    {
        $business = $this->makeBusiness();
        $dealer = $this->makeDealer();
        $relationship = $this->relate($business, $dealer);

        $this->actingAs($dealer, 'dealer');
        $this->get('/bayi')->assertOk();

        $relationship->forceFill(['status' => 'suspended'])->saveQuietly();

        $this->get('/bayi')->assertRedirect(route('dealer.select-business'));
        $this->get(route('dealer.select-business'))->assertOk()->assertSee('Aktif toptancı bağlantınız yok');
    }

    public function test_dealer_loses_access_when_wholesaler_is_suspended(): void
    {
        $business = $this->makeBusiness();
        $dealer = $this->makeDealer();
        $this->relate($business, $dealer);
        $this->actingAs($dealer, 'dealer');

        $business->update(['status' => 'suspended']);

        $this->get('/bayi')->assertRedirect(route('dealer.select-business'));
    }

    public function test_disabled_dealer_user_is_logged_out(): void
    {
        $business = $this->makeBusiness();
        $dealer = $this->makeDealer();
        $this->relate($business, $dealer);
        $this->actingAs($dealer, 'dealer');

        $dealer->update(['status' => 'disabled']);

        $this->get('/bayi')->assertRedirect('/bayi/login');
        $this->assertGuest('dealer');
    }
}
