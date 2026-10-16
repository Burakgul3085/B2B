<?php

namespace App\Domain\Platform;

use App\Domain\Audit\AuditLogger;
use App\Models\Business;
use App\Models\BusinessModule;
use App\Models\BusinessUser;
use Illuminate\Support\Facades\DB;

/**
 * Platform yöneticisi yeni bir toptancı işletmesi açar: işletme kaydı, varsayılan ayarlar,
 * modüller, ilk lisans ve tek işletme kullanıcısı tek transaction'da oluşur.
 * Kod değişikliği gerekmez (kabul koşulu 18).
 */
class CreateBusiness
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * Yetkili hesabı hemen etkindir: giriş e-postası ve şifreyi platform yöneticisi belirler (aktivasyon bağlantısı yok).
     *
     * @param  array{name: string, email: string, password: string}  $owner
     * @return array{business: Business}
     */
    public function handle(array $business, array $owner, array $license): array
    {
        [$model] = DB::transaction(function () use ($business, $owner, $license) {
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
