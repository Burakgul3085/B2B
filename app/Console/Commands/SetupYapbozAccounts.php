<?php

namespace App\Console\Commands;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\Area;
use App\Domain\Mail\EmailSettingService;
use App\Domain\Mail\MailFailure;
use App\Domain\Mail\MailTransport;
use App\Domain\Mail\SystemMailer;
use App\Domain\Platform\BackupService;
use App\Domain\Store\Mail\StoreMailer;
use App\Domain\Tenancy\TenantContext;
use App\Models\AuthToken;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\EmailSetting;
use App\Models\PlatformAdmin;
use App\Models\Store\StoreAdmin;
use App\Models\Store\StoreSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Throwable;

/**
 * Teslim için tek yetkili hesap: platform, B2B toptancı ve mağaza süper admin girişleri ile bütün e-posta gönderimi
 * (B2B e-posta ayarı, mağaza e-posta ayarı + bildirim adresi, sunucunun sistem e-postası) tek adrese ayarlanır.
 *
 * Şifreler ekranda görünmeden sorulur; komut satırına, loga veya denetim kaydına yazılmaz. Yalnızca aşağıdaki alanlar
 * değişir; ödeme (iyzico), fatura (Uyumsoft, seriler), mağaza/işletme bilgileri ve hiçbir iş verisi değişmez, silinmez.
 *  - platform_admins / business_users / store_admins: seçilen tek hesabın e-postası ve şifresi (oturumlar ve bağlantılar düşer)
 *  - email_settings: gönderen adres + uygulama şifresi (görünen ad ve ödeme hatırlatma tercihi korunur)
 *  - store_settings: mail_username, mail_from_address, mail_password, mail_host/port/encryption (adresten bulunur), notify_emails
 *  - .env: yalnızca SYSTEM_MAIL_HOST, _PORT, _USERNAME, _PASSWORD, _FROM_ADDRESS (önce kopyası alınır)
 */
class SetupYapbozAccounts extends Command
{
    protected $signature = 'yapboz:hesaplari-ayarla
        {--email=yapboz2016@gmail.com : Tek yetkili e-posta adresi (Gmail)}
        {--no-backup : Önce veritabanı yedeği alma (yalnızca testte)}
        {--no-test-mail : Sonunda deneme e-postası gönderme}
        {--env-file= : .env dosyasının yolu (yalnızca testte)}';

    protected $description = 'Platform, B2B toptancı ve mağaza yönetimi girişlerini ve tüm e-posta ayarlarını tek Gmail hesabına ayarlar';

