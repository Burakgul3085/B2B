<?php

namespace Database\Seeders;

use App\Domain\Tenancy\TenantContext;
use App\Models\Business;
use App\Models\BusinessModule;
use App\Models\BusinessUser;
use App\Models\Dealer;
use App\Models\DealerRelationship;
use App\Models\DealerUser;
use App\Models\PlatformAdmin;
use Illuminate\Database\Seeder;

/**
 * Yalnızca yerel geliştirme içindir (DatabaseSeeder local ortam dışında çağırmaz).
 * Tüm hesapların parolası: DEV_PASSWORD. Platform yöneticisi ilk girişte 2FA kurar.
 */
class DevelopmentSeeder extends Seeder
{
    public const DEV_PASSWORD = 'Gelistirme2026';

    public function run(): void
    {
        abort_unless(app()->environment('local', 'testing'), 403, 'Geliştirme verisi yalnızca local ortamda yüklenir.');

        PlatformAdmin::create([
            'name' => 'Platform Yöneticisi', 'email' => 'platform@b2b.test', 'password' => self::DEV_PASSWORD, 'is_active' => true,
        ]);

        $akgun = $this->business('akgun-kirtasiye', 'Akgün Kırtasiye', 'Akgün Kırtasiye Toptan Tic. Ltd. Şti.', 'yonetici@akgun.test');
        $other = $this->business('demo-toptan', 'Demo Toptan', 'Demo Toptan Ticaret A.Ş.', 'yonetici@demotoptan.test');

        // Onur Kırtasiye iki toptancıyla çalışıyor (çoklu toptancı senaryosu).
        $onur = Dealer::create(['legal_name' => 'Onur Kırtasiye', 'phone' => '0212 000 00 00', 'city' => 'İstanbul', 'district' => 'Kadıköy']);
        DealerUser::create([
            'dealer_id' => $onur->id, 'name' => 'Onur Yılmaz', 'email' => 'onur@onurkirtasiye.test',
            'password' => self::DEV_PASSWORD, 'status' => 'active', 'activated_at' => now(),
        ]);

        $tenant = app(TenantContext::class);
        $tenant->runAs($akgun->id, fn () => DealerRelationship::create([
            'dealer_id' => $onur->id, 'dealer_code' => 'B0001', 'status' => 'active', 'approved_at' => now(),
        ]));
        $tenant->runAs($other->id, fn () => DealerRelationship::create([
            'dealer_id' => $onur->id, 'dealer_code' => 'DT-001', 'status' => 'active', 'approved_at' => now(),
        ]));
    }

    private function business(string $slug, string $name, string $legal, string $email): Business
    {
        $business = Business::create([
            'slug' => $slug, 'display_name' => $name, 'legal_name' => $legal, 'status' => 'active',
            'city' => 'İstanbul', 'email' => "iletisim@{$slug}.test",
        ]);
        $business->settings()->create([]);
        $business->licenses()->create(['plan' => 'Standart', 'starts_on' => now()->subMonth()->toDateString()]);
        foreach (array_keys(BusinessModule::AVAILABLE) as $key) {
            $business->modules()->create(['module_key' => $key, 'enabled' => true]);
        }

        BusinessUser::create([
            'business_id' => $business->id, 'name' => "{$name} Yöneticisi", 'email' => $email,
            'password' => self::DEV_PASSWORD, 'status' => 'active', 'activated_at' => now(),
        ]);

        return $business;
    }
}
