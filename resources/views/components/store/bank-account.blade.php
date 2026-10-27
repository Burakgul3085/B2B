{{--
    Havale / EFT hesabı: banka logosu (IBAN'dan tanınır; tanınmayanda baş harfli rozet), hesap sahibi ve IBAN.
    IBAN düğmeyle kopyalanır. $account: BankTransfer::accounts() öğesi.
--}}
@props(['account'])
@php
    $iban = \App\Domain\Store\Payments\BankTransfer::formatIban($account['iban']);
    $initials = mb_strtoupper(collect(preg_split('/\s+/', trim($account['bank'])))->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode(''), 'UTF-8');
@endphp
<li {{ $attributes->merge(['class' => 'flex flex-col gap-3 rounded-xl bg-white/80 p-4 text-ink ring-1 ring-black/5 sm:flex-row sm:items-center']) }}>
    <span class="flex h-12 w-36 shrink-0 items-center justify-center rounded-lg bg-[#fff] px-3 ring-1 ring-line">
        @if ($account['logo'])
            <img src="{{ $account['logo'] }}" alt="{{ $account['bank'] }}" class="max-h-7 max-w-full object-contain" loading="lazy">
        @else
            <span class="flex size-9 items-center justify-center rounded-full bg-slate-100 text-sm font-bold text-slate-600" aria-hidden="true">{{ $initials }}</span>
        @endif
    </span>
    <span class="min-w-0 flex-1">
        <span class="block text-sm font-semibold">{{ $account['bank'] }}</span>
        <span class="block text-sm text-muted">{{ $account['holder'] }}</span>
    </span>
    <span class="flex items-center gap-2" x-data="copyCode(@alpine($account['iban']))">
        <span class="font-mono text-sm tracking-wide tabular-nums">{{ $iban }}</span>
        <button type="button" class="shrink-0 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-primary ring-1 ring-line hover:bg-surface" @click="copy()">
            <span x-show="!copied">Kopyala</span><span x-show="copied" x-cloak>Kopyalandı</span>
        </button>
    </span>
</li>
