<?php

namespace Tests\Feature;

use App\Domain\Platform\PlatformAdminService;
use App\Domain\Store\Admins\StoreAdminService;
use App\Models\AuthToken;
use App\Models\Store\StoreAdmin;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Yönetici silme: mağazada süper admin yalnızca yöneticileri siler; platform tüm mağaza yöneticilerini siler ama son etkin
 * süper admini silemez; platform yöneticisi kendini ve son etkin platform yöneticisini silemez. Her silme kayda geçer.
 */
class AdminDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function storeAdmin(bool $super = false, bool $active = true): StoreAdmin
    {
        return StoreAdmin::create([
            'name' => ($super ? 'Süper ' : 'Yönetici ').Str::random(4), 'email' => Str::lower(Str::random(8)).'@magaza.local',
            'password' => self::PASSWORD, 'is_super' => $super, 'is_active' => $active,
        ]);
    }

    public function test_super_admin_deletes_regular_admin_and_history_is_kept(): void
    {
        $super = $this->storeAdmin(super: true);
        $admin = $this->storeAdmin();
        AuthToken::create(['guard' => 'store_admin', 'user_id' => $admin->id, 'purpose' => 'password_reset', 'token_hash' => Str::random(64), 'expires_at' => now()->addHour()]);
        $this->actingAs($super, 'store_admin');

        $this->get(route('store_admin.admins.edit', $admin))->assertOk()->assertSee('Hesabı sil');
        $this->delete(route('store_admin.admins.destroy', $admin))->assertRedirect(route('store_admin.admins.index'))->assertSessionHasNoErrors();

        $this->assertModelMissing($admin);
        $this->assertSame(0, AuthToken::where('guard', 'store_admin')->where('user_id', $admin->id)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'store.admin_deleted', 'actor_type' => 'store_admin', 'actor_id' => $super->id, 'entity_id' => $admin->id]);
    }

    public function test_super_admin_cannot_delete_itself_or_another_super_admin(): void
    {
        $super = $this->storeAdmin(super: true);
        $other = $this->storeAdmin(super: true);
        $this->actingAs($super, 'store_admin');

        $this->get(route('store_admin.admins.edit', $other))->assertForbidden();
        foreach ([$super, $other] as $target) {
            $this->delete(route('store_admin.admins.destroy', $target))->assertForbidden();
            $this->assertModelExists($target);
        }
    }

    public function test_regular_admin_cannot_delete_anyone(): void
    {
        $admin = $this->storeAdmin();
        $other = $this->storeAdmin();
        $this->actingAs($admin, 'store_admin');

        $this->delete(route('store_admin.admins.destroy', $other))->assertForbidden();
        $this->delete(route('store_admin.admins.destroy', $admin))->assertForbidden();
        $this->assertSame(2, StoreAdmin::count());
    }

    public function test_deleted_store_admin_session_ends(): void
    {
        $super = $this->storeAdmin(super: true);
        $admin = $this->storeAdmin();
        $this->actingAs($admin, 'store_admin');
        $this->get(route('store_admin.dashboard'))->assertOk();

        app(StoreAdminService::class)->delete($admin, $super);
        $this->app['auth']->forgetGuards();

        $this->get(route('store_admin.dashboard'))->assertRedirect();
    }

    public function test_platform_deletes_store_admins_but_not_the_last_active_super_admin(): void
    {
        $super = $this->storeAdmin(super: true);
        $second = $this->storeAdmin(super: true);
        $inactiveSuper = $this->storeAdmin(super: true, active: false);
        $admin = $this->storeAdmin();
        $this->actingAsPlatformAdmin();

        $this->get(route('platform.store-admins.show', $admin))->assertOk()->assertSee('Hesabı sil');
        $this->delete(route('platform.store-admins.destroy', $admin))->assertRedirect(route('platform.store-admins.index'));
        $this->delete(route('platform.store-admins.destroy', $inactiveSuper))->assertSessionHasNoErrors();
        $this->delete(route('platform.store-admins.destroy', $second))->assertSessionHasNoErrors();
        $this->assertModelMissing($admin);
        $this->assertModelMissing($second);
        $this->assertModelMissing($inactiveSuper);

        $this->delete(route('platform.store-admins.destroy', $super))->assertSessionHasErrors('admin');
        $this->assertModelExists($super);
        $this->assertDatabaseHas('audit_logs', ['action' => 'store.admin_deleted', 'actor_type' => 'platform', 'entity_id' => $second->id]);
    }

    public function test_platform_admin_deletes_another_but_not_itself(): void
    {
        $me = $this->actingAsPlatformAdmin();
        $other = $this->makePlatformAdmin();
        $inactive = $this->makePlatformAdmin();
        $inactive->forceFill(['is_active' => false])->save();

        $this->get(route('platform.admins.index'))->assertOk()->assertSee($other->email)->assertSee('(siz)');

        $this->delete(route('platform.admins.destroy', $me))->assertSessionHasErrors('admin');
        $this->assertModelExists($me);

        $this->delete(route('platform.admins.destroy', $other))->assertRedirect(route('platform.admins.index'));
        $this->assertModelMissing($other);
        $this->assertDatabaseHas('audit_logs', ['action' => 'platform.admin_deleted', 'actor_type' => 'platform', 'actor_id' => $me->id, 'entity_id' => $other->id]);

        $this->delete(route('platform.admins.destroy', $inactive))->assertSessionHasNoErrors();
        $this->assertModelMissing($inactive);
        $this->assertModelExists($me);
    }

    public function test_last_active_platform_admin_cannot_be_deleted(): void
    {
        $actor = $this->makePlatformAdmin();
        $last = $this->makePlatformAdmin();
        // Silmeyi yapanın hesabı aynı anda pasife alınmış olsa bile son etkin yönetici silinemez.
        DB::table('platform_admins')->where('id', $actor->id)->update(['is_active' => false]);

        try {
            app(PlatformAdminService::class)->delete($last, $actor);
            $this->fail('son etkin platform yöneticisi silinmemeli');
        } catch (DomainException) {
        }
        $this->assertModelExists($last);
    }

    public function test_deletion_routes_require_own_area_login(): void
    {
        $admin = $this->storeAdmin();
        $platform = $this->makePlatformAdmin();

        $this->delete(route('platform.store-admins.destroy', $admin))->assertRedirect(route('platform.login'));
        $this->delete(route('platform.admins.destroy', $platform))->assertRedirect(route('platform.login'));
        $this->delete(route('store_admin.admins.destroy', $admin))->assertRedirect(route('store_admin.login'));

        // Mağaza süper admini platform rotalarına erişemez (mağaza alan adında platform rotası yoktur: 404).
        $this->actingAs($this->storeAdmin(super: true), 'store_admin');
        foreach ([route('platform.admins.destroy', $platform), route('platform.store-admins.destroy', $admin)] as $url) {
            $this->assertContains($this->delete($url)->status(), [302, 403, 404]);
        }

        $this->assertModelExists($admin);
        $this->assertModelExists($platform);
    }
}
