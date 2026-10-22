<?php

namespace Tests\Feature;

use App\Domain\Platform\BackupService;
use App\Domain\Platform\ErrorRecorder;
use App\Domain\Tenancy\TenantContext;
use App\Http\Middleware\RequirePlatformTwoFactor;
use App\Models\AuditLog;
use App\Models\DealerApplication;
use App\Models\EmailLog;
use App\Models\ErrorEvent;
use App\Models\SavedReport;
use App\Models\SupportAccessGrant;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/** B2B eksikleri: hata kayıtları, yedekler, destek erişimi, başvuruya web yanıtı, kayıtlı rapor filtreleri. */
class B2bLeftoversTest extends TestCase
{
    use RefreshDatabase;

    public function test_errors_are_grouped_without_personal_data_and_can_be_resolved(): void
    {
        $recorder = app(ErrorRecorder::class);
        $make = fn () => new RuntimeException('Beklenmeyen durum');
        $e = $make();
        $recorder->record($e);
        $recorder->record($e);
        $recorder->record(new QueryException('mysql', "insert into users (email) values ('gizli@ornek.com')", [], new RuntimeException('Duplicate entry')));

        $this->assertSame(2, ErrorEvent::count());
        $grouped = ErrorEvent::where('exception_class', RuntimeException::class)->firstOrFail();
        $this->assertSame(2, $grouped->occurrences);
        $this->assertStringStartsWith('tests/Feature/B2bLeftoversTest.php:', $grouped->location, 'proje köküne göreli yol');
        $query = ErrorEvent::where('exception_class', QueryException::class)->firstOrFail();
        $this->assertStringNotContainsString('gizli@ornek.com', $query->message, 'SQL değerleri yazılmaz');

        // Gerçek istekte oluşan hata da yakalanır (rapor kancası).
        $this->actingAsPlatformAdmin();
        $this->get(route('platform.errors'))->assertOk()->assertSee('Beklenmeyen durum')->assertSee('2</span> kez', false);
        $this->post(route('platform.errors.resolve', $grouped))->assertSessionHasNoErrors();
        $this->assertNotNull($grouped->fresh()->resolved_at);
        $recorder->record($e);
        $this->assertNull($grouped->fresh()->resolved_at, 'tekrar eden çözülmüş hata yeniden açılır');
        $this->assertSame(3, $grouped->fresh()->occurrences);

        app(ExceptionHandler::class)->report(new RuntimeException('Rapor kancası'));
        $this->assertTrue(ErrorEvent::where('message', 'Rapor kancası')->exists());
    }

    public function test_backups_dump_database_and_files_and_are_listed(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('businesses/1/belge.pdf', '%PDF-1.4 deneme');
        Process::fake(function (PendingProcess $process) {
            $resultFile = collect($process->command)->first(fn ($a) => str_starts_with($a, '--result-file='));
            file_put_contents(substr($resultFile, 14), "CREATE TABLE `orders` (id int);\n");

            return Process::result('');
        });
        config(['b2b.backup.keep' => 2]);

        $service = app(BackupService::class);
        $name = $service->create();
        $this->assertMatchesRegularExpression('/^yedek-\d{8}-\d{6}\.zip$/', $name);
        Process::assertRan(fn (PendingProcess $p) => in_array('--single-transaction', $p->command, true)
            && ! collect($p->command)->contains(fn ($a) => str_contains($a, (string) config('database.connections.mysql.password')) && config('database.connections.mysql.password') !== ''));

        $zip = new \ZipArchive;
        $zip->open(Storage::disk('local')->path('backups/'.$name));
        $this->assertSame("CREATE TABLE `orders` (id int);\n", gzdecode($zip->getFromName('veritabani.sql.gz')));
        $this->assertSame('%PDF-1.4 deneme', $zip->getFromName('dosyalar/businesses/1/belge.pdf'));
        $zip->close();

        // Saklama: son 2 yedek kalır.
        foreach (['yedek-20200101-000000.zip', 'yedek-20200102-000000.zip'] as $old) {
            Storage::disk('local')->put('backups/'.$old, 'x');
        }
        $service->create();
        $this->assertCount(2, $service->list());

        $admin = $this->actingAsPlatformAdmin();
        $this->get(route('platform.backups.index'))->assertOk()->assertSee($name);
        $this->get(route('platform.backups.download', $name))->assertOk();
        $this->get('/platform/yedekler/..%2F.env')->assertNotFound();
        $this->delete(route('platform.backups.destroy', $name))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('audit_logs', ['action' => 'platform.backup_deleted', 'actor_id' => $admin->id]);

        // Döküm başarısızsa yarım yedek kalmaz.
        Process::fake(fn () => Process::result('', 'Access denied', 2));
        $before = count($service->list());
        $this->expectException(\DomainException::class);
        try {
            $service->create();
        } finally {
            $this->assertCount($before, $service->list());
        }
    }

