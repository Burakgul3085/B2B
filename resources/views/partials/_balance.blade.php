{{-- Bakiye: işaretsiz tutar + B (borç) / A (alacak) etiketi. $amount: borç - alacak --}}
@php $cmp = bccomp((string) $amount, '0', 2); @endphp
<span class="inline-flex items-center justify-end gap-1.5 whitespace-nowrap {{ $cmp < 0 ? 'text-emerald-700' : '' }}">
    {{ \App\Domain\Support\Money::format(ltrim((string) $amount, '-')) }}
    @if ($cmp !== 0)
        <span @class([
            'inline-flex size-5 items-center justify-center rounded text-[10px] font-bold',
            'bg-slate-100 text-ink' => $cmp > 0,
            'bg-emerald-100 text-emerald-800' => $cmp < 0,
        ]) title="{{ $cmp > 0 ? 'Borç' : 'Alacak' }}">{{ $cmp > 0 ? 'B' : 'A' }}</span>
    @endif
</span>
