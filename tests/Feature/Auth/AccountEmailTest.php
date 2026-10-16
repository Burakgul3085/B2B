<?php

namespace Tests\Feature\Auth;

use App\Domain\Mail\SystemMailer;
use App\Models\AuthToken;
use App\Models\BusinessUser;
use App\Models\DealerRelationship;
use App\Models\DealerUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesOrders;
use Tests\TestCase;

/**
 * Giriş e-postası ve hesap bilgileri yetkileri:
 *  - Kişi kendi e-postasını doğrulama bağlantısıyla değiştirir.
 *  - Toptancı yalnızca aktivasyonu tamamlanmamış (ve başka toptancıyla ilişkisiz) bayinin e-postasını düzeltir;
 *    aktif bayiye yalnızca parola sıfırlama bağlantısı gönderir ve bu bağlantıyı görmez.
 *  - Platform yöneticisi gerekçeyle doğrudan değiştirir, sıfırlama bağlantısı üretir, hesabı kapatır.
 */
class AccountEmailTest extends TestCase
{
    use CreatesOrders, RefreshDatabase;

    private function pendingDealer($business): array
    {
        $this->actingAs($business->user, 'business')->post(route('business.dealers.store'), [
            'legal_name' => 'Hatalı Mail Kırtasiye', 'contact_name' => 'Ayşe Demir', 'email' => 'uydurma@yanlis.test',
            'phone' => '0212 111 22 33', 'city' => 'İstanbul', 'district' => 'Şişli', 'address' => 'Cadde 1', 'payment_term_days' => 30,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $user = DealerUser::where('email', 'uydurma@yanlis.test')->sole();
        $rel = $this->inTenant($business, fn () => DealerRelationship::where('dealer_id', $user->dealer_id)->sole());

        return [$user, $rel];
    }

    public function test_user_changes_own_email_only_after_verification(): void
    {
        $business = $this->makeBusiness();
        $user = $business->user;
        $other = $this->makeBusiness()->user;
        $this->actingAs($user, 'business');

        $this->post(route('business.account.email'), ['email' => 'yeni@gercek.test', 'email_password' => 'yanlis'])->assertSessionHasErrors('email_password');
        $this->post(route('business.account.email'), ['email' => $other->email, 'email_password' => self::PASSWORD])->assertSessionHasErrors('email');

        $response = $this->post(route('business.account.email'), ['email' => 'Yeni@Gercek.test', 'email_password' => self::PASSWORD])
            ->assertSessionHasNoErrors()->assertRedirect();
        $url = session('email_verify_url'); // demo ortamı: sistem e-postası yok, bağlantı ekranda
        $this->assertNotNull($url);
        $this->assertSame($user->email, $user->fresh()->email, 'Doğrulanmadan değişmemeli.');
        $this->get(route('business.account'))->assertSee('Doğrulama bekliyor:')->assertSee('yeni@gercek.test')->assertSee('Bağlantıyı yeniden gönder');

        // Bağlantı açılınca yalnızca onay sayfası gelir; değişiklik butonla uygulanır.
        $token = basename(parse_url($url, PHP_URL_PATH));
        $this->get($url)->assertOk()->assertSee('yeni@gercek.test');
        $this->assertSame($user->email, $user->fresh()->email);

        $this->post(route('business.email.confirm'), ['token' => $token])->assertRedirect(route('business.account'));
        $this->assertSame('yeni@gercek.test', $user->fresh()->email);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.email_changed']);

        // Tek kullanımlık.
        $this->post(route('business.email.confirm'), ['token' => $token])->assertRedirect(route('business.login'))->assertSessionHasErrors('email');

        $this->post(route('business.logout'));
        $this->post(route('business.login.store'), ['email' => 'yeni@gercek.test', 'password' => self::PASSWORD])->assertRedirect(route('business.dashboard'));
    }

    public function test_verification_goes_through_business_gmail_and_can_be_resent_or_cancelled(): void
    {
        $business = $this->makeBusiness();
        $this->inTenant($business, fn () => \App\Models\EmailSetting::create([
            'from_email' => 'gonderen@gmail.com', 'app_password_encrypted' => 'x', 'status' => 'verified',
        ]));
        \Illuminate\Support\Facades\Queue::fake();
        $this->actingAs($business->user, 'business');

        $this->post(route('business.account.email'), ['email' => 'gercek@gmail.com', 'email_password' => self::PASSWORD])->assertSessionHasNoErrors();
        $this->assertNull(session('email_verify_url'), 'Gmail ile gönderildiğinde bağlantı ekranda gösterilmez.');
        $this->assertDatabaseHas('email_logs', ['template_key' => 'account_email_verify', 'to_email' => 'gercek@gmail.com', 'status' => 'queued']);

        $this->post(route('business.account.email.resend'))->assertSessionHasNoErrors();
        $this->assertSame(2, \App\Models\EmailLog::withoutBusinessScope()->where('template_key', 'account_email_verify')->count());
        $this->assertSame(1, AuthToken::where(['purpose' => 'email_change'])->whereNull('used_at')->count(), 'Yalnızca son bağlantı geçerli.');

        $this->delete(route('business.account.email.cancel'))->assertSessionHasNoErrors();
        $this->assertSame(0, AuthToken::where(['purpose' => 'email_change'])->whereNull('used_at')->count());
        $this->post(route('business.account.email.resend'))->assertSessionHasErrors('email');
    }

    public function test_email_taken_meanwhile_is_rejected_at_confirmation(): void
    {
        $rel = $this->orderingDealer($this->makeBusiness());
        $this->actingAs($rel->dealer->user, 'dealer')
            ->post(route('dealer.account.email'), ['email' => 'kapisilan@bayi.test', 'email_password' => self::PASSWORD])->assertSessionHasNoErrors();
        $token = basename(parse_url(session('email_verify_url'), PHP_URL_PATH));

        $this->makeDealer()->forceFill(['email' => 'kapisilan@bayi.test'])->save();

        $this->post(route('dealer.email.confirm'), ['token' => $token])->assertSessionHasErrors('email');
        $this->assertNotSame('kapisilan@bayi.test', $rel->dealer->user->fresh()->email);
    }

    public function test_business_corrects_pending_dealer_email_and_resends_activation(): void
    {
        $business = $this->makeBusiness();
        [$user, $rel] = $this->pendingDealer($business);
        $oldToken = AuthToken::where(['guard' => 'dealer', 'user_id' => $user->id, 'purpose' => 'activation'])->whereNull('used_at')->sole();

        $this->put(route('business.dealers.email', $rel), ['email' => 'gercek@bayi.test'])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('gercek@bayi.test', $user->fresh()->email);
        $this->assertSame('gercek@bayi.test', $user->dealer->fresh()->email);
        $this->assertNotNull($oldToken->fresh()->used_at, 'Eski bağlantı geçersiz olmalı.');
        $this->assertNotNull(session('activation_url'), 'E-posta ayarı yokken bağlantı ekranda gösterilir.');
        $this->assertDatabaseHas('audit_logs', ['action' => 'dealer.pending_email_corrected']);
    }

    public function test_business_cannot_change_email_of_active_or_shared_dealer(): void
    {
        $business = $this->makeBusiness();
        $active = $this->orderingDealer($business);
        $this->actingAs($business->user, 'business')
            ->put(route('business.dealers.email', $active), ['email' => 'baska@bayi.test'])->assertSessionHasErrors('account_email');
        $this->assertNotSame('baska@bayi.test', $active->dealer->user->fresh()->email);

        // Bekleyen ama başka toptancıyla da ilişkili hesap.
        [$user, $rel] = $this->pendingDealer($business);
        $this->relate($this->makeBusiness(), $user);
        $this->put(route('business.dealers.email', $rel), ['email' => 'baska2@bayi.test'])->assertSessionHasErrors('account_email');
        $this->assertSame('uydurma@yanlis.test', $user->fresh()->email);
    }

    public function test_business_password_reset_link_is_never_shown_when_mail_is_available(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $this->actingAs($business->user, 'business');

        // Demo: toptancının Gmail'i ve sistem e-postası yok → bağlantı ekranda.
        $this->post(route('business.dealers.password-reset', $rel))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertNotNull(session('reset_url'));

        // Canlı benzeri: sistem e-postası tanımlı → bağlantı toptancıya gösterilmez.
        $this->mock(SystemMailer::class, fn ($m) => $m->shouldReceive('isConfigured')->andReturn(true)->shouldReceive('send')->andReturn(true));
        $this->flushSession();
        $this->actingAs($business->user, 'business')->post(route('business.dealers.password-reset', $rel))->assertRedirect();
        $this->assertNull(session('reset_url'));
        $this->assertSame(2, AuthToken::where(['guard' => 'dealer', 'purpose' => 'password_reset'])->count());
    }

    public function test_platform_changes_business_user_email_with_reason(): void
    {
        $business = $this->makeBusiness();
        $this->actingAsPlatformAdmin();

        $this->put(route('platform.businesses.user', $business), ['name' => 'Yeni Yetkili', 'email' => 'patron@gercek.test', 'reason' => ''])
            ->assertSessionHasErrors('reason');
        $this->put(route('platform.businesses.user', $business), ['name' => 'Yeni Yetkili', 'email' => 'patron@gercek.test', 'reason' => 'Müşteri talebi, telefonla doğrulandı'])
            ->assertSessionHasNoErrors()->assertRedirect();

        $user = BusinessUser::find($business->user->id);
        $this->assertSame('patron@gercek.test', $user->email);
        $this->assertSame('Yeni Yetkili', $user->name);
        $this->assertDatabaseHas('audit_logs', ['action' => 'platform.user_email_changed']);

        $this->post(route('platform.businesses.password-reset', $business))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(1, AuthToken::where(['guard' => 'business', 'user_id' => $user->id, 'purpose' => 'password_reset'])->count());
    }

    public function test_platform_manages_dealer_accounts(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $dealerUser = $rel->dealer->user;
        $this->relate($this->makeBusiness(), $dealerUser); // iki toptancıyla çalışan bayi

        $this->actingAsPlatformAdmin();
        $this->get(route('platform.dealer-accounts.index', ['q' => $dealerUser->email]))->assertOk()->assertSee($dealerUser->email)->assertSee($business->display_name);
        $this->get(route('platform.dealer-accounts.show', $dealerUser))->assertOk()->assertSee($business->display_name);

        $this->put(route('platform.dealer-accounts.update', $dealerUser), ['name' => $dealerUser->name, 'email' => 'bayi@gercek.test', 'reason' => 'Bayi eski adresine erişemiyor'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('bayi@gercek.test', $dealerUser->fresh()->email);

        $this->put(route('platform.dealer-accounts.status', $dealerUser), ['status' => 'disabled', 'reason' => 'Ödeme sorunu'])->assertSessionHasNoErrors();
        $this->assertSame('disabled', $dealerUser->fresh()->status);

        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->post(route('dealer.login.store'), ['email' => 'bayi@gercek.test', 'password' => self::PASSWORD])->assertSessionHasErrors();
        $this->assertGuest('dealer');

        $this->actingAsPlatformAdmin();
        $this->put(route('platform.dealer-accounts.status', $dealerUser), ['status' => 'active', 'reason' => 'Ödeme alındı'])->assertSessionHasNoErrors();
        $this->assertSame('active', $dealerUser->fresh()->status);
        $this->assertSame(2, DB::table('audit_logs')->where('action', 'platform.dealer_user_status_changed')->count());
    }

    public function test_other_areas_cannot_use_platform_account_screens(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);

        $this->actingAs($business->user, 'business')->get(route('platform.dealer-accounts.index'))->assertRedirect(route('platform.login'));
        $this->actingAs($rel->dealer->user, 'dealer')->put(route('platform.dealer-accounts.update', $rel->dealer->user), [
            'name' => 'X', 'email' => 'ele@gecir.test', 'reason' => 'deneme deneme',
        ])->assertRedirect(route('platform.login'));
        $this->assertNotSame('ele@gecir.test', $rel->dealer->user->fresh()->email);
    }
}