    public function test_backups_in_the_same_second_do_not_overwrite_each_other(): void
    {
        Storage::fake('local');
        Process::fake(function (PendingProcess $process) {
            $resultFile = collect($process->command)->first(fn ($a) => str_starts_with($a, '--result-file='));
            file_put_contents(substr($resultFile, 14), 'SELECT 1;');

            return Process::result('');
        });
        $this->travelTo(now()->startOfSecond());
        $service = app(BackupService::class);

        $first = $service->create();
        $second = $service->create();
        $this->assertNotSame($first, $second);
        $this->assertSame([$second, $first], array_column($service->list(), 'name'), 'en yeni önce');

        // Aynı saniyede başarısız olan yedek, var olan yedeği silmez.
        Process::fake(fn () => Process::result('', 'Access denied', 2));
        try {
            $service->create();
        } catch (\DomainException) {
        }
        $this->assertSame([$second, $first], array_column($service->list(), 'name'));
    }

    public function test_support_access_grant_view_and_revoke(): void
    {
        $business = $this->makeBusiness();
        $dealer = $this->makeDealer();
        $this->relate($business, $dealer);
        $admin = $this->makePlatformAdmin();
        $other = $this->makePlatformAdmin();

        // Toptancı erişim verir.
        $this->actingAs($business->user, 'business');
        $this->get(route('business.support-access.index'))->assertOk()->assertSee($admin->name);
        $this->post(route('business.support-access.store'), ['platform_admin_id' => $admin->id, 'hours' => 5, 'reason' => 'Hata incelemesi'])->assertSessionHasErrors('hours');
        $this->post(route('business.support-access.store'), ['platform_admin_id' => $admin->id, 'hours' => 4, 'reason' => 'Sipariş ekranı incelemesi'])->assertSessionHasNoErrors();
        $grant = SupportAccessGrant::sole();
        $this->assertTrue($grant->expires_at->between(now()->addHours(4)->subMinute(), now()->addHours(4)->addMinute()));
        $this->post(route('business.support-access.store'), ['platform_admin_id' => $admin->id, 'hours' => 1, 'reason' => 'İkinci erişim'])->assertSessionHasErrors('platform_admin_id');

        // Erişimi olan yönetici salt okunur görür; olmayan göremez.
        auth('business')->logout();
        $this->flushSession();
        $this->actingAs($other, 'platform')->withSession([RequirePlatformTwoFactor::SESSION_KEY => true]);
        $other->forceFill(['two_factor_secret' => 'x', 'two_factor_confirmed_at' => now()])->save();
        $this->get(route('platform.businesses.support', [$business, 'dealers']))->assertForbidden();

        auth('platform')->logout();
        $this->flushSession();
        $admin->forceFill(['two_factor_secret' => 'x', 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($admin, 'platform')->withSession([RequirePlatformTwoFactor::SESSION_KEY => true]);
        $this->get(route('platform.businesses.show', $business))->assertOk()->assertSee('Destek görünümünü aç');
        $this->get(route('platform.businesses.support', [$business, 'dealers']))->assertOk()->assertSee($dealer->dealer->legal_name);
        $this->get(route('platform.businesses.support', [$business, 'orders']))->assertOk();
        $this->get(route('platform.businesses.support', [$business, 'gizli']))->assertNotFound();
        $this->assertSame(2, AuditLog::where('business_id', $business->id)->where('action', 'support.viewed')->count());

        // Toptancı kayıtları görür ve erişimi kaldırır.
        auth('platform')->logout();
        $this->flushSession();
        $this->actingAs($business->user, 'business');
        $this->get(route('business.support-access.index'))->assertSee('Görüntüledi: Bayiler');
        $this->delete(route('business.support-access.destroy', $grant->id))->assertSessionHasNoErrors();
        $this->assertNotNull($grant->fresh()->revoked_at);

        auth('business')->logout();
        $this->flushSession();
        $this->actingAs($admin, 'platform')->withSession([RequirePlatformTwoFactor::SESSION_KEY => true]);
        $this->get(route('platform.businesses.support', [$business, 'dealers']))->assertForbidden();
    }

    public function test_applicant_replies_to_info_request_via_single_use_link(): void
    {
        Storage::fake('local');
        $business = $this->makeBusiness();
        $application = app(TenantContext::class)->runAs($business->id, fn () => DealerApplication::create([
            'legal_name' => 'Yeni Kırtasiye', 'contact_name' => 'Ali Veli', 'phone' => '0532 000 00 00', 'email' => 'ali@ornek.com',
            'city' => 'Eskişehir', 'district' => 'Tepebaşı', 'address' => 'Adres 1', 'status' => 'new',
            'consent_text_version' => '1', 'consent_at' => now(),
        ]));

        $this->actingAs($business->user, 'business');
        $this->post(route('business.applications.request-info', $application), ['note' => 'Vergi levhanızı yükleyin.'])->assertRedirect();
        $application->refresh();
        $this->assertSame('waiting_info', $application->status);
        $url = EmailLog::withoutBusinessScope()->where('template_key', 'application_info_requested')->latest('id')->value('payload')['action_url'];
        $this->assertStringContainsString('/basvuru-yanit/', $url);
        $this->assertSame(hash('sha256', basename($url)), $application->reply_token_hash, 'yalnızca özet saklanır');

        auth('business')->logout();
        $this->flushSession();
        $this->get($url)->assertOk()->assertSee('Vergi levhanızı yükleyin.');
        $this->get('/basvuru-yanit/'.str_repeat('a', 48))->assertOk()->assertSee('Bağlantı geçersiz');
        $this->post($url, ['message' => ''])->assertSessionHasErrors('message');
        $this->post($url, ['message' => 'Vergi levhası ekte.', 'documents' => [UploadedFile::fake()->createWithContent('levha.pdf', '%PDF-1.4 x')]])
            ->assertRedirect(route('public.application.thanks', $business->slug));

        $application->refresh();
        $this->assertSame(['in_review', 'Vergi levhası ekte.', null], [$application->status, $application->info_reply, $application->reply_token_hash]);
        $this->assertSame(1, app(TenantContext::class)->runAs($business->id, fn () => $application->attachments()->count()));
        $this->get($url)->assertSee('Bağlantı geçersiz', false); // tek kullanımlık
        $this->post($url, ['message' => 'Tekrar'])->assertNotFound();

        $this->actingAs($business->user, 'business');
        $this->get(route('business.applications.show', $application))->assertOk()->assertSee('Başvuranın yanıtı')->assertSee('Vergi levhası ekte.');
        $this->assertDatabaseHas('audit_logs', ['action' => 'dealer_application.info_replied', 'entity_id' => $application->id]);

        // Süresi dolmuş bağlantı
        $this->post(route('business.applications.request-info', $application), ['note' => 'İmza sirküleri']);
        $application->refresh()->forceFill(['reply_expires_at' => now()->subMinute()])->save();
        $url2 = EmailLog::withoutBusinessScope()->where('template_key', 'application_info_requested')->latest('id')->value('payload')['action_url'];
        auth('business')->logout();
        $this->flushSession();
        $this->get($url2)->assertSee('Bağlantı geçersiz');
    }

    public function test_saved_report_filters_are_private_to_their_owner(): void
    {
        $business = $this->makeBusiness();
        $this->actingAs($business->user, 'business');
        $key = 'collections'; // tarih aralığı filtreli rapor

        $this->post(route('business.reports.save', $key), ['name' => 'Ekim', 'query' => json_encode(['from' => '2026-10-01', 'to' => '2026-10-31', 'page' => 3, 'yok' => 'x'])])
            ->assertSessionHasNoErrors();
        $saved = SavedReport::withoutBusinessScope()->sole();
        $this->assertEquals(['from' => '2026-10-01', 'to' => '2026-10-31'], $saved->filters);
        $this->assertArrayNotHasKey('page', $saved->filters);
        $this->assertArrayNotHasKey('yok', $saved->filters);
        $this->post(route('business.reports.save', $key), ['name' => 'Bozuk', 'query' => json_encode(['from' => 'dün'])])->assertSessionHasErrors();

        $this->get(route('business.reports.index'))->assertOk()->assertSee('Kayıtlı raporlarım')->assertSee('Ekim');
        $this->get(route('business.reports.show', $key))->assertOk()->assertSee('Bu filtreleri kaydet');

        // Başka işletmenin kullanıcısı göremez, silemez.
        $other = $this->makeBusiness();
        auth('business')->logout();
        $this->flushSession();
        $this->actingAs($other->user, 'business');
        $this->get(route('business.reports.index'))->assertDontSee('Kayıtlı raporlarım');
        $this->delete(route('business.reports.saved.destroy', $saved->id))->assertNotFound();

        auth('business')->logout();
        $this->flushSession();
        $this->actingAs($business->user, 'business');
        $this->delete(route('business.reports.saved.destroy', $saved->id))->assertSessionHasNoErrors();
        $this->assertSame(0, SavedReport::withoutBusinessScope()->count());
    }

    public function test_dark_theme_is_rendered_server_side_from_cookie(): void
    {
        $business = $this->makeBusiness();
        $this->actingAs($business->user, 'business');

        $this->get(route('business.dashboard'))->assertOk()->assertSee('<html lang="tr" class="">', false)->assertSee('x-data="themeSwitch"', false);
        $this->withUnencryptedCookie('tema', 'dark')->get(route('business.dashboard'))->assertSee('<html lang="tr" class="dark">', false);
        $this->withUnencryptedCookie('tema', 'auto')->get(route('business.dashboard'))->assertDontSee('class="dark"', false); // sistem: tarayıcıda uygulanır
        $this->withUnencryptedCookie('tema', '"><script>')->get(route('business.dashboard'))->assertSee('<html lang="tr" class="">', false);
    }
}
