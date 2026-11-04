{{-- Bayiye fiyat: işletmenin KDV dahil/hariç gösterim ayarına göre; indirimli bayi fiyatında indirimsiz fiyat üstü çizili. --}}
@props(['price', 'display' => 'excl_vat', 'size' => 'md'])
@php
    use App\Domain\Support\Money;
    /** @var \App\Domain\Pricing\ResolvedPrice $price */
    $inclusive = $display === 'incl_vat';
    $shown = $inclusive ? $price->unitPriceWithTax() : $price->unitPrice;
    $before = $inclusive ? $price->wasPriceWithTax() : $price->wasPrice;
@endphp
<div {{ $attributes }}>
    @if ($price->hasDiscount())
        <span class="text-xs text-muted line-through tabular-nums">{{ Money::formatUnit($before) }}</span>
        <span class="ml-1 rounded bg-success-soft px-1.5 py-0.5 text-[11px] font-semibold text-success">%{{ $price->savingPercent() }}</span>
    @endif
    <p @class(['font-semibold text-ink tabular-nums', 'text-lg' => $size === 'md', 'text-2xl' => $size === 'lg'])>
        {{ Money::formatUnit($shown) }}
        <span class="text-xs font-normal whitespace-nowrap text-muted">{{ $inclusive ? 'KDV dahil' : '+ KDV %'.rtrim(rtrim(number_format((float) $price->taxRate, 2, ',', ''), '0'), ',') }}</span>
    </p>
</div>
