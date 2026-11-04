<?php

namespace App\Http\Controllers\Business;

use App\Domain\Ledger\AccountAnalysis;
use App\Domain\Ledger\LedgerService;
use App\Domain\Support\LocalTime;
use App\Http\Controllers\Controller;
use App\Models\CollectionEntry;
use App\Models\CurrentAccount;
use App\Models\CurrentAccountMovement;
use App\Models\Order;
use App\Rules\NotBefore;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Cari hesaplar: liste, bayi özeti (ödemesi bekleyen siparişler, hareketler), ödeme, açılış bakiyesi ve düzeltme. */
class CurrentAccountController extends Controller
{
    public function __construct(private LedgerService $ledger, private AccountAnalysis $analysis) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'view' => ['nullable', Rule::in(['all', 'debt', 'overdue'])],
        ]);
        $view = $filters['view'] ?? 'debt';

        $accounts = CurrentAccount::query()->with('relationship.dealer:id,legal_name')
            ->when($filters['q'] ?? null, function ($q, $term) {
                $like = addcslashes($term, '%_\\');
                $q->where(fn ($q) => $q->where('code', 'like', "{$like}%")
                    ->orWhereHas('relationship.dealer', fn ($d) => $d->where('legal_name', 'like', "%{$like}%")));
            })
            ->when($view !== 'all', fn ($q) => $q->where('balance', '>', 0))
            ->orderByDesc('balance')
            ->get();

        $aging = $this->analysis->agingMany($accounts);
        if ($view === 'overdue') {
            $accounts = $accounts->filter(fn ($a) => bccomp($aging[$a->id]['overdue'], '0', 2) > 0)->values();
        }

        // Kutular bütün bayilerden (filtreden bağımsız).
        $all = $this->analysis->agingMany(CurrentAccount::where('balance', '>', 0)->get());
        $totals = [
            'receivable' => (string) CurrentAccount::where('balance', '>', 0)->sum('balance'),
            'overdue' => collect($all)->reduce(fn ($c, $a) => bcadd($c, $a['overdue'], 2), '0.00'),
            'due_soon' => collect($all)->reduce(fn ($c, $a) => bcadd($c, $a['due_soon'], 2), '0.00'),
        ];
        $sums = $this->analysis->totals($accounts->pluck('id')->all());

        return view('business.ledger.index', compact('accounts', 'aging', 'filters', 'view', 'totals', 'sums'));
    }

    public function show(Request $request, CurrentAccount $account): View
    {
        $range = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', new NotBefore('from')]]);
        $from = $range['from'] ?? LocalTime::now()->subMonths(3)->startOfMonth()->toDateString();
        $to = $range['to'] ?? LocalTime::today();

        $account->load('relationship.dealer');

        return view('business.ledger.show', [
            'account' => $account,
            'statement' => $this->analysis->statement($account, $from, $to),
            'aging' => $this->analysis->aging($account),
            'sums' => $this->analysis->totals([$account->id])[$account->id] ?? ['debit' => '0.00', 'credit' => '0.00'],
            'from' => $from,
            'to' => $to,
            'hasOpening' => CurrentAccountMovement::where('source_key', "opening:{$account->id}")->exists(),
            'orders' => Order::where('dealer_relationship_id', $account->dealer_relationship_id)
                ->whereNotNull('ledger_applied_at')->whereNull('ledger_reversed_at')->latest('id')->limit(50)->pluck('order_no', 'id'),
        ]);
    }

    public function storeCollection(Request $request, CurrentAccount $account): RedirectResponse
    {
        $data = $request->validate([
            'collected_on' => ['required', 'date', 'before_or_equal:'.LocalTime::today()],
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'max:9999999999999'],
            'method' => ['required', Rule::in(CollectionEntry::SELECTABLE)],
            'order_id' => ['nullable', 'integer'],
            'description' => ['nullable', 'string', 'max:500'],
        ], [], ['collected_on' => 'ödeme tarihi', 'amount' => 'tutar', 'method' => 'ödeme yöntemi', 'description' => 'açıklama']);

        return $this->run(fn () => $this->ledger->recordCollection($account, $data, $request->user()->id), 'Ödeme kaydedildi.');
    }

    public function storeOpening(Request $request, CurrentAccount $account): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'decimal:0,2', 'not_in:0', 'min:-9999999999999', 'max:9999999999999'],
            'date' => ['required', 'date', 'before_or_equal:'.LocalTime::today()],
            'note' => ['nullable', 'string', 'max:500'],
        ], [], ['amount' => 'açılış bakiyesi', 'date' => 'tarih']);

        return $this->run(fn () => $this->ledger->setOpening($account, (string) $data['amount'], $data['date'], $data['note'] ?? null), 'Açılış bakiyesi kaydedildi.');
    }

    public function storeAdjustment(Request $request, CurrentAccount $account): RedirectResponse
    {
        $data = $request->validate([
            'direction' => ['required', Rule::in(['debit', 'credit'])],
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'max:9999999999999'],
            'date' => ['required', 'date', 'before_or_equal:'.LocalTime::today()],
            'note' => ['required', 'string', 'max:500'],
        ], [], ['amount' => 'tutar', 'date' => 'tarih', 'note' => 'açıklama']);

        return $this->run(fn () => $this->ledger->adjust($account, $data['direction'], (string) $data['amount'], $data['date'], $data['note']), 'Düzeltme hareketi kaydedildi.');
    }

    private function run(callable $action, string $message): RedirectResponse
    {
        try {
            $action();
        } catch (DomainException $e) {
            return back()->withErrors(['ledger' => $e->getMessage()])->withInput();
        }

        return back()->with('status', $message);
    }
}
