{{-- Cari ekstre yazdırma belgesi (toptancı ve bayi ortak). $business, $account, $aging, $statement, $from, $to, $statementArgs (partials.statement parametreleri). --}}
@php
    use App\Domain\Support\Money;
    use Illuminate\Support\Carbon;

    $balance = (string) $account->balance;
    $sign = bccomp($balance, '0', 2);
@endphp
<x-print-document title="Cari Hesap Ekstresi" :number="'Cari kodu '.$account->code" :business="$business" :dealer="$account->relationship->dealer"
    :meta="[
        'Dönem' => Carbon::parse($from)->format('d.m.Y').' – '.Carbon::parse($to)->format('d.m.Y'),
        'Ödeme' => $account->relationship->payLaterEnabled() ? $account->relationship->payLaterLabel() : null,
        'Güncel bakiye' => Money::format(ltrim($balance, '-')).($sign > 0 ? ' (B)' : ($sign < 0 ? ' (A)' : '')),
        'Gecikmiş tutar' => bccomp((string) $aging['overdue'], '0', 2) > 0 ? Money::format($aging['overdue']) : null,
    ]">
    @include('partials.statement', $statementArgs + ['bare' => true])
</x-print-document>