    public function handle(AuditLogger $audit, BackupService $backups, EmailSettingService $emailSettings, TenantContext $tenant): int
    {
        $email = Str::lower(trim((string) $this->option('email')));
        if (Validator::make(['e' => $email], ['e' => ['required', 'email']])->fails() || ! str_ends_with($email, '@gmail.com')) {
            $this->error('Geçerli bir Gmail adresi verin (ör. yapboz2016@gmail.com).');

            return self::FAILURE;
        }

        $business = Business::orderBy('id')->first();
        $businessUser = $business ? BusinessUser::where('business_id', $business->id)->first() : null;
        if (! $business || ! $businessUser) {
            $this->error('İşletme veya toptancı kullanıcısı bulunamadı.');

            return self::FAILURE;
        }
        $platform = $this->pick('Platform yöneticisi', PlatformAdmin::orderBy('id')->get());
        $store = $this->pick('Mağaza süper admini', StoreAdmin::where('is_super', true)->orderByDesc('is_active')->orderBy('id')->get());
        if (! $platform || ! $store) {
            return self::FAILURE;
        }

        $taken = array_filter([
            'platform' => PlatformAdmin::where('email', $email)->whereKeyNot($platform->id)->exists(),
            'toptancı' => BusinessUser::where('email', $email)->whereKeyNot($businessUser->id)->exists(),
            'mağaza' => StoreAdmin::where('email', $email)->whereKeyNot($store->id)->exists(),
        ]);
        if ($taken) {
            $this->error("{$email} şu alanda başka bir hesapta kullanılıyor: ".implode(', ', array_keys($taken)).'. Önce o hesabı panelden silin veya e-postasını değiştirin.');

            return self::FAILURE;
        }

        $envPath = (string) ($this->option('env-file') ?: base_path('.env'));
        $envReal = realpath($envPath) ?: $envPath;
        if (! is_file($envReal) || ! is_writable($envReal) || ! is_writable(dirname($envReal))) {
            // Veritabanına dokunmadan önce: yarım iş kalmasın.
            $this->error(".env dosyasına yazılamıyor ({$envReal}). Komutu dosyanın sahibi olan kullanıcıyla çalıştırın. Hiçbir şey değiştirilmedi.");

            return self::FAILURE;
        }
        $this->line('Değişecekler:');
        $this->table(['Alan', 'Şu an', 'Yeni'], [
            ['Platform girişi', $platform->email, "{$email} + yeni şifre"],
            ['B2B toptancı girişi', $businessUser->email, "{$email} + yeni şifre"],
            ['Mağaza yönetimi girişi (süper admin)', $store->email, "{$email} + yeni şifre"],
            ['B2B e-posta ayarı', EmailSetting::withoutBusinessScope()->where('business_id', $business->id)->value('from_email') ?? '—', "{$email} + uygulama şifresi"],
            ['Mağaza e-posta ayarı', StoreSetting::get('mail_username') ?? '—', "{$email} + uygulama şifresi"],
            ['Mağaza bildirim adresi', StoreSetting::get('notify_emails') ?? '—', $email],
            ['Sistem e-postası (.env SYSTEM_MAIL_*)', (string) config('b2b.system_mail.username') ?: '—', "{$email} + uygulama şifresi"],
        ]);
        $this->line('Değişmeyecekler: iyzico, Uyumsoft ve fatura ayarları, mağaza ve işletme bilgileri, ürün, stok, sipariş, fatura, müşteri, bayi kayıtları.');

        $password = $this->askPassword();
        if ($password === null) {
            return self::FAILURE;
        }
        $appPassword = preg_replace('/\s+/', '', (string) $this->secret('Gmail uygulama şifresi (16 harf; boşluklar önemsiz)'));
        if (! preg_match('/^[a-zA-Z]{16}$/', $appPassword)) {
            $this->error('Uygulama şifresi 16 harften oluşmalıdır. Hiçbir şey değiştirilmedi.');

            return self::FAILURE;
        }

        if (! $this->confirm('Yukarıdaki alanlar güncellensin mi? (Önce yedek alınır)', false)) {
            $this->info('Vazgeçildi; hiçbir şey değiştirilmedi.');

            return self::SUCCESS;
        }

        if (! $this->option('no-backup')) {
            $this->info('Yedek alındı: '.$backups->create());
        }

        DB::transaction(function () use ($email, $password, $appPassword, $platform, $businessUser, $store, $business, $emailSettings, $tenant, $audit) {
            foreach ([[Area::Platform, $platform], [Area::Business, $businessUser], [Area::StoreAdmin, $store]] as [$area, $account]) {
                $account->forceFill(['email' => $email, 'password' => $password, 'remember_token' => Str::random(60)])->save();
                AuthToken::where('guard', $area->value)->where('user_id', $account->id)->delete();
            }

            $tenant->runAs($business->id, function () use ($emailSettings, $email, $appPassword) {
                $current = EmailSetting::first();
                $emailSettings->save(['from_email' => $email, 'from_name' => $current?->from_name, 'app_password' => $appPassword,
                    'payment_reminders' => (bool) $current?->payment_reminders]);
            });

            [$host, $port, $encryption] = StoreMailer::detect($email);
            StoreSetting::put(['mail_username' => $email, 'mail_from_address' => $email, 'mail_host' => $host, 'mail_port' => (string) $port,
                'mail_encryption' => $encryption, 'notify_emails' => $email]);
            StoreSetting::putSecret('mail_password', $appPassword);

            $audit->log('system.accounts_unified', null, [], ['email' => $email, 'platform_admin_id' => $platform->id,
                'business_user_id' => $businessUser->id, 'store_admin_id' => $store->id]);
        });
        $this->info('Girişler ve e-posta ayarları güncellendi. Açık oturumlar kapandı; yeni şifreyle giriş yapın.');

        $envOk = $this->writeEnv($envPath, [
            'SYSTEM_MAIL_HOST' => 'smtp.gmail.com', 'SYSTEM_MAIL_PORT' => '587', 'SYSTEM_MAIL_USERNAME' => $email,
            'SYSTEM_MAIL_PASSWORD' => $appPassword, 'SYSTEM_MAIL_FROM_ADDRESS' => $email,
        ]);
        config(['b2b.system_mail' => ['host' => 'smtp.gmail.com', 'port' => 587, 'username' => $email, 'password' => $appPassword,
            'from_address' => $email] + (array) config('b2b.system_mail')]);
        if ($envOk && ! $this->option('env-file') && app()->configurationIsCached()) {
            Artisan::call('config:cache');
            $this->info('Ayar önbelleği yenilendi.');
        }

        if (! $this->option('no-test-mail')) {
            $this->sendTestMails($email, $business, $tenant, $emailSettings);
        }

        return $envOk ? self::SUCCESS : self::FAILURE;
    }

