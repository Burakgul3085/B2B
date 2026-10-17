<?php

namespace Tests\Feature\Dealers;

use App\Domain\Tenancy\TenantContext;
use App\Models\CurrentAccount;
use App\Models\DealerAddress;
use App\Models\DealerApplication;
use App\Models\DealerRelationship;
use App\Models\DealerUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** docs/03-is-kurallari.md §1 — başvuru, onay, hesap açma. */
class DealerOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function form(array $overrides = []): array
    {
        return $overrides + [
            'legal_name' => 'Onur Kırtasiye', 'contact_name' => 'Onur Yılmaz', 'phone' => '0212 555 44 33',
            'email' => 'onur@ornek.test', 'city' => 'İstanbul', 'district' => 'Kadıköy', 'address' => 'Moda Cd. No:1',
            'business_type' => 'retail', 'consent' => '1',
        ];
    }

    private function applications($business)
    {
        return app(TenantContext::class)->runAs($business->id, fn () => DealerApplication::with('attachments')->get());
    }

    public function test_public_form_creates_application_with_consent_record(): void
    {
        Storage::fake('local');
        $business = $this->makeBusiness();

        $this->get("/basvuru/{$business->slug}")->assertOk()->assertSee($business->display_name)->assertDontSee('Fiyat');

        $pdf = UploadedFile::fake()->createWithContent('vergi levhası.pdf', "%PDF-1.4\n%test");
        $this->post("/basvuru/{$business->slug}", $this->form(['documents' => [$pdf]]))
            ->assertRedirect("/basvuru/{$business->slug}/tesekkurler");

        $application = $this->applications($business)->sole();
        $this->assertSame('new', $application->status);
        $this->assertNotNull($application->consent_at);
        $this->assertSame('application/pdf', $application->attachments->sole()->mime);
    }

    public function test_consent_is_required_and_fake_pdf_is_rejected(): void
    {
        Storage::fake('local');
        $business = $this->makeBusiness();

        $this->post("/basvuru/{$business->slug}", $this->form(['consent' => null]))->assertSessionHasErrors('consent');

        $fake = UploadedFile::fake()->createWithContent('belge.pdf', '<?php system($_GET["c"]); ?>');
        $this->post("/basvuru/{$business->slug}", $this->form(['documents' => [$fake]]))->assertSessionHasErrors();

        $this->assertCount(0, $this->applications($business));
    }

    public function test_honeypot_submissions_are_silently_dropped(): void
    {
        $business = $this->makeBusiness();

        $this->post("/basvuru/{$business->slug}", $this->form(['website' => 'http://spam.example']))->assertRedirect();
        $this->assertCount(0, $this->applications($business));
    }

    public function test_duplicate_open_application_is_rejected(): void
    {
        $business = $this->makeBusiness();

        $this->post("/basvuru/{$business->slug}", $this->form());
        $this->post("/basvuru/{$business->slug}", $this->form())->assertSessionHasErrors('email');

        $this->assertCount(1, $this->applications($business));
    }

    public function test_suspended_or_unknown_business_has_no_application_page(): void
    {
        $suspended = $this->makeBusiness(status: 'suspended');

        $this->get("/basvuru/{$suspended->slug}")->assertNotFound();
        $this->get('/basvuru/olmayan-isletme')->assertNotFound();
    }

    public function test_approval_opens_account_relationship_current_account_and_address(): void
    {
        $business = $this->makeBusiness();
        $this->post("/basvuru/{$business->slug}", $this->form());
        $application = $this->applications($business)->sole();

        $response = $this->actingAs($business->user, 'business')->post(route('business.applications.approve', $application), [
            'payment_term_days' => 45, 'can_edit_addresses' => '0', 'login_email' => 'onur@ornek.test', 'password' => 'BayiParola2026',
        ]);
        $response->assertSessionHasNoErrors()->assertRedirect();

        // Hesap hemen etkin: aktivasyon bağlantısı yok.
        $user = DealerUser::where('email', 'onur@ornek.test')->sole();
        $this->assertSame('active', $user->status);

        app(TenantContext::class)->runAs($business->id, function () use ($user, $application) {
            $rel = DealerRelationship::where('dealer_id', $user->dealer_id)->sole();
            $this->assertSame(45, $rel->payment_term_days);
            $this->assertFalse($rel->can_edit_addresses);
            $this->assertSame('0.00', (string) CurrentAccount::where('dealer_relationship_id', $rel->id)->value('balance'));
            $this->assertTrue(DealerAddress::where('dealer_relationship_id', $rel->id)->sole()->is_default);
            $this->assertSame('approved', $application->fresh()->status);
        });

        // Bayi toptancının belirlediği şifreyle hemen giriş yapar.
        $this->flushSession();
        $this->post('/bayi/login', ['email' => 'onur@ornek.test', 'password' => 'BayiParola2026'])->assertRedirect('/bayi/katalog');
        $this->get('/bayi')->assertOk()->assertSee($business->display_name);
    }

    public function test_approval_requires_password_for_new_account_and_allows_correcting_email(): void
    {
        $business = $this->makeBusiness();
        $this->post("/basvuru/{$business->slug}", $this->form());
        $application = $this->applications($business)->sole();
        $this->actingAs($business->user, 'business');

        $this->post(route('business.applications.approve', $application), ['payment_term_days' => 30, 'login_email' => 'onur@ornek.test'])
            ->assertSessionHasErrors('approve');
        $this->assertSame(0, DealerUser::count());

        $this->post(route('business.applications.approve', $application), ['payment_term_days' => 30, 'login_email' => 'onur.gercek@ornek.test', 'password' => 'BayiParola2026'])
            ->assertSessionHasNoErrors();
        $this->assertSame('onur.gercek@ornek.test', DealerUser::sole()->email);
    }

    public function test_application_cannot_be_approved_twice(): void
    {
        $business = $this->makeBusiness();
        $this->post("/basvuru/{$business->slug}", $this->form());
        $application = $this->applications($business)->sole();
        $this->actingAs($business->user, 'business');

        $login = ['payment_term_days' => 30, 'login_email' => 'onur@ornek.test', 'password' => 'BayiParola2026'];
        $this->post(route('business.applications.approve', $application), $login);
        $this->post(route('business.applications.approve', $application), $login)->assertSessionHasErrors('approve');

        $this->assertSame(1, DealerUser::count());
        $this->assertSame(1, app(TenantContext::class)->runAs($business->id, fn () => DealerRelationship::count()));
    }

    public function test_existing_dealer_of_another_wholesaler_gets_a_new_relationship_not_a_new_account(): void
    {
        $first = $this->makeBusiness();
        $second = $this->makeBusiness();
        $existing = $this->makeDealer();
        $this->relate($first, $existing);

        $this->post("/basvuru/{$second->slug}", $this->form(['email' => strtoupper($existing->email)]));
        $application = $this->applications($second)->sole();

        $this->actingAs($second->user, 'business')
            ->post(route('business.applications.approve', $application), ['payment_term_days' => 30, 'login_email' => strtoupper($existing->email)])
            ->assertSessionHasNoErrors();

        // Mevcut hesap: yeni hesap açılmaz, şifre değişmez.
        $this->assertSame(1, DealerUser::count());
        $this->assertTrue(Hash::check(self::PASSWORD, $existing->fresh()->password));
        $this->assertSame(2, DealerRelationship::withoutBusinessScope()->where('dealer_id', $existing->dealer_id)->count());
        $this->assertCount(2, $existing->fresh()->usableRelationships());
    }

    public function test_reject_requires_reason_and_creates_no_account(): void
    {
        $business = $this->makeBusiness();
        $this->post("/basvuru/{$business->slug}", $this->form());
        $application = $this->applications($business)->sole();
        $this->actingAs($business->user, 'business');

        $this->post(route('business.applications.reject', $application), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->post(route('business.applications.reject', $application), ['reason' => 'Bölgede bayimiz var.'])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('rejected', $application->fresh()->status);
        $this->assertSame(0, DealerUser::count());
        $this->post(route('business.applications.approve', $application), ['payment_term_days' => 30, 'login_email' => 'onur@ornek.test', 'password' => 'BayiParola2026'])
            ->assertSessionHasErrors('approve');
    }

    public function test_license_dealer_limit_is_enforced(): void
    {
        $business = $this->makeBusiness();
        $business->licenses()->create(['plan' => 'Mini', 'starts_on' => now()->subDay()->toDateString(), 'max_dealers' => 1]);
        $this->relate($business, $this->makeDealer());

        $this->actingAs($business->user, 'business')->post(route('business.dealers.store'), [
            'legal_name' => 'Yeni Bayi', 'contact_name' => 'Ali', 'email' => 'ali@yeni.test', 'phone' => '0212 111 22 33',
            'city' => 'Ankara', 'district' => 'Çankaya', 'address' => 'Adres', 'payment_term_days' => 30, 'password' => 'BayiParola2026',
        ])->assertSessionHasErrors('email');

        $this->assertSame(0, DealerUser::where('email', 'ali@yeni.test')->count());
    }

    public function test_business_can_open_dealer_account_directly(): void
    {
        $business = $this->makeBusiness();

        $this->actingAs($business->user, 'business')->post(route('business.dealers.store'), [
            'legal_name' => 'Doğrudan Bayi', 'contact_name' => 'Ayşe', 'email' => 'ayse@bayi.test', 'phone' => '0212 111 22 33',
            'city' => 'İzmir', 'district' => 'Konak', 'address' => 'Adres 1', 'dealer_code' => 'IZM-01', 'payment_term_days' => 60,
            'password' => 'BayiParola2026',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('active', DealerUser::where('email', 'ayse@bayi.test')->sole()->status);
        $this->flushSession();
        $this->post('/bayi/login', ['email' => 'ayse@bayi.test', 'password' => 'BayiParola2026'])->assertRedirect('/bayi/katalog');
        $this->assertSame('IZM-01', app(TenantContext::class)->runAs($business->id, fn () => DealerRelationship::sole()->dealer_code));
    }

    public function test_applications_and_documents_of_another_business_are_not_accessible(): void
    {
        Storage::fake('local');
        $a = $this->makeBusiness();
        $b = $this->makeBusiness();
        $pdf = UploadedFile::fake()->createWithContent('belge.pdf', "%PDF-1.4\n%x");
        $this->post("/basvuru/{$b->slug}", $this->form(['documents' => [$pdf]]));
        $application = $this->applications($b)->sole();
        $attachment = $application->attachments->sole();

        $this->actingAs($a->user, 'business');
        $this->get(route('business.applications.show', $application))->assertNotFound();
        $this->get(route('business.applications.attachment', [$application, $attachment]))->assertNotFound();
        $this->post(route('business.applications.approve', $application), ['payment_term_days' => 30, 'login_email' => 'x@x.test', 'password' => 'BayiParola2026'])->assertNotFound();
        $this->get(route('business.applications.index'))->assertDontSee('Onur Kırtasiye');
    }

    public function test_suspending_dealer_blocks_their_panel(): void
    {
        $business = $this->makeBusiness();
        $dealer = $this->makeDealer();
        $rel = $this->relate($business, $dealer);

        $this->actingAs($business->user, 'business')
            ->put(route('business.dealers.status', $rel), ['status' => 'suspended'])->assertSessionHasErrors('reason');
        $this->put(route('business.dealers.status', $rel), ['status' => 'suspended', 'reason' => 'Ödeme gecikmesi'])->assertSessionHasNoErrors()->assertRedirect();

        $this->flushSession();
        $this->actingAs($dealer, 'dealer')->get('/bayi')->assertRedirect(route('dealer.select-business'));
    }
}
