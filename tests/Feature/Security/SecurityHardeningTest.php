<?php

namespace Tests\Feature\Security;

use App\Models\DealerAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrders;
use Tests\TestCase;

/** Aşama 9 — oturum, başlık, kaba kuvvet, yönlendirme, toplu atama ve XSS kontrolleri. */
class SecurityHardeningTest extends TestCase
{
    use CreatesOrders, RefreshDatabase;

    public function test_security_headers_and_content_security_policy(): void
    {
        $response = $this->get(route('business.login'))->assertOk();

        $response->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringNotContainsString('*', $csp);
        $response->assertHeaderMissing('Strict-Transport-Security');

        $this->get('https://localhost/toptanci/login')->assertHeader('Strict-Transport-Security');
    }

    public function test_login_locks_after_repeated_failures_even_for_correct_password(): void
    {
        $business = $this->makeBusiness();
        $email = $business->user->email;

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('business.login.store'), ['email' => $email, 'password' => 'yanlis-parola'])->assertSessionHasErrors('email');
        }
        $this->post(route('business.login.store'), ['email' => $email, 'password' => self::PASSWORD])
            ->assertSessionHasErrors(['email' => 'Çok fazla başarısız deneme. Lütfen 15 dakika sonra tekrar deneyin.']);
        $this->assertGuest('business');

        // Kilit e-postaya özeldir; başka hesap aynı IP'den girebilir.
        $other = $this->makeBusiness();
        $this->post(route('business.login.store'), ['email' => $other->user->email, 'password' => self::PASSWORD])->assertRedirect(route('business.dashboard'));
    }

    public function test_session_is_regenerated_on_login_and_invalidated_on_logout(): void
    {
        $business = $this->makeBusiness();
        $this->get(route('business.login'));
        $before = session()->getId();

        $this->post(route('business.login.store'), ['email' => $business->user->email, 'password' => self::PASSWORD])->assertRedirect();
        $this->assertNotSame($before, session()->getId());
        $this->assertAuthenticatedAs($business->user, 'business');

        $this->post(route('business.logout'))->assertRedirect();
        $this->assertGuest('business');
        $this->get(route('business.dashboard'))->assertRedirect(route('business.login'));
    }

    public function test_login_never_redirects_outside_its_area(): void
    {
        $business = $this->makeBusiness();

        foreach (['https://kotu.example/toptanci/x', url('/bayi/siparislerim'), '//kotu.example/toptanci'] as $intended) {
            $this->flushSession();
            $this->app['auth']->forgetGuards();
            $this->withSession(['url.intended' => $intended])
                ->post(route('business.login.store'), ['email' => $business->user->email, 'password' => self::PASSWORD])
                ->assertRedirect(route('business.dashboard'));
        }
    }

    public function test_password_reset_does_not_reveal_whether_account_exists(): void
    {
        $business = $this->makeBusiness();

        $known = $this->from(route('business.password.request'))->post(route('business.password.email'), ['email' => $business->user->email]);
        $unknown = $this->from(route('business.password.request'))->post(route('business.password.email'), ['email' => 'yok@yok.test']);

        $this->assertSame($known->getSession()->get('status'), $unknown->getSession()->get('status'));
        $this->assertNotNull($known->getSession()->get('status'));
    }

    public function test_foreign_keys_and_tenant_columns_in_form_input_are_ignored(): void
    {
        $business = $this->makeBusiness();
        $other = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $sibling = $this->orderingDealer($business);
        $this->inTenant($business, fn () => $rel->forceFill(['can_edit_addresses' => true])->save());

        $this->actingAs($rel->dealer->user, 'dealer')->post(route('dealer.addresses.store'), [
            'title' => 'Depo', 'recipient_name' => 'Alan', 'phone' => '0212 111 11 11', 'city' => 'İstanbul', 'district' => 'Şişli',
            'address_line' => 'Cadde 5', 'business_id' => $other->id, 'dealer_relationship_id' => $sibling->id, 'id' => 999999,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $address = DealerAddress::withoutGlobalScopes()->where('title', 'Depo')->sole();
        $this->assertSame($business->id, $address->business_id);
        $this->assertSame($rel->id, $address->dealer_relationship_id);
        $this->assertNotSame(999999, $address->id);
    }

    public function test_end_date_before_start_date_is_still_rejected(): void
    {
        $business = $this->makeBusiness();
        $this->actingAs($business->user, 'business');

        $this->get(route('business.orders.index', ['from' => '2026-05-10', 'to' => '2026-05-01']))->assertSessionHasErrors('to');
        $this->get(route('business.orders.index', ['from' => '2026-05-01', 'to' => '2026-05-10']))->assertOk()->assertSessionHasNoErrors();
        $this->get(route('business.orders.index', ['from' => ['x'], 'to' => '2026-05-10']))->assertSessionHasErrors('from');
    }

    public function test_user_content_is_escaped_in_every_area(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $payload = '<script>alert("x")</script><img src=x onerror=alert(1)>';
        $product = $this->makeProduct($business, ['name' => $payload, 'description' => $payload]);
        $this->stockUp($business, $product, '5');
        $order = $this->placeOrder($business, $rel, [$product->id => '1']);

        $this->actingAs($business->user, 'business');
        foreach ([route('business.products.index'), route('business.products.show', $product), route('business.orders.show', $order)] as $url) {
            $this->get($url)->assertOk()->assertDontSee($payload, false)->assertSee('&lt;script&gt;', false);
        }
        $this->actingAs($rel->dealer->user, 'dealer');
        foreach ([route('dealer.catalog'), route('dealer.catalog.show', $product), route('dealer.orders.show', $order)] as $url) {
            $this->get($url)->assertOk()->assertDontSee($payload, false);
        }
    }
}
