{{-- Bayiye fiyat: işletmenin KDV dahil/hariç gösterim ayarına göre. --}}
@props(['price', 'display' => 'excl_vat', 'size' => 'md'])
@php
    use App\Domain\Support\Money;
    /** @var \App\Domain\Pricing\ResolvedPrice $price */
    $inclusive = $display === 'incl_vat';
    $shown = $inclusive ? $price->unitPriceWithTax() : $price->unitPrice;
    $before = $inclusive
        ? \App\Domain\Support\Decimal::round(bcmul($price->basePrice, bcadd('1', bcdiv($price->taxRate, '100', 6), 6), 10), 4)
        : $price->basePrice;
@endphp
<div {{ $attributes }}>
    @if ($price->hasDiscount())
        <span class="text-xs text-muted line-through tabular-nums">{{ Money::formatUnit($before) }}</span>
        <span class="ml-1 rounded bg-success-soft px-1.5 py-0.5 text-[11px] font-semibold text-success">%{{ rtrim(rtrim(number_format((float) $price->discountPercent, 2, ',', ''), '0'), ',') }}</span>
    @endif
    <p @class(['font-semibold text-ink tabular-nums', 'text-lg' => $size === 'md', 'text-2xl' => $size === 'lg'])>
        {{ Money::formatUnit($shown) }}
        <span class="text-xs font-normal whitespace-nowrap text-muted">{{ $inclusive ? 'KDV dahil' : '+ KDV %'.rtrim(rtrim(number_format((float) $price->taxRate, 2, ',', ''), '0'), ',') }}</span>
    </p>
</div>
