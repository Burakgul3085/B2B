<?php

namespace App\Console\Commands;

use App\Domain\Audit\AuditLogger;
use App\Models\PlatformAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;

/**
 * Doğrulama uygulamasını kaybeden platform yöneticisinin iki adımlı doğrulamasını sıfırlar (yalnızca sunucudan, SSH ile).
 * Kurtarma kodu yoktur; hesabın sahibi bir sonraki girişte yeni karekodla yeniden kurar. Açık oturumları kapatılır.
 */
class ResetTwoFactor extends Command
{
    protected $signature = 'platform:reset-two-factor {email : Yöneticinin giriş e-postası} {--force : Onay sormadan sıfırla (SSH ile tek komut)}';

    protected $description = 'Platform yöneticisinin iki adımlı doğrulamasını sıfırlar (yeni karekodla yeniden kurulur)';

    public function handle(AuditLogger $audit): int
    {
        $admin = PlatformAdmin::where('email', Str::lower(trim($this->argument('email'))))->first();
        if (! $admin) {
            $this->error('Bu e-postayla bir platform yöneticisi bulunamadı.');

            return self::FAILURE;
        }
        if (! $this->option('force') && ! confirm("{$admin->name} ({$admin->email}) için iki adımlı doğrulama sıfırlansın mı?", default: false)) {
            return self::FAILURE;
        }

        DB::transaction(function () use ($admin, $audit) {
            $admin->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null, 'remember_token' => null])->save();
            $audit->log('auth.two_factor_reset_by_server', $admin, [], ['email' => $admin->email]);
        });

        $this->info('Sıfırlandı. Bir sonraki girişte yeni karekod gösterilecek; doğrulama uygulamasıyla yeniden okutun.');

        return self::SUCCESS;
    }
}
