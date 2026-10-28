<?php

namespace App\Http\Controllers\Business;

use App\Domain\Ledger\LedgerService;
use App\Domain\Support\LocalTime;
use App\Http\Controllers\Controller;
use App\Models\CollectionEntry;
use App\Models\CurrentAccount;
use App\Rules\NotBefore;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CollectionController extends Controller
{
    public function __construct(private LedgerService $ledger) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', new NotBefore('from')],
            'method' => ['nullable', Rule::in(array_keys(CollectionEntry::METHODS))],
            'account' => ['nullable', 'integer'],
            'cancelled' => ['nullable', 'boolean'],
        ]);
        $from = $filters['from'] ?? LocalTime::now()->startOfMonth()->toDateString();
        $to = $filters['to'] ?? LocalTime::today();

        $query = CollectionEntry::query()
            ->whereBetween('collected_on', [$from, $to])
            ->when($filters['method'] ?? null, fn ($q, $m) => $q->where('method', $m))
            ->when($filters['account'] ?? null, fn ($q, $a) => $q->where('current_account_id', $a))
            ->when(! ($filters['cancelled'] ?? false), fn ($q) => $q->whereNull('cancelled_at'));

        return view('business.ledger.collections', [
            'collections' => (clone $query)->with(['account.relationship.dealer:id,legal_name', 'order:id,order_no'])->latest('collected_on')->latest('id')->paginate(30)->withQueryString(),
            'total' => (string) (clone $query)->whereNull('cancelled_at')->sum('amount'),
            'filters' => $filters + ['from' => $from, 'to' => $to],
            'accounts' => CurrentAccount::with('relationship.dealer:id,legal_name')->orderBy('code')->get()->mapWithKeys(fn ($a) => [$a->id => $a->code.' · '.$a->relationship->dealer->legal_name]),
        ]);
    }

    public function cancel(Request $request, CollectionEntry $collection): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'max:500']], [], ['reason' => 'iptal nedeni'])['reason'];

        try {
            $this->ledger->cancelCollection($collection, $reason);
        } catch (DomainException $e) {
            return back()->withErrors(['ledger' => $e->getMessage()]);
        }

        return back()->with('status', 'Tahsilat iptal edildi; ters kayıt cari hesaba yazıldı.');
    }
}
