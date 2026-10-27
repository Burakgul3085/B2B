<?php

namespace Tests;

use App\Domain\Platform\ProvisionBusinessDefaults;
use App\Domain\Tenancy\TenantContext;
use App\Http\Middleware\RequirePlatformTwoFactor;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\Dealer;
use App\Models\DealerRelationship;
use App\Models\DealerUser;
use App\Models\PlatformAdmin;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

abstract class TestCase extends BaseTestCase
{
    protected const PASSWORD = 'TestParola123';

    protected function setUp(): void
    {
        parent::setUp();
        // Girişi akış olarak deneyen testler parolayla doğrudan girer; e-posta kodu Auth\LoginCodeTest'te ayrıca denenir.
        config(['b2b.login_code' => false]);
    }

    protected function makeBusiness(string $status = 'active', string $userStatus = 'active'): Business
    {
        $slug = 'isletme-'.Str::lower(Str::random(6));
        $business = Business::create([
            'slug' => $slug, 'display_name' => "İşletme {$slug}", 'legal_name' => "İşletme {$slug} Ltd.", 'status' => $status,
        ]);
        $business->settings()->create([]);
        app(ProvisionBusinessDefaults::class)->handle($business);

        BusinessUser::create([
            'business_id' => $business->id, 'name' => 'Yönetici', 'email' => "{$slug}@test.local",
            'password' => $userStatus === 'pending_activation' ? null : self::PASSWORD,
            'status' => $userStatus,
        ]);

        return $business->fresh('user');
    }

    protected function makeDealer(): DealerUser
    {
        $dealer = Dealer::create(['legal_name' => 'Bayi '.Str::random(5)]);

        return DealerUser::create([
            'dealer_id' => $dealer->id, 'name' => 'Bayi Yetkilisi', 'email' => Str::lower(Str::random(8)).'@bayi.local',
            'password' => self::PASSWORD, 'status' => 'active',
        ])->fresh();
    }

    protected function relate(Business $business, DealerUser $dealerUser, string $status = 'active'): DealerRelationship
    {
        return app(TenantContext::class)->runAs($business->id, fn () => DealerRelationship::create([
            'dealer_id' => $dealerUser->dealer_id, 'dealer_code' => 'B'.Str::random(5), 'status' => $status,
        ]));
    }

    /** 2FA kurulmuş ve bu oturumda doğrulanmış platform yöneticisi olarak işlem yapar. */
    protected function actingAsPlatformAdmin(): PlatformAdmin
    {
        $admin = $this->makePlatformAdmin();
        $admin->forceFill(['two_factor_secret' => (new Google2FA)->generateSecretKey(32), 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($admin, 'platform')->withSession([RequirePlatformTwoFactor::SESSION_KEY => true]);

        return $admin;
    }

    protected function makePlatformAdmin(): PlatformAdmin
    {
        return PlatformAdmin::create([
            'name' => 'Platform', 'email' => Str::lower(Str::random(8)).'@platform.local', 'password' => self::PASSWORD, 'is_active' => true,
        ])->fresh();
    }
}
