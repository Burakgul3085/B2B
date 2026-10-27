<?php

namespace Tests\Feature\Store;

use App\Models\AuditLog;
use App\Models\Store\StoreAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Yönetici hesapları (docs/13-magaza-mimarisi.md §5): süper admini platform açar, süper admin yönetici açar;
 * yöneticiler kimseyi yönetemez, süper admin kendini ve diğer süper adminleri yönetemez. Her işlem kayda geçer.
 */
class StoreAdminManagementTest extends TestCase
{
    use RefreshDatabase;

    private const NEW_PASSWORD = 'MagazaSifre2026';

    private function admin(bool $super = false, bool $active = true): StoreAdmin
    {
        $admin = StoreAdmin::create([
            'name' => ($super ? 'Süper ' : 'Yönetici ').Str::random(4), 'email' => Str::lower(Str::random(8)).'@magaza.local',
            'password' => self::PASSWORD, 'is_super' => $super, 'is_active' => $active,
        ]);

        return $admin;
    }

    private function actingAsStore(StoreAdmin $admin): void
    {
        $this->actingAs($admin, 'store_admin');
    }

    public function test_platform_creates_super_admin_who_can_then_sign_in(): void
    {
        $this->actingAsPlatformAdmin();

        $this->get(route('platform.store-admins.index'))->assertOk()->assertSee('Henüz mağaza yöneticisi yok');
        $this->post(route('platform.store-admins.store'), [
            'name' => 'Burak Gül', 'email' => 'Burak@Yapboz.test', 'password' => self::NEW_PASSWORD, 'is_super' => '1',
        ])->assertSessionHasNoErrors();

        $super = StoreAdmin::where('email', 'burak@yapboz.test')->firstOrFail();
        $this->assertTrue($super->is_super);
        $this->assertSame('platform', $super->created_by_type);
        $this->assertDatabaseHas('audit_logs', ['action' => 'store.admin_created', 'actor_type' => 'platform', 'entity_id' => $super->id]);

        // Aynı e-postayla ikinci hesap açılamaz.
        $this->post(route('platform.store-admins.store'), ['name' => 'X', 'email' => 'burak@yapboz.test', 'password' => self::NEW_PASSWORD])
            ->assertSessionHasErrors('email');
    }

    public function test_super_admin_creates_and_manages_admins(): void
    {
        $super = $this->admin(super: true);
        $this->actingAsStore($super);

        $this->get(route('store_admin.admins.index'))->assertOk();
        $this->post(route('store_admin.admins.store'), ['name' => 'Ayşe', 'email' => 'ayse@yapboz.test', 'password' => self::NEW_PASSWORD])
            ->assertRedirect(route('store_admin.admins.index'));

        $ayse = StoreAdmin::where('email', 'ayse@yapboz.test')->firstOrFail();
        $this->assertFalse($ayse->is_super, 'süper admin yalnızca yönetici açar');
        $this->assertSame(['store_admin', $super->id], [$ayse->created_by_type, $ayse->created_by_id]);

        // Formdan "is_super" gönderilse bile yönetici olarak açılır.
        $this->post(route('store_admin.admins.store'), ['name' => 'Can', 'email' => 'can@yapboz.test', 'password' => self::NEW_PASSWORD, 'is_super' => 1]);
        $this->assertFalse(StoreAdmin::where('email', 'can@yapboz.test')->value('is_super'));

        $this->get(route('store_admin.admins.edit', $ayse))->assertOk();
        $this->put(route('store_admin.admins.update', $ayse), ['name' => 'Ayşe Yılmaz', 'email' => 'ayse.yilmaz@yapboz.test'])->assertSessionHasNoErrors();
        $this->put(route('store_admin.admins.password', $ayse), ['password' => 'BaskaSifre2026', 'password_confirmation' => 'BaskaSifre2026'])->assertSessionHasNoErrors();
        $this->put(route('store_admin.admins.status', $ayse), ['active' => 0])->assertSessionHasNoErrors();

        $ayse->refresh();
        $this->assertSame(['Ayşe Yılmaz', 'ayse.yilmaz@yapboz.test', false], [$ayse->name, $ayse->email, $ayse->is_active]);
        $this->assertSame(1, StoreAdmin::whereKey($ayse->id)->count(), 'hesap silinmez, pasife alınır');

        foreach (['store.admin_created', 'store.admin_updated', 'store.admin_password_set', 'store.admin_deactivated'] as $action) {
            $this->assertDatabaseHas('audit_logs', ['action' => $action, 'actor_type' => 'store_admin', 'actor_id' => $super->id]);
        }
    }

