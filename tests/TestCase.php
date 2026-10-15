<?php

namespace Tests;

use App\Domain\Tenancy\TenantContext;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\Dealer;
use App\Models\DealerRelationship;
use App\Models\DealerUser;
use App\Models\PlatformAdmin;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    protected const PASSWORD = 'TestParola123';

    protected function makeBusiness(string $status = 'active', string $userStatus = 'active'): Business
    {
        $slug = 'isletme-'.Str::lower(Str::random(6));
        $business = Business::create([
            'slug' => $slug, 'display_name' => "İşletme {$slug}", 'legal_name' => "İşletme {$slug} Ltd.", 'status' => $status,
        ]);
        $business->settings()->create([]);

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

    protected function makePlatformAdmin(): PlatformAdmin
    {
        return PlatformAdmin::create([
            'name' => 'Platform', 'email' => Str::lower(Str::random(8)).'@platform.local', 'password' => self::PASSWORD, 'is_active' => true,
        ])->fresh();
    }
}
