<?php

namespace Tests\Feature\Tenancy;

use App\Domain\Tenancy\MissingTenantContext;
use App\Domain\Tenancy\TenantContext;
use App\Models\DealerGroup;
use App\Models\DealerRelationship;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

/** Bölüm 3: bir toptancının verisi diğerine hiçbir koşulda gösterilmez. */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_owned_model_cannot_be_queried_without_context(): void
    {
        $this->expectException(MissingTenantContext::class);

        DealerRelationship::count();
    }

    public function test_queries_only_return_rows_of_the_active_business(): void
    {
        $a = $this->makeBusiness();
        $b = $this->makeBusiness();
        $dealer = $this->makeDealer();
        $this->relate($a, $dealer);
        $this->relate($b, $dealer);
        $this->relate($b, $this->makeDealer());

        $tenant = app(TenantContext::class);

        $this->assertSame(1, $tenant->runAs($a->id, fn () => DealerRelationship::count()));
        $this->assertSame(2, $tenant->runAs($b->id, fn () => DealerRelationship::count()));
        $this->assertSame([$a->id], $tenant->runAs($a->id, fn () => DealerRelationship::pluck('business_id')->unique()->values()->all()));
    }

    public function test_find_by_id_of_another_business_returns_nothing(): void
    {
        $a = $this->makeBusiness();
        $b = $this->makeBusiness();
        $foreign = $this->relate($b, $this->makeDealer());

        $found = app(TenantContext::class)->runAs($a->id, fn () => DealerRelationship::find($foreign->id));

        $this->assertNull($found);
    }

    public function test_record_cannot_be_created_for_another_business(): void
    {
        $a = $this->makeBusiness();
        $b = $this->makeBusiness();
        $dealer = $this->makeDealer();

        $this->expectException(LogicException::class);

        app(TenantContext::class)->runAs($a->id, fn () => (new DealerRelationship)->forceFill([
            'business_id' => $b->id, 'dealer_id' => $dealer->dealer_id, 'dealer_code' => 'X1',
        ])->save());
    }

    public function test_business_of_a_record_cannot_be_changed(): void
    {
        $a = $this->makeBusiness();
        $b = $this->makeBusiness();
        $relationship = $this->relate($a, $this->makeDealer());

        $this->expectException(LogicException::class);

        app(TenantContext::class)->runAs($a->id, function () use ($relationship, $b) {
            $relationship->business_id = $b->id;
            $relationship->save();
        });
    }

    public function test_database_rejects_dealer_group_of_another_business(): void
    {
        $a = $this->makeBusiness();
        $b = $this->makeBusiness();
        $groupOfB = app(TenantContext::class)->runAs($b->id, fn () => DealerGroup::create(['name' => 'A Grubu']));
        $dealer = $this->makeDealer();

        // Uygulama katmanı atlansa bile bileşik yabancı anahtar engeller.
        $this->expectException(QueryException::class);

        DB::table('dealer_relationships')->insert([
            'business_id' => $a->id, 'dealer_id' => $dealer->dealer_id, 'dealer_code' => 'Y1',
            'dealer_group_id' => $groupOfB->id, 'status' => 'active', 'payment_term_days' => 30,
            'can_edit_addresses' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_business_context_comes_from_session_user_not_from_request(): void
    {
        $a = $this->makeBusiness();
        $b = $this->makeBusiness();
        $this->relate($b, $this->makeDealer());

        $this->actingAs($a->user, 'business')
            ->get('/toptanci?business_id='.$b->id)
            ->assertOk()
            ->assertSee($a->display_name)
            ->assertDontSee($b->display_name);
    }
}
