<?php

namespace App\Http\Controllers\Business;

use App\Domain\Ledger\AccountAnalysis;
use App\Domain\Ledger\LedgerService;
use App\Http\Controllers\Controller;
use App\Models\CollectionEntry;
use App\Models\CurrentAccount;
use App\Models\CurrentAccountMovement;
use App\Models\Order;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Cari hesaplar: liste, ekstre, yaşlandırma, tahsilat, açılış bakiyesi ve düzeltme. */
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

        $totals = [
            'receivable' => (string) CurrentAccount::where('balance', '>', 0)->sum('balance'),
            'overdue' => collect($aging)->reduce(fn ($c, $a) => bcadd($c, $a['overdue'], 2), '0.00'),
            'month_collections' => (string) CollectionEntry::whereNull('cancelled_at')->where('collected_on', '>=', now()->startOfMonth()->toDateString())->sum('amount'),
        ];

        return view('business.ledger.index', compact('accounts', 'aging', 'filters', 'view', 'totals'));
    }

    public function show(Request $request, CurrentAccount $account): View
    {
        $range = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $from = $range['from'] ?? now()->subMonths(3)->startOfMonth()->toDateString();
        $to = $range['to'] ?? now()->toDateString();

        $account->load('relationship.dealer');

        return view('business.ledger.show', [
            'account' => $account,
            'statement' => $this->analysis->statement($account, $from, $to),
            'aging' => $this->analysis->aging($account),
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
            'collected_on' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'max:9999999999999'],
            'method' => ['required', Rule::in(array_keys(CollectionEntry::METHODS))],
            'order_id' => ['nullable', 'integer'],
            'description' => ['nullable', 'string', 'max:500'],
        ], [], ['collected_on' => 'tahsilat tarihi', 'amount' => 'tutar', 'method' => 'tahsilat yöntemi', 'description' => 'açıklama']);

        return $this->run(fn () => $this->ledger->recordCollection($account, $data, $request->user()->id), 'Tahsilat kaydedildi.');
    }

    public function storeOpening(Request $request, CurrentAccount $account): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'decimal:0,2', 'not_in:0', 'min:-9999999999999', 'max:9999999999999'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [], ['amount' => 'açılış bakiyesi', 'date' => 'tarih']);

        return $this->run(fn () => $this->ledger->setOpening($account, (string) $data['amount'], $data['date'], $data['note'] ?? null), 'Açılış bakiyesi kaydedildi.');
    }

    public function storeAdjustment(Request $request, CurrentAccount $account): RedirectResponse
    {
        $data = $request->validate([
            'direction' => ['required', Rule::in(['debit', 'credit'])],
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'max:9999999999999'],
            'date' => ['required', 'date', 'before_or_equal:today'],
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