    /** Tek kayıt varsa onu, birden fazlaysa sorarak seçer. */
    private function pick(string $label, $accounts): PlatformAdmin|StoreAdmin|null
    {
        if ($accounts->isEmpty()) {
            $this->error("{$label} hesabı bulunamadı.");

            return null;
        }
        if ($accounts->count() === 1) {
            return $accounts->first();
        }
        $chosen = $this->choice("{$label}: hangi hesap güncellensin? (diğerleri değişmez)", $accounts->pluck('email')->all());

        return $accounts->firstWhere('email', $chosen);
    }

    private function askPassword(): ?string
    {
        $password = (string) $this->secret('Yeni giriş şifresi (üç panel için aynı)');
        $confirmation = (string) $this->secret('Yeni giriş şifresi (tekrar)');
        $validator = Validator::make(['password' => $password, 'password_confirmation' => $confirmation],
            ['password' => ['required', 'string', 'confirmed', Password::defaults()]], [], ['password' => 'şifre']);
        if ($validator->fails()) {
            $this->error($validator->errors()->first().' Hiçbir şey değiştirilmedi.');

            return null;
        }

        return $password;
    }

    /** .env'de yalnızca verilen satırları değiştirir (yoksa ekler); önce kopyasını alır. */
    private function writeEnv(string $path, array $values): bool
    {
        $real = realpath($path) ?: $path;
        $content = is_file($real) ? (string) file_get_contents($real) : '';
        if (! is_writable(is_file($real) ? $real : dirname($real))) {
            $this->error(".env yazılamıyor ({$real}). Sistem e-postası satırlarını elle güncelleyin: ".implode(', ', array_keys($values)));

            return false;
        }

        if ($content !== '') {
            $copy = $real.'.yedek-'.now('Europe/Istanbul')->format('Ymd-His');
            copy($real, $copy);
            @chmod($copy, 0600);
        }
        foreach ($values as $key => $value) {
            $line = $key.'='.$value;
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
            $content = preg_match($pattern, $content)
                ? preg_replace_callback($pattern, fn () => $line, $content, 1)
                : rtrim($content, "\n")."\n".$line."\n";
        }

        try {
            file_put_contents($real, $content, LOCK_EX);
        } catch (Throwable) {
            $this->error('.env yazılamadı; sistem e-postası satırlarını elle güncelleyin.');

            return false;
        }
        $this->info('.env içindeki sistem e-postası satırları güncellendi.');

        return true;
    }

    private function sendTestMails(string $email, Business $business, TenantContext $tenant, EmailSettingService $emailSettings): void
    {
        $this->line("Deneme e-postaları {$email} adresine gönderiliyor…");
        $content = fn (string $what) => ['title' => 'E-posta ayarı çalışıyor', 'intro' => "Bu deneme e-postası {$what} hesabıyla gönderildi."];

        $this->report('Sistem e-postası', app(SystemMailer::class)->send($email, 'Deneme — sistem e-postası', $content('sistem e-postası')));
        $this->report('Mağaza e-postası', app(StoreMailer::class)->send($email, 'Deneme — mağaza e-postası', $content('mağaza e-postası')));
        try {
            $tenant->runAs($business->id, fn () => $emailSettings->test(app(MailTransport::class)));
            $this->report('B2B e-postası', true);
        } catch (MailFailure $e) {
            $this->report('B2B e-postası', false, $e->getMessage());
        }
    }

    private function report(string $label, bool $ok, ?string $why = null): void
    {
        $ok ? $this->info("✓ {$label} gönderildi.") : $this->warn("✗ {$label} gönderilemedi.".($why ? " ({$why})" : ' Uygulama şifresini kontrol edin.'));
    }
}
