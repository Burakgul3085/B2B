<?php

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use App\Models\Store\StoreAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Doğrulama uygulamasını kaybeden yöneticinin iki adımlı doğrulaması sunucudan sıfırlanır. */
class ResetTwoFactorCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_two_factor_is_reset_after_confirmation(): void
    {
        $admin = PlatformAdmin::create(['name' => 'Burak', 'email' => 'admin@ornek.com', 'password' => self::PASSWORD, 'is_active' => true]);
        $admin->forceFill(['two_factor_secret' => 'SECRET', 'two_factor_confirmed_at' => now()])->save();

        $this->artisan('platform:reset-two-factor', ['email' => 'ADMIN@ornek.com'])->expectsConfirmation('Burak (admin@ornek.com) için iki adımlı doğrulama sıfırlansın mı?', 'no')->assertFailed();
        $this->assertNotNull($admin->fresh()->two_factor_confirmed_at);

        $this->artisan('platform:reset-two-factor', ['email' => 'admin@ornek.com'])->expectsConfirmation('Burak (admin@ornek.com) için iki adımlı doğrulama sıfırlansın mı?', 'yes')->assertSuccessful();
        $this->assertNull($admin->fresh()->two_factor_secret);
        $this->assertNull($admin->fresh()->two_factor_confirmed_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.two_factor_reset_by_server', 'entity_id' => $admin->id]);
    }

    public function test_store_admin_option_and_unknown_email(): void
    {
        $admin = StoreAdmin::create(['name' => 'Süper', 'email' => 's@magaza.local', 'password' => self::PASSWORD, 'is_super' => true]);
        $admin->forceFill(['two_factor_secret' => 'SECRET', 'two_factor_confirmed_at' => now()])->save();

        $this->artisan('platform:reset-two-factor', ['email' => 's@magaza.local'])->assertFailed(); // platform yöneticisi değil
        $this->artisan('platform:reset-two-factor', ['email' => 's@magaza.local', '--store-admin' => true])
            ->expectsConfirmation('Süper (s@magaza.local) için iki adımlı doğrulama sıfırlansın mı?', 'yes')->assertSuccessful();
        $this->assertNull($admin->fresh()->two_factor_confirmed_at);
    }
}
