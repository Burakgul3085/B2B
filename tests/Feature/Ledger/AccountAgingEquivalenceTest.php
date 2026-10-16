<?php

namespace Tests\Feature\Ledger;

use App\Domain\Ledger\AccountAnalysis;
use App\Domain\Ledger\LedgerService;
use App\Models\CurrentAccount;
use App\Models\CurrentAccountMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrders;
use Tests\TestCase;

/**
 * Toplu yaşlandırma (SQL, agingMany) ile hareketlerden FIFO hesabının (agingFromMovements) birebir aynı
 * sonucu verdiğini rastgele defterlerle doğrular: borç/alacak düzeltmeleri, açılış, tahsilat, iptaller,
 * aynı vadeli borçlar, alacak bakiyesi.
 */
class AccountAgingEquivalenceTest extends TestCase
{
    use CreatesOrders, RefreshDatabase;

    public function test_bulk_aging_matches_fifo_on_random_ledgers(): void
    {
        $business = $this->makeBusiness();
        $ledger = app(LedgerService::class);
        mt_srand(7);

        $accounts = [];
        for ($a = 0; $a < 12; $a++) {
            $rel = $this->orderingDealer($business);
            $accounts[] = $this->inTenant($business, function () use ($rel, $ledger, $business, $a) {
                $account = CurrentAccount::where('dealer_relationship_id', $rel->id)->sole();
                if ($a % 3 === 0) {
                    $ledger->setOpening($account, (string) (mt_rand(-5000, 20000) / 100), now()->subDays(mt_rand(30, 200))->toDateString(), null);
                }
                for ($i = 0; $i < mt_rand(3, 15); $i++) {
                    $date = now()->subDays(mt_rand(0, 120))->toDateString();
                    $amount = (string) (mt_rand(100, 90000) / 100);
                    match (mt_rand(1, 4)) {
                        1, 2 => $ledger->adjust($account->fresh(), 'debit', $amount, $date, 'Borç'),
                        3 => $ledger->adjust($account->fresh(), 'credit', $amount, $date, 'Alacak'),
                        4 => (function () use ($ledger, $account, $amount, $date, $business) {
                            $c = $ledger->recordCollection($account->fresh(), ['collected_on' => $date, 'amount' => $amount, 'method' => 'cash'], $business->user->id);
                            if (mt_rand(0, 1)) {
                                $ledger->cancelCollection($c, 'Hatalı');
                            }
                        })(),
                    };
                }
                // Vadeleri farklılaştır (aynı vadeli borçlar dahil).
                CurrentAccountMovement::where('current_account_id', $account->id)->where('debit', '>', 0)->whereNull('reverses_movement_id')->get()
                    ->each(fn ($m) => $m->forceFill(['due_date' => now()->addDays(mt_rand(-60, 30) - (mt_rand(0, 3) ? 0 : 5))->toDateString()])->saveQuietly());

                return $account->fresh();
            });
        }

        $this->inTenant($business, function () use ($accounts) {
            $analysis = app(AccountAnalysis::class);
            $bulk = $analysis->agingMany(collect($accounts));
            $checkedPositive = 0;

            foreach ($accounts as $account) {
                $single = $analysis->aging($account);
                foreach (['overdue', 'due_soon', 'not_due', 'open', 'credit'] as $key) {
                    $this->assertSame($single[$key], $bulk[$account->id][$key], "Hesap {$account->id}, {$key}");
                }
                $this->assertSame(
                    $single['items']->map(fn ($i) => [$i['movement']->id, $i['open'], $i['bucket']])->all(),
                    $bulk[$account->id]['items']->map(fn ($i) => [$i['movement_id'], $i['open'], $i['bucket']])->all(),
                );
                $checkedPositive += bccomp((string) $account->balance, '0', 2) > 0 ? 1 : 0;
            }
            $this->assertGreaterThan(3, $checkedPositive);
        });
    }
}
