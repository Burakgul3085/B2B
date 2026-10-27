<?php

namespace Tests\Feature\Auth;

use App\Domain\Mail\MailTransport;
use App\Domain\Mail\SecretBox;
use App\Domain\Store\Mail\StoreEmailRenderer;
use App\Domain\Store\Mail\StoreMailer;
use App\Domain\Tenancy\TenantContext;
use App\Models\Business;
use App\Models\EmailLog;
use App\Models\EmailSetting;
use App\Models\Store\StoreAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeMailTransport;
use Tests\TestCase;

/**
 * Toptancı, bayi ve mağaza yönetimi girişinde e-postaya giden 4 haneli kod (App\Domain\Auth\LoginCode);
 * platform girişinde doğrulama uygulaması kalır.
 */
class LoginCodeTest extends TestCase
{
    use RefreshDatabase;

    private FakeMailTransport $transport;

    /** @var \ArrayObject<int, array{to: string, subject: string, content: array}> mağaza e-postaları */
    private \ArrayObject $storeMails;

    protected function setUp(): void
    {
        parent::setUp();
        config(['b2b.login_code' => true]);
        $this->transport = new FakeMailTransport;
        $this->app->instance(MailTransport::class, $this->transport);
        $this->storeMails = new \ArrayObject;
        $this->app->instance(StoreMailer::class, new class($this->storeMails) extends StoreMailer
        {
            public function __construct(private \ArrayObject $mails) {}

            public function send(string $to, string $subject, array $content): bool
            {
                $this->mails[] = compact('to', 'subject', 'content');

                return true;
            }
        });
    }

    private function configureMail(Business $business): void
    {
        $this->inTenant($business, fn () => EmailSetting::create([
            'from_email' => 'satis@yapboz.test', 'from_name' => 'Yapboz', 'status' => 'verified',
            'app_password_encrypted' => app(SecretBox::class)->encrypt('abcdefghijklmnop'),
        ]));
    }

    private function inTenant(Business $business, callable $fn): mixed
    {
        return app(TenantContext::class)->runAs($business->id, $fn);
    }

    /** Son gönderilen B2B e-postasındaki kod (düz metin gövdeden). */
    private function lastB2bCode(): string
    {
        $mail = end($this->transport->sent)['mail'];
        $this->assertMatchesRegularExpression('/Doğrulama kodunuz: (\d{4})/', $mail['text']);
        preg_match('/Doğrulama kodunuz: (\d{4})/', $mail['text'], $m);

        return $m[1];
    }

