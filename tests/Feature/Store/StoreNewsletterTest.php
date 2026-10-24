<?php

namespace Tests\Feature\Store;

use App\Domain\Store\Mail\StoreMailer;
use App\Models\Store\StoreAdmin;
use App\Models\Store\StoreCustomer;
use App\Models\Store\StoreNewsletterCampaign;
use App\Models\Store\StoreNewsletterSubscriber;
use App\Models\Store\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/** E-bülten: iki adımlı onay, ayrılma, üyelik izniyle eşitleme, toplu e-posta ve dışa aktarma. */
class StoreNewsletterTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'http://magaza.localhost';

    /** @var list<array{to: string, subject: string, html: string, headers: array}> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['store.bot_protection' => false]);
        StoreSetting::put(['is_open' => '1']);

        $mailer = Mockery::mock(StoreMailer::class)->makePartial();
        $mailer->shouldReceive('send')->andReturnUsing(function ($to, $subject, $content) {
            $this->sent[] = ['to' => $to, 'subject' => $subject, 'html' => $content['action_url'] ?? '', 'headers' => []];

            return true;
        });
        $mailer->shouldReceive('sendHtml')->andReturnUsing(function ($to, $subject, $html, $text, $headers = []) {
            $this->sent[] = ['to' => $to, 'subject' => $subject, 'html' => $html, 'headers' => $headers];

            return true;
        });
        $mailer->shouldReceive('isConfigured')->andReturnTrue();
        $this->app->instance(StoreMailer::class, $mailer);
    }

    private function admin(): void
    {
        $this->actingAs(StoreAdmin::create(['name' => 'V', 'email' => 'v@magaza.local', 'password' => self::PASSWORD]), 'store_admin');
    }

    public function test_footer_signup_needs_consent_and_email_confirmation(): void
    {
        $this->get(self::HOST.'/urunler')->assertSee('id="e-bulten"', false)->assertSee('Kampanyalardan ilk siz haberdar olun');

        $this->from(self::HOST.'/urunler')->post(self::HOST.'/bulten', ['email' => 'ali@ornek.com'])
            ->assertSessionHasErrorsIn('newsletter', 'consent');
        $this->from(self::HOST.'/urunler')->post(self::HOST.'/bulten', ['email' => 'Ali@Ornek.com', 'consent' => '1'])
            ->assertRedirect(self::HOST.'/urunler#e-bulten')->assertSessionHas('newsletter');

        $s = StoreNewsletterSubscriber::sole();
        $this->assertSame(['ali@ornek.com', 'pending', 'footer'], [$s->email, $s->status, $s->source]);
        $this->assertNotNull($s->consented_at);
        $this->assertSame(StoreSetting::get('newsletter_consent'), $s->consent_text);
        $this->assertStringContainsString('/bulten/onay/'.$s->token, $this->sent[0]['html']);

        $this->get(self::HOST.'/bulten/onay/'.$s->token)->assertOk()->assertSee('Aboneliğiniz onaylandı');
        $this->assertSame('active', $s->fresh()->status);
        $this->get(self::HOST.'/bulten/onay/yanlis')->assertOk()->assertSee('Bağlantı geçersiz');

        // Ayrılma: önce onay sorulur, sonra çıkılır
        $this->get(self::HOST.'/bulten/ayril/'.$s->token)->assertOk()->assertSee('ayrılmak istiyor musunuz');
        $this->post(self::HOST.'/bulten/ayril/'.$s->token)->assertOk()->assertSee('E-bültenden ayrıldınız');
        $this->assertSame('unsubscribed', $s->fresh()->status);
    }

    public function test_customer_marketing_consent_syncs_and_account_close_forgets(): void
    {
        $this->post(self::HOST.'/uye-ol', ['first_name' => 'Ayşe', 'last_name' => 'Kaya', 'email' => 'ayse@ornek.com', 'password' => 'Gizli-Parola-123',
            'password_confirmation' => 'Gizli-Parola-123', 'kvkk' => '1', 'marketing' => '1'])->assertSessionHasNoErrors();
        $s = StoreNewsletterSubscriber::sole();
        $this->assertSame(['active', 'register'], [$s->status, $s->source]);

        $customer = StoreCustomer::sole();
        $this->put(self::HOST.'/hesabim', ['first_name' => 'Ayşe', 'last_name' => 'Kaya'])->assertSessionHasNoErrors();
        $this->assertSame('unsubscribed', $s->fresh()->status);

        $this->put(self::HOST.'/hesabim', ['first_name' => 'Ayşe', 'last_name' => 'Kaya', 'marketing' => '1']);
        $this->assertSame('active', $s->fresh()->status);

        // E-postadaki bağlantıyla ayrılınca hesaptaki izin de kalkar
        $this->post(self::HOST.'/bulten/ayril/'.$s->token);
        $this->assertNull($customer->fresh()->marketing_consent_at);

        $this->delete(self::HOST.'/hesabim', ['close_password' => 'Gizli-Parola-123']);
        $this->assertSame(0, StoreNewsletterSubscriber::count());
    }

    public function test_campaign_is_tested_sent_once_to_active_subscribers_with_unsubscribe_link(): void
    {
        $active = StoreNewsletterSubscriber::create(['email' => 'a@ornek.com', 'status' => 'active', 'token' => str_repeat('a', 48)]);
        StoreNewsletterSubscriber::create(['email' => 'b@ornek.com', 'status' => 'active', 'token' => str_repeat('b', 48)]);
        StoreNewsletterSubscriber::create(['email' => 'c@ornek.com', 'status' => 'pending', 'token' => str_repeat('c', 48)]);
        StoreNewsletterSubscriber::create(['email' => 'd@ornek.com', 'status' => 'unsubscribed', 'token' => str_repeat('d', 48)]);
        $this->admin();

        $this->post(route('store_admin.campaigns.store'), ['subject' => '', 'body' => ''])->assertSessionHasErrors(['subject', 'body']);
        $this->post(route('store_admin.campaigns.store'), ['subject' => 'Okula dönüş', 'body' => 'Metin', 'button_label' => 'Gör'])->assertSessionHasErrors('button_url');
        $this->post(route('store_admin.campaigns.store'), ['subject' => 'Okula dönüş başladı', 'preheader' => '%20 indirim', 'body' => "Merhaba!\n\n**Kalemlerde** indirim.",
            'button_label' => 'Fırsatları gör', 'button_url' => 'https://ornek.com/indirim'])->assertSessionHasNoErrors();
        $campaign = StoreNewsletterCampaign::sole();

        $this->get(route('store_admin.campaigns.preview', $campaign))->assertOk()->assertSee('<strong>Kalemlerde</strong>', false)->assertSee('Fırsatları gör');
        $this->post(route('store_admin.campaigns.test', $campaign))->assertSessionHas('status');
        $this->assertSame(['v@magaza.local', '[Deneme] Okula dönüş başladı'], [$this->sent[0]['to'], $this->sent[0]['subject']]);

        $this->sent = [];
        $this->post(route('store_admin.campaigns.send', $campaign))->assertSessionHas('status');
        $campaign->refresh();
        $this->assertSame(['sent', 2, 2, 0], [$campaign->status, $campaign->recipients_count, $campaign->sent_count, $campaign->failed_count]);
        $this->assertEqualsCanonicalizing(['a@ornek.com', 'b@ornek.com'], array_column($this->sent, 'to'));
        $mail = collect($this->sent)->firstWhere('to', 'a@ornek.com');
        $this->assertStringContainsString('/bulten/ayril/'.$active->token, $mail['html']);
        $this->assertStringContainsString('/bulten/ayril/'.$active->token, $mail['headers']['List-Unsubscribe']);

        // İkinci kez gönderilemez, gönderilmiş e-posta düzenlenemez
        $this->post(route('store_admin.campaigns.send', $campaign))->assertSessionHasErrors('campaign');
        $this->put(route('store_admin.campaigns.update', $campaign), ['subject' => 'Yeni', 'body' => 'x'])->assertSessionHasErrors('campaign');
        $this->getJson(route('store_admin.campaigns.progress', $campaign))->assertJson(['status' => 'sent', 'sent' => 2, 'percent' => 100]);
        $this->get(route('store_admin.campaigns.index'))->assertOk()->assertSee('Gönderildi');
    }

    public function test_admin_lists_exports_and_configures_newsletter(): void
    {
        StoreNewsletterSubscriber::create(['email' => 'a@ornek.com', 'status' => 'active', 'token' => str_repeat('a', 48), 'consented_at' => now()]);
        StoreNewsletterSubscriber::create(['email' => 'p@ornek.com', 'status' => 'pending', 'token' => str_repeat('p', 48)]);
        $this->admin();

        $this->get(route('store_admin.newsletter.index'))->assertOk()->assertSee('a@ornek.com')->assertSee('Onay bekliyor');
        $this->get(route('store_admin.newsletter.index', ['status' => 'active']))->assertSee('a@ornek.com')->assertDontSee('p@ornek.com');

        $iys = $this->get(route('store_admin.newsletter.export', ['format' => 'iys']))->assertOk()->streamedContent();
        $this->assertStringContainsString('ALICI;ALICI_TURU;IZIN_TURU;DURUM;IZIN_TARIHI;KAYNAK', $iys);
        $this->assertStringContainsString('a@ornek.com;BIREYSEL;EPOSTA;ONAY', $iys);
        $this->assertStringNotContainsString('p@ornek.com', $iys);

        $this->put(route('store_admin.newsletter.settings'), ['newsletter_title' => 'Fırsatları kaçırmayın', 'newsletter_consent' => 'İzin veriyorum.'])->assertSessionHasNoErrors();
        $this->get(self::HOST.'/urunler')->assertDontSee('id="e-bulten"', false);
        $this->put(route('store_admin.newsletter.settings'), ['newsletter_enabled' => '1', 'newsletter_title' => 'Fırsatları kaçırmayın', 'newsletter_consent' => 'İzin veriyorum.']);
        $this->get(self::HOST.'/urunler')->assertSee('Fırsatları kaçırmayın')->assertSee('İzin veriyorum.');
    }
}
