<?php

namespace App\Http\Controllers\Dealer;

use App\Domain\Ledger\AccountAnalysis;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Dealer\Concerns\UsesDealerContext;
use App\Models\CollectionEntry;
use App\Models\CurrentAccount;
use App\Rules\NotBefore;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Bayinin seçili toptancıdaki cari hesabı: bakiye, vade durumu, ekstre ve tahsilatlar. Yalnızca görüntüleme. */
class AccountStatementController extends Controller
{
    use UsesDealerContext;

    public function __invoke(Request $request, AccountAnalysis $analysis): View
    {
        $range = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', new NotBefore('from')]]);
        $from = $range['from'] ?? now()->subMonths(3)->startOfMonth()->toDateString();
        $to = $range['to'] ?? now()->toDateString();

        $account = CurrentAccount::where('dealer_relationship_id', $this->relationship()->id)->firstOrFail();

        return view('dealer.account', [
            'account' => $account,
            'aging' => $analysis->aging($account),
            'statement' => $analysis->statement($account, $from, $to),
            'collections' => CollectionEntry::where('current_account_id', $account->id)->whereNull('cancelled_at')->latest('collected_on')->limit(10)->get(),
            'from' => $from,
            'to' => $to,
        ]);
    }
}