    public function test_business_user_needs_the_emailed_code_and_the_code_is_not_logged(): void
    {
        $business = $this->makeBusiness();
        $this->configureMail($business);
        $user = $business->user;

        $this->post(route('business.login.store'), ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertRedirect(route('business.login.code'));
        $this->assertGuest('business');
        $this->get(route('business.dashboard'))->assertRedirect(route('business.login'));

        $this->assertSame([$user->email], $this->transport->recipients());
        $mail = $this->transport->sent[0]['mail'];
        $this->assertStringContainsString('giriş doğrulama kodunuz', $mail['subject']);
        $code = $this->lastB2bCode();
        $this->assertStringContainsString($code, $mail['html']);

        // Kod e-posta kaydına yazılmaz.
        $log = $this->inTenant($business, fn () => EmailLog::where('template_key', 'login_code')->sole());
        $this->assertSame('sent', $log->status);
        $this->assertStringNotContainsString($code, json_encode($log->payload));

        $this->get(route('business.login.code'))->assertOk()->assertSee('E-postanızı kontrol edin')->assertSee(mb_substr($user->email, 0, 1).'**', false);
        $wrong = $code === '0000' ? '1111' : '0000';
        $this->post(route('business.login.code.verify'), ['code' => $wrong])->assertSessionHasErrors('code');
        $this->assertGuest('business');

        $this->post(route('business.login.code.verify'), ['code' => $code])->assertRedirect(route('business.dashboard'));
        $this->assertAuthenticatedAs($user, 'business');
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_dealer_gets_the_code_from_its_wholesalers_mail_and_too_many_wrong_codes_restart_login(): void
    {
        $business = $this->makeBusiness();
        $this->configureMail($business);
        $dealer = $this->makeDealer();
        $this->relate($business, $dealer);

        $this->post(route('dealer.login.store'), ['email' => $dealer->email, 'password' => self::PASSWORD])->assertRedirect(route('dealer.login.code'));
        $this->assertSame([$dealer->email], $this->transport->recipients());
        $code = $this->lastB2bCode();
        $wrong = $code === '0000' ? '1111' : '0000';

        for ($i = 1; $i <= 4; $i++) {
            $this->post(route('dealer.login.code.verify'), ['code' => $wrong])->assertSessionHasErrors('code');
        }
        $this->post(route('dealer.login.code.verify'), ['code' => $wrong])->assertRedirect(route('dealer.login'));
        // Doğru kod artık işe yaramaz; giriş baştan yapılır.
        $this->post(route('dealer.login.code.verify'), ['code' => $code])->assertRedirect(route('dealer.login'));
        $this->assertGuest('dealer');
        $this->get(route('dealer.login.code'))->assertRedirect(route('dealer.login'));
    }

    public function test_code_expires_and_resend_waits_a_minute(): void
    {
        $business = $this->makeBusiness();
        $this->configureMail($business);
        $user = $business->user;
        $this->post(route('business.login.store'), ['email' => $user->email, 'password' => self::PASSWORD]);
        $first = $this->lastB2bCode();

        $this->post(route('business.login.code.resend'))->assertSessionHasErrors('code');
        $this->assertCount(1, $this->transport->sent);

        $this->travel(61)->seconds();
        $this->post(route('business.login.code.resend'))->assertSessionHas('status');
        $this->assertCount(2, $this->transport->sent);
        $second = $this->lastB2bCode();
        if ($first !== $second) {
            $this->post(route('business.login.code.verify'), ['code' => $first])->assertSessionHasErrors('code'); // eski kod geçersiz
        }

        $this->travel(11)->minutes();
        $this->post(route('business.login.code.verify'), ['code' => $second])->assertRedirect(route('business.login'))->assertSessionHasErrors('email');
        $this->assertGuest('business');
    }

    public function test_login_is_refused_when_the_code_cannot_be_emailed(): void
    {
        $business = $this->makeBusiness(); // e-posta ayarı yok
        $this->post(route('business.login.store'), ['email' => $business->user->email, 'password' => self::PASSWORD])
            ->assertSessionHasErrors(['email' => 'Doğrulama kodu e-postası gönderilemedi. Lütfen biraz sonra tekrar deneyin veya yöneticinize bildirin.']);
        $this->assertGuest('business');
        $this->get(route('business.login.code'))->assertRedirect(route('business.login'));

        // Sunucuda kapatılırsa (LOGIN_EMAIL_CODE=false) parola yeterlidir.
        config(['b2b.login_code' => false]);
        $this->post(route('business.login.store'), ['email' => $business->user->email, 'password' => self::PASSWORD])->assertRedirect(route('business.dashboard'));
    }

    public function test_store_admin_gets_the_code_with_the_store_template(): void
    {
        $admin = StoreAdmin::create(['name' => 'Onur', 'email' => 'onur@magaza.local', 'password' => self::PASSWORD, 'is_super' => true]);

        $this->post(route('store_admin.login.store'), ['email' => $admin->email, 'password' => self::PASSWORD])
            ->assertRedirect(route('store_admin.login.code'));
        $this->assertGuest('store_admin');
        $this->assertCount(1, $this->storeMails);
        $mail = $this->storeMails[0];
        $this->assertSame('onur@magaza.local', $mail['to']);
        $this->assertMatchesRegularExpression('/^\d{4}$/', $mail['content']['code']);

        $this->get(route('store_admin.login.code'))->assertOk()->assertSee('o***@magaza.local');
        $this->post(route('store_admin.login.code.verify'), ['code' => 'ab12'])->assertSessionHasErrors('code');
        $this->post(route('store_admin.login.code.verify'), ['code' => $mail['content']['code']])->assertRedirect(route('store_admin.dashboard'));
        $this->get(route('store_admin.dashboard'))->assertOk()->assertSee('Hoş geldiniz, Onur');
        $this->get(route('store_admin.account'))->assertOk()->assertSee('Giriş doğrulaması');
    }

    public function test_store_mail_rendering_shows_the_code_box(): void
    {
        $html = app(StoreEmailRenderer::class)->render('Kod', ['title' => 'Giriş doğrulama kodunuz', 'intro' => 'Merhaba', 'code' => '4821']);
        $this->assertStringContainsString('Doğrulama kodunuz', $html['html']);
        $this->assertStringContainsString('4821', $html['html']);
        $this->assertStringContainsString('Doğrulama kodunuz: 4821', $html['text']);
    }

    public function test_platform_keeps_the_authenticator_and_gets_no_email_code(): void
    {
        $admin = $this->makePlatformAdmin();
        $this->get(route('platform.login.code'))->assertRedirect(route('platform.login'));
        $this->post(route('platform.login.store'), ['email' => $admin->email, 'password' => self::PASSWORD])
            ->assertRedirect(route('platform.two-factor.setup'));
        $this->assertSame([], $this->transport->sent);
        $this->assertCount(0, $this->storeMails);
    }
}
