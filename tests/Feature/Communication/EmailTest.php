<?php

namespace Tests\Feature\Communication;

use App\Domain\Ledger\LedgerService;
use App\Domain\Mail\BusinessMailer;
use App\Domain\Mail\MailFailure;
use App\Domain\Mail\MailTransport;
use App\Domain\Mail\SecretBox;
use App\Models\AuditLog;
use App\Models\BusinessModule;
use App\Models\CurrentAccount;
use App\Models\DealerApplication;
use App\Models\EmailLog;
use App\Models\EmailSetting;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesOrders;
use Tests\Fakes\FakeMailTransport;
use Tests\TestCase;

/** Bölüm 14 — işletme bazlı Gmail ayarı ve PHPMailer gönderimi. */
class EmailTest extends TestCase
{
    use CreatesOrders, RefreshDatabase;

    private const APP_PASSWORD = 'abcdefghijklmnop';

    private FakeMailTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transport = new FakeMailTransport;
        $this->app->instance(MailTransport::class, $this->transport);
    }

    private function configure($business, string $status = 'verified'): void
    {
        $this->inTenant($business, fn () => EmailSetting::create([
            'from_email' => 'satis@akgun.test', 'from_name' => 'Akgün Satış', 'status' => $status,
            'app_password_encrypted' => app(SecretBox::class)->encrypt(self::APP_PASSWORD),
        ]));
    }

    public function test_settings_store_only_encrypted_password_and_never_show_it(): void
    {
        $business = $this->makeBusiness();
        $this->actingAs($business->user, 'business');

        $this->put(route('business.email.update'), ['from_email' => 'satis@akgun.test', 'app_password' => 'kisa'])->assertSessionHasErrors('app_password');
        $this->put(route('business.email.update'), ['from_email' => 'satis@akgun.test', 'app_password' => 'abcd efgh ijkl mnop', 'from_name' => 'Akgün'])
            ->assertSessionHasNoErrors()->assertRedirect();

        $raw = DB::table('email_settings')->value('app_password_encrypted');
        $this->assertStringNotContainsString(self::APP_PASSWORD, $raw);
        $this->assertSame(self::APP_PASSWORD, app(SecretBox::class)->decrypt($raw));

        $page = $this->get(route('business.email.settings'))->assertOk()->assertSee('Yeni uygulama şifresi gir');
        $page->assertDontSee(self::APP_PASSWORD)->assertDontSee($raw);
        $this->assertStringNotContainsString(self::APP_PASSWORD, AuditLog::pluck('new_values')->toJson());

        // Şifre boş bırakılırsa kayıtlı şifre korunur.
        $this->put(route('business.email.update'), ['from_email' => 'satis@akgun.test', 'from_name' => 'Yeni ad'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(self::APP_PASSWORD, app(SecretBox::class)->decrypt(DB::table('email_settings')->value('app_password_encrypted')));
    }

    public function test_connection_test_success_and_failure(): void
    {
        $business = $this->makeBusiness();
        $this->configure($business, 'unverified');
        $this->actingAs($business->user, 'business');

        $this->post(route('business.email.test'))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('verified', $this->inTenant($business, fn () => EmailSetting::value('status')));
        $this->assertSame(['satis@akgun.test'], $this->transport->recipients(), 'Test e-postası ana adrese gider.');
        // Sunucu, port ve şifreleme kullanıcıdan istenmez: formda yok.
        $this->get(route('business.email.settings'))->assertDontSee('smtp.gmail.com')->assertDontSee('Port');

        $this->transport->failWith = 'auth';
        $this->post(route('business.email.test'))->assertSessionHasErrors(['email' => MailFailure::MESSAGES['auth']]);
        $this->assertSame('failed', $this->inTenant($business, fn () => EmailSetting::value('status')));
    }

    public function test_order_email_is_sent_from_business_account_after_commit(): void
    {
        $business = $this->makeBusiness();
        $this->configure($business);
        $rel = $this->orderingDealer($business);
        $product = $this->makeProduct($business);
        $this->stockUp($business, $product, '10');

        $this->actingAs($rel->dealer->user, 'dealer');
        $this->post(route('dealer.cart.add'), ['product_id' => $product->id, 'quantity' => '1'])->assertRedirect();
        $this->post(route('dealer.cart.submit'), ['address_id' => $this->inTenant($business, fn () => $rel->addresses()->value('id')), 'submit_token' => (string) Str::uuid()])->assertRedirect();

        // Toptancıya gelen bildirim ana e-posta adresine (Gmail hesabı) düşer.
        $this->assertContains('satis@akgun.test', $this->transport->recipients());
        $this->assertNotContains($business->user->email, $this->transport->recipients());
        $this->assertSame('satis@akgun.test', $this->transport->sent[0]['sender']['email']);
        $this->assertSame(self::APP_PASSWORD, $this->transport->sent[0]['sender']['password']);
        $this->assertStringContainsString('Yeni sipariş', $this->transport->sent[0]['mail']['subject']);
        $this->assertSame('sent', $this->inTenant($business, fn () => EmailLog::where('template_key', 'order_submitted')->value('status')));
    }

    public function test_email_failure_does_not_roll_back_the_order(): void
    {
        $business = $this->makeBusiness();
        $this->configure($business);
        $this->transport->failWith = 'connect';
        $rel = $this->orderingDealer($business);
        $product = $this->makeProduct($business);
        $this->stockUp($business, $product, '10');

        $this->actingAs($rel->dealer->user, 'dealer');
        $this->post(route('dealer.cart.add'), ['product_id' => $product->id, 'quantity' => '1']);
        $this->post(route('dealer.cart.submit'), ['address_id' => $this->inTenant($business, fn () => $rel->addresses()->value('id')), 'submit_token' => (string) Str::uuid()])->assertRedirect();

        $this->assertSame(1, $this->inTenant($business, fn () => Order::count()));
        $log = $this->inTenant($business, fn () => EmailLog::where('template_key', 'order_submitted')->first());
        $this->assertSame('failed', $log->status);
        $this->assertSame('connect', $log->last_error_code);

        // Tekrar gönderim
        $this->transport->failWith = null;
        $this->flushSession();
        $this->actingAs($business->user, 'business')->post(route('business.email.retry', $log))->assertRedirect();
        $this->assertSame('sent', $log->fresh()->status);
    }

    public function test_without_settings_emails_are_skipped_but_account_works(): void
    {
        $business = $this->makeBusiness();
        $this->post("/basvuru/{$business->slug}", [
            'legal_name' => 'Yeni Bayi', 'contact_name' => 'Ali', 'phone' => '0212 111 22 33', 'email' => 'ali@bayi.test',
            'city' => 'İzmir', 'district' => 'Konak', 'address' => 'Adres', 'consent' => '1',
        ]);
        $application = $this->inTenant($business, fn () => DealerApplication::sole());

        $this->actingAs($business->user, 'business')->post(route('business.applications.approve', $application),
            ['payment_term_days' => 30, 'login_email' => 'ali@bayi.test', 'password' => 'BayiSifre2026'])->assertSessionHasNoErrors();

        $this->assertSame([], $this->transport->sent);
        $this->assertSame(['skipped'], $this->inTenant($business, fn () => EmailLog::pluck('status')->unique()->values()->all()));
        $this->flushSession();
        $this->post('/bayi/login', ['email' => 'ali@bayi.test', 'password' => 'BayiSifre2026'])->assertRedirect();
        $this->assertAuthenticated('dealer');
    }

    public function test_application_notifies_business_and_applicant_and_approval_mail_has_no_password(): void
    {
        $business = $this->makeBusiness();
        $this->configure($business);
        $this->post("/basvuru/{$business->slug}", [
            'legal_name' => 'Yeni Bayi', 'contact_name' => 'Ali', 'phone' => '0212 111 22 33', 'email' => 'ali@bayi.test',
            'city' => 'İzmir', 'district' => 'Konak', 'address' => 'Adres', 'consent' => '1',
        ]);
        $this->assertContains('satis@akgun.test', $this->transport->recipients(), 'Başvuru bildirimi toptancının ana adresine gitmeli.');
        $applicant = collect($this->transport->sent)->firstWhere('mail.to', 'ali@bayi.test');
        $this->assertNotNull($applicant, 'Başvuru sahibine "başvurunuz alındı" e-postası gitmeli.');
        $this->assertStringContainsString('başvurunuz alındı', mb_strtolower($applicant['mail']['subject']));
        $application = $this->inTenant($business, fn () => DealerApplication::sole());

        $this->transport->sent = [];
        $this->actingAs($business->user, 'business')->post(route('business.applications.approve', $application),
            ['payment_term_days' => 30, 'login_email' => 'ali@bayi.test', 'password' => 'BayiSifre2026'])->assertSessionHasNoErrors();

        $mail = collect($this->transport->sent)->firstWhere('mail.to', 'ali@bayi.test');
        $this->assertNotNull($mail);
        $this->assertStringContainsString('ali@bayi.test', $mail['mail']['html']);
        $this->assertStringNotContainsString('BayiSifre2026', $mail['mail']['html'], 'Şifre e-postayla gönderilmez.');
        $this->assertStringNotContainsString('aktivasyon', $mail['mail']['html']);
    }

    public function test_disabled_email_module_skips_sending(): void
    {
        $business = $this->makeBusiness();
        $this->configure($business);
        BusinessModule::create(['business_id' => $business->id, 'module_key' => 'email', 'enabled' => false]);

        $log = $this->inTenant($business, fn () => app(BusinessMailer::class)->queue('announcement', 'x@y.test', ['baslik' => 'A', 'metin' => 'B']));

        $this->assertSame('skipped', $log->status);
        $this->assertSame([], $this->transport->sent);
    }

    public function test_template_override_and_placeholder_safety(): void
    {
        $business = $this->makeBusiness();
        $this->configure($business);
        $this->actingAs($business->user, 'business')->put(route('business.email.template'), [
            'key' => 'announcement', 'subject' => 'Duyuru: {baslik}', 'intro' => '{metin} — {bilinmeyen}',
        ])->assertRedirect();

        $this->inTenant($business, fn () => app(BusinessMailer::class)->queue('announcement', 'x@y.test', ['baslik' => 'Yeni sezon', 'metin' => '<script>alert(1)</script> {baslik}']));

        $mail = $this->transport->sent[0]['mail'];
        $this->assertSame('Duyuru: Yeni sezon', $mail['subject']);
        $this->assertStringNotContainsString('<script>', $mail['html']);
        $this->assertStringContainsString('&lt;script&gt;', $mail['html']);
        $this->assertStringContainsString('{baslik}', $mail['html'], 'Değer içindeki süslü parantez değiştirilmemeli.');
        $this->assertStringNotContainsString('{bilinmeyen}', $mail['html']);
    }

    public function test_payment_reminder_command(): void
    {
        $business = $this->makeBusiness();
        $this->configure($business);
        $this->inTenant($business, fn () => EmailSetting::query()->update(['payment_reminders' => true]));
        $rel = $this->orderingDealer($business);
        $quiet = $this->orderingDealer($business);
        $this->inTenant($business, fn () => app(LedgerService::class)->setOpening(
            CurrentAccount::where('dealer_relationship_id', $rel->id)->sole(), '100.00', now()->subDays(40)->toDateString(), null));

        $this->artisan('ledger:send-reminders')->assertSuccessful();

        $this->assertSame([$rel->dealer->user->email], $this->transport->recipients());
        $this->assertNotContains($quiet->dealer->user->email, $this->transport->recipients());
    }
}
