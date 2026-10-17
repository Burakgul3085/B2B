<?php

namespace Tests\Feature\Auth;

use App\Domain\Mail\MailTransport;
use App\Domain\Mail\SecretBox;
use App\Models\AuthToken;
use App\Models\BusinessUser;
use App\Models\DealerApplication;
use App\Models\DealerRelationship;
use App\Models\DealerUser;
use App\Models\EmailSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesOrders;
use Tests\Fakes\FakeMailTransport;
use Tests\TestCase;

/**
 * Sade hesap ve e-posta kurgusu:
 *  - Hesaplar şifreyle açılır, hemen etkindir (aktivasyon/doğrulama bağlantısı yok).
 *  - Herkes kendi e-postasını mevcut şifresiyle hemen değiştirir.
 *  - Toptancı bayisine, platform her hesaba yeni şifre belirler; platform bayi açar ve başvuruları onaylar.
 *  - Tek ana Gmail: toptancı panelinden ve platformdan girilir; toptancı bildirimleri bu adrese düşer.
 */
class AccountEmailTest extends TestCase
{
    use CreatesOrders, RefreshDatabase;

    private FakeMailTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transport = new FakeMailTransport;
        $this->app->instance(MailTransport::class, $this->transport);
    }

    private function configureMail($business, string $address = 'ana@gmail.com'): void
    {
        $this->inTenant($business, fn () => EmailSetting::create([
            'from_email' => $address, 'status' => 'verified', 'app_password_encrypted' => app(SecretBox::class)->encrypt('abcdefghijklmnop'),
        ]));
    }

    public function test_user_changes_own_email_immediately_with_current_password(): void
    {
        $business = $this->makeBusiness();
        $other = $this->makeBusiness()->user;
        $this->actingAs($business->user, 'business');

        $this->post(route('business.account.email'), ['email' => 'yeni@gercek.test', 'email_password' => 'yanlis'])->assertSessionHasErrors('email_password');
        $this->post(route('business.account.email'), ['email' => $other->email, 'email_password' => self::PASSWORD])->assertSessionHasErrors('email');

        $this->post(route('business.account.email'), ['email' => 'Yeni@Gercek.test', 'email_password' => self::PASSWORD])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('yeni@gercek.test', $business->user->fresh()->email);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.email_changed']);
        $this->assertSame(0, AuthToken::count(), 'Doğrulama bağlantısı üretilmez.');

        $this->post(route('business.logout'));
        $this->post(route('business.login.store'), ['email' => 'yeni@gercek.test', 'password' => self::PASSWORD])->assertRedirect(route('business.dashboard'));
    }

    public function test_dealer_changes_own_email_and_password(): void
    {
        $rel = $this->orderingDealer($this->makeBusiness());
        $user = $rel->dealer->user;
        $this->actingAs($user, 'dealer');

        $this->get(route('dealer.account'))->assertOk()->assertSee('Giriş e-postası');
        $this->post(route('dealer.account.email'), ['email' => 'bayi@gercek.test', 'email_password' => self::PASSWORD])->assertSessionHasNoErrors();
        $this->put(route('dealer.account.password'), ['current_password' => self::PASSWORD, 'password' => 'BayiYeni2026x', 'password_confirmation' => 'BayiYeni2026x'])
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('bayi@gercek.test', $user->email);
        $this->assertTrue(Hash::check('BayiYeni2026x', $user->password));
    }

    public function test_business_creates_dealer_with_password_and_dealer_logs_in_immediately(): void
    {
        $business = $this->makeBusiness();
        $form = [
            'legal_name' => 'Yeni Kırtasiye', 'contact_name' => 'Ayşe Demir', 'email' => 'ayse@bayi.test', 'phone' => '0212 111 22 33',
            'city' => 'İstanbul', 'district' => 'Şişli', 'address' => 'Cadde 1', 'payment_term_days' => 30,
        ];
        $this->actingAs($business->user, 'business');
        $this->post(route('business.dealers.store'), $form)->assertSessionHasErrors('password');
        $this->post(route('business.dealers.store'), $form + ['password' => 'kisa'])->assertSessionHasErrors('password');
        $this->post(route('business.dealers.store'), $form + ['password' => 'BayiSifre2026'])->assertSessionHasNoErrors()->assertRedirect();

        $user = DealerUser::where('email', 'ayse@bayi.test')->sole();
        $this->assertSame('active', $user->status);
        $this->assertSame(0, AuthToken::count());

        $this->flushSession();
        $this->post(route('dealer.login.store'), ['email' => 'ayse@bayi.test', 'password' => 'BayiSifre2026'])->assertRedirect(route('dealer.home'));
    }

    public function test_business_sets_new_password_for_dealer(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $this->actingAs($business->user, 'business');

        $this->put(route('business.dealers.password', $rel), ['password' => 'YeniBayi2026', 'password_confirmation' => 'farkli'])->assertSessionHasErrors('password');
        $this->put(route('business.dealers.password', $rel), ['password' => 'YeniBayi2026', 'password_confirmation' => 'YeniBayi2026'])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('YeniBayi2026', $rel->dealer->user->fresh()->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'dealer.password_set_by_business']);

        // Eski akıştan kalan, şifresi olmayan hesap şifre belirlenince etkinleşir.
        $pending = $this->orderingDealer($business);
        $pending->dealer->user->forceFill(['status' => 'pending_activation', 'password' => null])->save();
        $this->put(route('business.dealers.password', $pending), ['password' => 'YeniBayi2026', 'password_confirmation' => 'YeniBayi2026'])->assertSessionHasNoErrors();
        $this->assertSame('active', $pending->dealer->user->fresh()->status);
    }

    public function test_business_corrects_email_only_before_dealers_first_login_and_if_not_shared(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $this->actingAs($business->user, 'business');

        $this->put(route('business.dealers.email', $rel), ['email' => 'dogru@bayi.test'])->assertSessionHasNoErrors();
        $this->assertSame('dogru@bayi.test', $rel->dealer->user->fresh()->email);

        $rel->dealer->user->forceFill(['last_login_at' => now()])->save();
        $this->put(route('business.dealers.email', $rel), ['email' => 'baska@bayi.test'])->assertSessionHasErrors('account_email');

        $shared = $this->orderingDealer($business);
        $this->relate($this->makeBusiness(), $shared->dealer->user);
        $this->put(route('business.dealers.email', $shared), ['email' => 'baska2@bayi.test'])->assertSessionHasErrors('account_email');
    }

    public function test_forgot_password_link_is_sent_through_the_business_gmail(): void
    {
        $business = $this->makeBusiness();
        $this->configureMail($business);
        $rel = $this->orderingDealer($business);

        $this->post(route('dealer.password.email'), ['email' => $rel->dealer->user->email])->assertSessionHas('status');

        $mail = collect($this->transport->sent)->firstWhere('mail.to', $rel->dealer->user->email);
        $this->assertNotNull($mail, 'Sıfırlama bağlantısı bayinin kendi e-postasına gitmeli.');
        $this->assertSame('ana@gmail.com', $mail['sender']['email']);
        $this->assertStringContainsString('/bayi/parola-sifirla/', $mail['mail']['html']);
    }

    public function test_platform_manages_business_owner_and_mail_settings(): void
    {
        $business = $this->makeBusiness();
        $this->actingAsPlatformAdmin();

        $this->put(route('platform.businesses.user', $business), ['name' => 'Yeni Yetkili', 'email' => 'patron@gercek.test', 'reason' => ''])
            ->assertSessionHasErrors('reason');
        $this->put(route('platform.businesses.user', $business), [
            'name' => 'Yeni Yetkili', 'email' => 'patron@gercek.test', 'password' => 'PatronSifre2026', 'password_confirmation' => 'PatronSifre2026',
            'reason' => 'Müşteri talebi, telefonla doğrulandı',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $user = BusinessUser::find($business->user->id);
        $this->assertSame('patron@gercek.test', $user->email);
        $this->assertTrue(Hash::check('PatronSifre2026', $user->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'platform.user_email_changed']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'platform.password_set']);

        // Ana Gmail ayarı platformdan da girilir ve test edilir.
        $this->put(route('platform.businesses.email', $business), ['from_email' => 'burak@gmail.com', 'app_password' => 'abcd efgh ijkl mnop'])->assertSessionHasNoErrors();
        $this->assertSame('burak@gmail.com', EmailSetting::withoutBusinessScope()->where('business_id', $business->id)->value('from_email'));
        $this->post(route('platform.businesses.email.test', $business))->assertSessionHasNoErrors();
        $this->assertSame(['burak@gmail.com'], $this->transport->recipients());
        $this->assertSame('verified', EmailSetting::withoutBusinessScope()->where('business_id', $business->id)->value('status'));
    }

    public function test_platform_creates_dealer_for_a_chosen_business_and_manages_it(): void
    {
        $business = $this->makeBusiness();
        $this->actingAsPlatformAdmin();

        $this->post(route('platform.dealer-accounts.store'), [
            'business_id' => $business->id, 'legal_name' => 'Platform Bayisi', 'contact_name' => 'Can', 'email' => 'can@bayi.test',
            'phone' => '0212 111 22 33', 'city' => 'Bursa', 'district' => 'Nilüfer', 'address' => 'Adres', 'password' => 'CanSifre2026',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $user = DealerUser::where('email', 'can@bayi.test')->sole();
        $this->assertSame(1, DealerRelationship::withoutBusinessScope()->where('business_id', $business->id)->where('dealer_id', $user->dealer_id)->count());
        $this->get(route('platform.dealer-accounts.show', $user))->assertOk()->assertSee($business->display_name);

        $this->put(route('platform.dealer-accounts.update', $user), [
            'name' => 'Can Yılmaz', 'email' => 'can@gercek.test', 'password' => 'CanYeni2026x', 'password_confirmation' => 'CanYeni2026x', 'reason' => 'Bayi talebi',
        ])->assertSessionHasNoErrors();
        $user->refresh();
        $this->assertSame('can@gercek.test', $user->email);
        $this->assertTrue(Hash::check('CanYeni2026x', $user->password));

        $this->put(route('platform.dealer-accounts.status', $user), ['status' => 'disabled', 'reason' => 'Ödeme sorunu'])->assertSessionHasNoErrors();
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->post(route('dealer.login.store'), ['email' => 'can@gercek.test', 'password' => 'CanYeni2026x'])->assertSessionHasErrors();
        $this->assertGuest('dealer');
    }

    public function test_application_reaches_platform_and_platform_can_approve_it(): void
    {
        $business = $this->makeBusiness();
        $this->configureMail($business);
        $this->post("/basvuru/{$business->slug}", [
            'legal_name' => 'Başvuran Kırtasiye', 'contact_name' => 'Deniz', 'phone' => '0212 111 22 33', 'email' => 'deniz@bayi.test',
            'city' => 'İzmir', 'district' => 'Konak', 'address' => 'Adres', 'consent' => '1',
        ])->assertRedirect();
        $application = DealerApplication::withoutBusinessScope()->sole();

        // Toptancının ana adresine ve başvuru sahibine e-posta.
        $this->assertContains('ana@gmail.com', $this->transport->recipients());
        $this->assertContains('deniz@bayi.test', $this->transport->recipients());

        $this->actingAsPlatformAdmin();
        $this->get(route('platform.applications.index'))->assertOk()->assertSee('Başvuran Kırtasiye')->assertSee($business->display_name);
        $this->get(route('platform.applications.show', $application->id))->assertOk();
        $this->post(route('platform.applications.approve', $application->id), ['login_email' => 'deniz@bayi.test', 'password' => 'DenizSifre2026', 'payment_term_days' => 30])
            ->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('approved', $application->fresh()->status);
        $this->assertSame('active', DealerUser::where('email', 'deniz@bayi.test')->sole()->status);
        $this->post(route('platform.applications.approve', $application->id), ['login_email' => 'deniz@bayi.test', 'password' => 'DenizSifre2026', 'payment_term_days' => 30])
            ->assertSessionHasErrors('approve');
    }

    public function test_dealer_login_page_links_to_application_form(): void
    {
        $only = $this->makeBusiness();
        $this->get(route('dealer.login'))->assertOk()->assertSee('Bayimiz olmak ister misiniz?')->assertSee(route('public.apply'), false);
        $this->get(route('public.apply'))->assertRedirect(route('public.application', $only->slug));

        $second = $this->makeBusiness();
        $this->get(route('public.apply'))->assertOk()->assertSee($only->display_name)->assertSee($second->display_name);
    }

    public function test_views_have_no_inline_event_handlers_blocked_by_csp(): void
    {
        $offenders = collect(File::allFiles(resource_path('views')))
            ->filter(fn ($f) => preg_match('/\son(submit|click|change|input|load)=/', $f->getContents()))
            ->map(fn ($f) => $f->getRelativePathname())->values()->all();

        $this->assertSame([], $offenders, 'İçerik güvenlik politikası satır içi olay işleyicilerini engeller; data-confirm / data-autosubmit kullanın.');
    }
}
