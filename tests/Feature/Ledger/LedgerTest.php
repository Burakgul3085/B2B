<?php

namespace Tests\Feature\Ledger;

use App\Domain\Ledger\AccountAnalysis;
use App\Domain\Ledger\LedgerService;
use App\Domain\Ordering\OrderService;
use App\Domain\Ordering\ReturnService;
use App\Models\CurrentAccount;
use App\Models\CurrentAccountMovement;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesOrders;
use Tests\TestCase;

/** docs/03-is-kurallari.md §6 — cari hesap kuralları C1–C9. */
class LedgerTest extends TestCase
{
    use CreatesOrders, RefreshDatabase;

    private function account($business, $rel): CurrentAccount
    {
        return $this->inTenant($business, fn () => CurrentAccount::where('dealer_relationship_id', $rel->id)->sole());
    }

    private function ledger(): LedgerService
    {
        return app(LedgerService::class);
    }

    private function assertInvariant(): void
    {
        $mismatch = DB::select('SELECT ca.id FROM current_accounts ca LEFT JOIN (SELECT current_account_id, SUM(debit) - SUM(credit) t FROM current_account_movements GROUP BY current_account_id) m
            ON m.current_account_id = ca.id WHERE ca.balance <> COALESCE(m.t, 0)');
        $this->assertSame([], $mismatch);
    }

    /** Onaylanmış ve tamamlanmış sipariş (10,00 × miktar + %20 KDV). */
    private function completedOrder($business, $rel, string $qty = '5')
    {
        $product = $this->makeProduct($business, ['dealer_price' => '10.00']);
        $this->stockUp($business, $product, '100');
        $order = $this->placeOrder($business, $rel, [$product->id => $qty]);

        return $this->inTenant($business, function () use ($business, $order) {
            $order = app(OrderService::class)->approve($order, [], $this->defaultWarehouse($business)->id, [], $business->user->id);

            return [app(OrderService::class)->complete($order)->fresh('items'), $order->items()->first()->product];
        });
    }

    public function test_collection_reduces_balance_and_cancellation_reverses_it_once(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $this->completedOrder($business, $rel); // borç 60,00
        $account = $this->account($business, $rel);

        $collection = $this->inTenant($business, fn () => $this->ledger()->recordCollection($account, [
            'collected_on' => now()->toDateString(), 'amount' => '45.50', 'method' => 'transfer',
        ], $business->user->id));
        $this->assertSame('14.50', $this->balance($business, $rel));

        $this->inTenant($business, fn () => $this->ledger()->cancelCollection($collection, 'Yanlış bayi'));
        $this->assertSame('60.00', $this->balance($business, $rel));
        $this->assertNotNull($collection->fresh()->cancelled_at);

        $this->expectException(DomainException::class);
        try {
            $this->inTenant($business, fn () => $this->ledger()->cancelCollection($collection->fresh(), 'Tekrar'));
        } finally {
            $this->assertSame('60.00', $this->balance($business, $rel));
            $this->assertInvariant();
        }
    }

    public function test_collection_cannot_reference_another_dealers_order(): void
    {
        $business = $this->makeBusiness();
        $rel1 = $this->orderingDealer($business);
        $rel2 = $this->orderingDealer($business);
        [$order] = $this->completedOrder($business, $rel1);

        $this->expectException(DomainException::class);
        $this->inTenant($business, fn () => $this->ledger()->recordCollection($this->account($business, $rel2), [
            'collected_on' => now()->toDateString(), 'amount' => '10', 'method' => 'cash', 'order_id' => $order->id,
        ], $business->user->id));
    }

    public function test_opening_balance_only_once_and_sign_meaning(): void
    {
        $business = $this->makeBusiness();
        $relDebt = $this->orderingDealer($business);
        $relCredit = $this->orderingDealer($business);

        $this->inTenant($business, fn () => $this->ledger()->setOpening($this->account($business, $relDebt), '250.00', '2026-01-01', null));
        $this->inTenant($business, fn () => $this->ledger()->setOpening($this->account($business, $relCredit), '-40.00', '2026-01-01', null));

        $this->assertSame('250.00', $this->balance($business, $relDebt));
        $this->assertSame('-40.00', $this->balance($business, $relCredit));

        $this->expectException(DomainException::class);
        $this->inTenant($business, fn () => $this->ledger()->setOpening($this->account($business, $relDebt), '1.00', '2026-01-01', null));
    }

    public function test_adjustment_requires_note_and_is_audited(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $account = $this->account($business, $rel);

        $this->actingAs($business->user, 'business');
        $this->post(route('business.accounts.adjust', $account), ['direction' => 'debit', 'amount' => '10', 'date' => now()->toDateString(), 'note' => ''])
            ->assertSessionHasErrors('note');
        $this->post(route('business.accounts.adjust', $account), ['direction' => 'credit', 'amount' => '7.25', 'date' => now()->toDateString(), 'note' => 'Yuvarlama farkı'])
            ->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('-7.25', $this->balance($business, $rel));
        $this->assertDatabaseHas('audit_logs', ['action' => 'ledger.adjusted']);
    }

    public function test_return_adds_stock_and_credit_within_returnable_limit(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        [$order, $product] = $this->completedOrder($business, $rel, '5'); // stok 95, borç 60
        $item = $order->items->first();

        $this->inTenant($business, fn () => app(ReturnService::class)->create($order, [$item->id => '2'], 'Hasarlı geldi', $business->user->id));

        $this->assertSame('97.000', $this->stockQty($business, $product));
        $this->assertSame('36.00', $this->balance($business, $rel)); // 60 − (2 × 10 × 1,20)
        $this->assertSame('completed', $order->fresh()->status, 'İade siparişin durumunu değiştirmemeli.');

        // Kalan iade edilebilir: 3. Fazlası reddedilir.
        try {
            $this->inTenant($business, fn () => app(ReturnService::class)->create($order, [$item->id => '4'], null, $business->user->id));
            $this->fail('İade edilebilir miktar aşıldı.');
        } catch (DomainException) {
        }
        $this->inTenant($business, fn () => app(ReturnService::class)->create($order, [$item->id => '3'], null, $business->user->id));
        $this->assertSame('0.00', $this->balance($business, $rel));
        $this->assertSame('100.000', $this->stockQty($business, $product));
        $this->assertInvariant();
    }

    public function test_return_is_only_for_completed_orders(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $product = $this->makeProduct($business);
        $this->stockUp($business, $product, '10');
        $order = $this->placeOrder($business, $rel, [$product->id => '1']);

        $this->expectException(DomainException::class);
        $this->inTenant($business, fn () => app(ReturnService::class)->create($order, [$order->items->first()->id => '1'], null, $business->user->id));
    }

    public function test_fifo_aging_applies_credits_to_oldest_debts_and_ignores_reversed_pairs(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $account = $this->account($business, $rel);
        $today = '2026-10-20';

        $mk = fn (array $attrs) => CurrentAccountMovement::create($attrs + ['current_account_id' => $account->id, 'debit' => 0, 'credit' => 0]);

        $this->inTenant($business, function () use ($mk, $account, $today) {
            $mk(['type' => 'adjustment_debit', 'debit' => '100', 'movement_date' => '2026-09-01', 'due_date' => '2026-10-10']); // 10 gün gecikmiş
            $mk(['type' => 'adjustment_debit', 'debit' => '50', 'movement_date' => '2026-10-01', 'due_date' => '2026-10-23']);  // 3 gün içinde
            $mk(['type' => 'adjustment_debit', 'debit' => '80', 'movement_date' => '2026-10-15', 'due_date' => '2026-11-20']);  // vadesi uzak
            $cancelled = $mk(['type' => 'adjustment_debit', 'debit' => '500', 'movement_date' => '2026-08-01', 'due_date' => '2026-08-01']);
            $mk(['type' => 'reversal', 'credit' => '500', 'movement_date' => '2026-08-02', 'reverses_movement_id' => $cancelled->id]);
            $mk(['type' => 'collection', 'credit' => '120', 'movement_date' => '2026-10-18']);

            $aging = app(AccountAnalysis::class)->aging($account, $today);

            // 120 tahsilat önce 100'lük gecikmiş borcu, sonra 50'liğin 20'sini kapatır. İptal çifti dikkate alınmaz.
            $this->assertSame('0.00', $aging['overdue']);
            $this->assertSame('30.00', $aging['due_soon']);
            $this->assertSame('80.00', $aging['not_due']);
            $this->assertSame('110.00', $aging['open']);
        });
    }

    public function test_statement_has_opening_and_running_balance(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $account = $this->account($business, $rel);

        $this->inTenant($business, function () use ($account, $business) {
            $this->ledger()->setOpening($account, '100.00', '2026-01-15', null);
            $this->ledger()->adjust($account, 'debit', '50.00', '2026-03-10', 'Fark');
            $this->ledger()->recordCollection($account, ['collected_on' => '2026-03-20', 'amount' => '30.00', 'method' => 'cash'], $business->user->id);

            $s = app(AccountAnalysis::class)->statement($account->fresh(), '2026-03-01', '2026-03-31');
            $this->assertSame('100.00', $s['opening']);
            $this->assertSame(['150.00', '120.00'], $s['rows']->pluck('balance')->all());
            $this->assertSame('120.00', $s['closing']);
        });
    }

    public function test_accounts_are_isolated_between_businesses_and_dealers(): void
    {
        $a = $this->makeBusiness();
        $b = $this->makeBusiness();
        $relA1 = $this->orderingDealer($a);
        $relA2 = $this->orderingDealer($a);
        $accountA1 = $this->account($a, $relA1);
        $collection = $this->inTenant($a, fn () => $this->ledger()->recordCollection($accountA1, ['collected_on' => now()->toDateString(), 'amount' => '5', 'method' => 'cash', 'description' => 'Kasa fişi 4471'], $a->user->id));

        $this->actingAs($b->user, 'business');
        $this->get(route('business.accounts.show', $accountA1))->assertNotFound();
        $this->post(route('business.accounts.collections.store', $accountA1), ['collected_on' => now()->toDateString(), 'amount' => '1', 'method' => 'cash'])->assertNotFound();
        $this->post(route('business.collections.cancel', $collection), ['reason' => 'x'])->assertNotFound();
        $this->assertNull($collection->fresh()->cancelled_at);

        // Bayi yalnızca kendi carisini görür.
        $this->flushSession();
        $this->actingAs($relA2->dealer->user, 'dealer')->get(route('dealer.account.statement'))->assertOk()->assertDontSee('Kasa fişi 4471');
        $this->flushSession();
        $this->actingAs($relA1->dealer->user, 'dealer')->get(route('dealer.account.statement'))->assertOk()->assertSee('Nakit: Kasa fişi 4471');
    }

    public function test_ledger_pages_render(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        [$order] = $this->completedOrder($business, $rel);
        $account = $this->account($business, $rel);

        $this->actingAs($business->user, 'business');
        foreach ([route('business.accounts.index'), route('business.accounts.index', ['view' => 'all']), route('business.accounts.index', ['view' => 'overdue']),
            route('business.accounts.show', $account), route('business.collections.index'), route('business.dashboard')] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get(route('business.orders.show', $order))->assertOk()->assertSee('İade kaydı');
        $this->get(route('business.accounts.show', $account))->assertSee($order->order_no);
    }
}