    public function test_super_admin_cannot_manage_itself_or_other_super_admins(): void
    {
        $super = $this->admin(super: true);
        $otherSuper = $this->admin(super: true);
        $this->actingAsStore($super);

        foreach ([$super, $otherSuper] as $target) {
            $this->get(route('store_admin.admins.edit', $target))->assertForbidden();
            $this->put(route('store_admin.admins.status', $target), ['active' => 0])->assertSessionHasErrors('admin');
            $this->put(route('store_admin.admins.password', $target), ['password' => 'BaskaSifre2026', 'password_confirmation' => 'BaskaSifre2026'])
                ->assertSessionHasErrors('admin');
            $this->put(route('store_admin.admins.update', $target), ['name' => 'X', 'email' => 'x@x.test'])->assertSessionHasErrors('admin');
        }

        $this->assertTrue($super->fresh()->is_active);
        $this->assertTrue($otherSuper->fresh()->is_active);
    }

    public function test_regular_admin_cannot_manage_anyone(): void
    {
        $admin = $this->admin();
        $other = $this->admin();
        $super = $this->admin(super: true);
        $this->actingAsStore($admin);

        $this->get(route('store_admin.admins.index'))->assertForbidden();
        $this->get(route('store_admin.admins.create'))->assertForbidden();
        $this->post(route('store_admin.admins.store'), ['name' => 'X', 'email' => 'x@yapboz.test', 'password' => self::NEW_PASSWORD])->assertForbidden();
        foreach ([$other, $super] as $target) {
            $this->put(route('store_admin.admins.status', $target), ['active' => 0])->assertForbidden();
            $this->put(route('store_admin.admins.password', $target), ['password' => 'BaskaSifre2026', 'password_confirmation' => 'BaskaSifre2026'])->assertForbidden();
        }

        $this->assertSame(3, StoreAdmin::where('is_active', true)->count());
        $this->get(route('store_admin.dashboard'))->assertOk()->assertDontSee(route('store_admin.admins.index'));
    }

    public function test_platform_can_deactivate_super_admin(): void
    {
        $super = $this->admin(super: true);
        $this->actingAsPlatformAdmin();

        $this->get(route('platform.store-admins.show', $super))->assertOk();

        $this->put(route('platform.store-admins.status', $super), ['active' => 0])->assertSessionHasNoErrors();
        $this->assertFalse($super->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'store.admin_deactivated', 'actor_type' => 'platform', 'entity_id' => $super->id]);
    }

    public function test_activity_log_shows_store_events_only(): void
    {
        $super = $this->admin(super: true);
        $this->actingAsStore($super);
        $this->post(route('store_admin.admins.store'), ['name' => 'Ayşe', 'email' => 'ayse@yapboz.test', 'password' => self::NEW_PASSWORD]);
        AuditLog::create(['action' => 'platform.business_created', 'actor_type' => 'platform', 'actor_id' => 1]);

        $this->get(route('store_admin.activity'))->assertOk()
            ->assertSee('Yönetici hesabı açıldı')
            ->assertSee($super->name)
            ->assertDontSee('platform.business_created');
    }

    public function test_store_admin_routes_are_unreachable_from_b2b_sessions(): void
    {
        $business = $this->makeBusiness();
        $this->actingAs($business->user, 'business');

        $this->get(route('store_admin.dashboard'))->assertRedirect(route('store_admin.login'));
        $this->get(route('store_admin.admins.index'))->assertRedirect(route('store_admin.login'));
    }
}
