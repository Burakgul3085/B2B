<?php

namespace App\Domain\Platform;

use App\Domain\Audit\AuditLogger;
use App\Models\Business;
use App\Models\BusinessModule;
use App\Models\BusinessUser;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Platform yöneticisi toptancı işletmesini açar: işletme kaydı, varsayılan ayarlar,
 * modüller, ilk lisans ve tek işletme kullanıcısı tek transaction'da oluşur.
 *
 * Sistem tek toptancı içindir (docs/13-magaza-mimarisi.md §4): bir işletme varken ikincisi açılamaz.
 * Çok işletmeli altyapı (tenant izolasyonu) yerinde kalır.
 */
class CreateBusiness
{
    public function __construct(private AuditLogger $audit) {}

    public static function allowed(): bool
    {
        return ! Business::exists();
    }

    /**
     * Yetkili hesabı hemen etkindir: giriş e-postası ve şifreyi platform yöneticisi belirler (aktivasyon bağlantısı yok).
     *
     * @param  array{name: string, email: string, password: string}  $owner
     * @return array{business: Business}
     *
     * @throws DomainException
     */
    public function handle(array $business, array $owner, array $license): array
    {
        [$model] = DB::transaction(function () use ($business, $owner, $license) {
            // Aynı anda iki istek gelse de yalnızca biri işletme açabilsin.
            DB::table('businesses')->lockForUpdate()->count();
            if (! self::allowed()) {
                throw new DomainException('Sistem tek toptancı içindir; zaten bir işletme var.');
            }

            $model = Business::create($business + ['status' => 'active']);
            $model->settings()->create([]);
            app(ProvisionBusinessDefaults::class)->handle($model);

            foreach (array_keys(BusinessModule::AVAILABLE) as $key) {
                $model->modules()->create(['module_key' => $key, 'enabled' => true]);
            }

            $model->licenses()->create($license);

            $user = BusinessUser::create([
                'business_id' => $model->id,
                'name' => $owner['name'],
                'email' => $owner['email'],
                'password' => $owner['password'],
            ]);
            $user->forceFill(['status' => 'active', 'activated_at' => now()])->save();

            $this->audit->log('platform.business_created', $model, [], [
                'slug' => $model->slug, 'legal_name' => $model->legal_name, 'owner_email' => $user->email,
            ], businessId: $model->id);

            return [$model, $user];
        });

        return ['business' => $model];
    }
}
