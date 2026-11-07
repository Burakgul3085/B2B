<?php

namespace Tests\Feature;

use App\Domain\Mail\SecretBox;
use App\Domain\Tenancy\TenantContext;
use App\Models\AuthToken;
use App\Models\BusinessUser;
use App\Models\EmailSetting;
use App\Models\PlatformAdmin;
use App\Models\Store\StoreAdmin;
use App\Models\Store\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/** yapboz:hesaplari-ayarla: yalnızca üç giriş hesabı ve e-posta ayarları değişir; ödeme, fatura ve mağaza bilgileri değişmez. */
class SetupYapbozAccountsTest extends TestCase
{
    use RefreshDatabase;

    private const NEW_PASSWORD = 'YeniPanelSifre2026';

    private const APP_PASSWORD = 'abcd efgh ijkl mnop';

    private string $env;

    protected function setUp(): void
    {
        parent::setUp();
        $this->env = sys_get_temp_dir().'/yapboz-env-'.Str::random(8);
        file_put_contents($this->env, "APP_NAME=Yapboz\nSYSTEM_MAIL_HOST=eski.host\nSYSTEM_MAIL_USERNAME=eski@ornek.com\nIYZICO_X=dokunma\n");
    }

    protected function tearDown(): void
    {
        foreach (glob($this->env.'*') ?: [] as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    private function run_(array $answers, bool $confirm = true)
    {
        $command = $this->artisan('yapboz:hesaplari-ayarla', ['--no-backup' => true, '--no-test-mail' => true, '--env-file' => $this->env]);
        foreach ($answers as $question => $answer) {
            $command->expectsQuestion($question, $answer);
        }
        if ($confirm !== null) {
            $command->expectsConfirmation('Yukarıdaki alanlar güncellensin mi? (Önce yedek alınır)', $confirm ? 'yes' : 'no');
        }

        return $command;
    }

    private function answers(string $password = self::NEW_PASSWORD, string $app = self::APP_PASSWORD): array
    {
        return [
            'Yeni giriş şifresi (üç panel için aynı)' => $password,
            'Yeni giriş şifresi (tekrar)' => $password,
            'Gmail uygulama şifresi (16 harf; boşluklar önemsiz)' => $app,
        ];
    }

    /** @return array{0: PlatformAdmin, 1: BusinessUser, 2: StoreAdmin, 3: StoreAdmin} */
    private function world(): array
    {
        $business = $this->makeBusiness();
        $platform = $this->makePlatformAdmin();
        $super = StoreAdmin::create(['name' => 'Süper', 'email' => 'eski@magaza.test', 'password' => self::PASSWORD, 'is_super' => true, 'is_active' => true]);
        $admin = StoreAdmin::create(['name' => 'Yönetici', 'email' => 'calisan@magaza.test', 'password' => self::PASSWORD, 'is_super' => false, 'is_active' => true]);
        DB::table('invoice_settings')->insert(['business_id' => $business->id, 'driver' => 'uyumsoft', 'uyumsoft_mode' => 'live', 'uyumsoft_username' => 'Kullanici_WebServis',
            'series_earsiv' => 'BYA', 'series_efatura' => 'BYF', 'delivery_vat' => '20.00', 'created_at' => now(), 'updated_at' => now()]);
        StoreSetting::put(['iyzico_mode' => 'live', 'invoice_driver' => 'uyumsoft', 'email' => 'iletisim@yapboz.test', 'address' => 'Eskişehir',
            'mail_username' => 'eski@gmail.com', 'notify_emails' => 'eski@gmail.com']);
        StoreSetting::putSecret('iyzico_secret_key', 'gizli-iyzico');
        StoreSetting::putSecret('uyumsoft_password', 'gizli-uyumsoft');
        app(TenantContext::class)->runAs($business->id, fn () => EmailSetting::create([
            'from_email' => 'eski@gmail.com', 'from_name' => 'Yapboz Kırtasiye', 'app_password_encrypted' => app(SecretBox::class)->encrypt('eskieskieskieski'),
            'payment_reminders' => true, 'status' => 'verified',
        ]));
        AuthToken::create(['guard' => 'business', 'user_id' => $business->user->id, 'purpose' => 'password_reset', 'token_hash' => Str::random(64), 'expires_at' => now()->addHour()]);

        return [$platform, $business->user, $super, $admin];
    }

    public function test_sets_all_accounts_and_mailers_and_keeps_everything_else(): void
    {
        [$platform, $businessUser, $super, $admin] = $this->world();
        $protected = ['invoice_settings', 'businesses', 'business_settings', 'store_products', 'products', 'orders', 'store_orders', 'dealers'];
        $snapshot = fn () => collect($protected)->mapWithKeys(fn ($t) => [$t => DB::table($t)->get()->toJson()])->all();
        $before = $snapshot();
        $storeBefore = DB::table('store_settings')->whereNotIn('key', ['mail_username', 'mail_from_address', 'mail_password', 'mail_host', 'mail_port', 'mail_encryption', 'notify_emails'])
            ->orderBy('key')->pluck('value', 'key')->all();

        $this->run_($this->answers())->assertSuccessful();

        foreach ([$platform, $businessUser, $super] as $account) {
            $account->refresh();
            $this->assertSame('yapboz2016@gmail.com', $account->email);
            $this->assertTrue(Hash::check(self::NEW_PASSWORD, $account->password));
        }
        $this->assertSame('calisan@magaza.test', $admin->fresh()->email, 'diğer mağaza yöneticisi değişmez');
        $this->assertTrue(Hash::check(self::PASSWORD, $admin->fresh()->password));
        $this->assertSame(0, AuthToken::where('guard', 'business')->where('user_id', $businessUser->id)->count());

        $setting = EmailSetting::withoutBusinessScope()->first();
        $this->assertSame(['yapboz2016@gmail.com', 'Yapboz Kırtasiye', true], [$setting->from_email, $setting->from_name, $setting->payment_reminders]);
        $this->assertSame('abcdefghijklmnop', app(SecretBox::class)->decrypt($setting->app_password_encrypted));

        $this->assertSame('yapboz2016@gmail.com', StoreSetting::get('mail_username'));
        $this->assertSame('yapboz2016@gmail.com', StoreSetting::get('mail_from_address'));
        $this->assertSame('yapboz2016@gmail.com', StoreSetting::get('notify_emails'));
        $this->assertSame('smtp.gmail.com', StoreSetting::get('mail_host'));
        $this->assertSame('abcdefghijklmnop', StoreSetting::secret('mail_password'));

        // Ödeme, fatura, mağaza ve işletme bilgileri birebir aynı.
        $this->assertSame($before, $snapshot());
        $this->assertSame($storeBefore, DB::table('store_settings')->whereIn('key', array_keys($storeBefore))->orderBy('key')->pluck('value', 'key')->all());
        $this->assertSame('gizli-iyzico', StoreSetting::secret('iyzico_secret_key'));
        $this->assertSame('gizli-uyumsoft', StoreSetting::secret('uyumsoft_password'));

        $env = file_get_contents($this->env);
        foreach (['SYSTEM_MAIL_HOST=smtp.gmail.com', 'SYSTEM_MAIL_PORT=587', 'SYSTEM_MAIL_USERNAME=yapboz2016@gmail.com',
            'SYSTEM_MAIL_PASSWORD=abcdefghijklmnop', 'SYSTEM_MAIL_FROM_ADDRESS=yapboz2016@gmail.com', 'APP_NAME=Yapboz', 'IYZICO_X=dokunma'] as $line) {
            $this->assertStringContainsString($line."\n", $env);
        }
        $this->assertStringNotContainsString('eski.host', $env);
        $this->assertCount(1, glob($this->env.'.yedek-*'), '.env önce kopyalanır');

        // Denetim kaydında şifre yoktur.
        $log = DB::table('audit_logs')->where('action', 'system.accounts_unified')->first();
        $this->assertNotNull($log);
        $this->assertStringNotContainsString(self::NEW_PASSWORD, (string) $log->new_values);
        $this->assertStringNotContainsString('abcdefghijklmnop', DB::table('audit_logs')->get()->toJson());
    }

    public function test_nothing_changes_on_bad_password_bad_app_password_or_cancel(): void
    {
        [$platform, $businessUser, $super] = $this->world();
        $state = fn () => [$platform->fresh()->email, $businessUser->fresh()->email, $super->fresh()->email, StoreSetting::get('mail_username'),
            EmailSetting::withoutBusinessScope()->value('from_email'), file_get_contents($this->env)];
        $before = $state();

        $this->artisan('yapboz:hesaplari-ayarla', ['--no-backup' => true, '--no-test-mail' => true, '--env-file' => $this->env])
            ->expectsQuestion('Yeni giriş şifresi (üç panel için aynı)', 'kisa')
            ->expectsQuestion('Yeni giriş şifresi (tekrar)', 'kisa')
            ->assertFailed();
        $this->artisan('yapboz:hesaplari-ayarla', ['--no-backup' => true, '--no-test-mail' => true, '--env-file' => $this->env])
            ->expectsQuestion('Yeni giriş şifresi (üç panel için aynı)', self::NEW_PASSWORD)
            ->expectsQuestion('Yeni giriş şifresi (tekrar)', self::NEW_PASSWORD)
            ->expectsQuestion('Gmail uygulama şifresi (16 harf; boşluklar önemsiz)', '1234')
            ->assertFailed();
        $this->run_($this->answers(), false)->assertSuccessful();

        $this->assertSame($before, $state());
        $this->assertTrue(Hash::check(self::PASSWORD, $platform->fresh()->password));
        $this->assertSame([], glob($this->env.'.yedek-*'));
    }

    public function test_refuses_when_email_belongs_to_another_account(): void
    {
        $this->world();
        StoreAdmin::create(['name' => 'Başka', 'email' => 'yapboz2016@gmail.com', 'password' => self::PASSWORD, 'is_super' => false, 'is_active' => true]);

        $this->artisan('yapboz:hesaplari-ayarla', ['--no-backup' => true, '--no-test-mail' => true, '--env-file' => $this->env])
            ->expectsOutputToContain('başka bir hesapta kullanılıyor')
            ->assertFailed();
        $this->assertSame(1, StoreAdmin::where('email', 'yapboz2016@gmail.com')->count());
    }
}
