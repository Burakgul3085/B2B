{{-- Izgara ürün kartı: $product (available_qty seçili), $prices, $settings, $openRequests --}}
@php
    use App\Domain\Catalog\StockLevel;
    use App\Domain\Inventory\Quantity;
    $taxLabel = fn ($rate) => '%'.rtrim(rtrim(number_format((float) $rate, 2, ',', ''), '0'), ',');
@endphp
@php
    $level = StockLevel::for((string) $product->available_qty, (string) $product->critical_stock_level, $settings->stock_display, $product->unit->name);
    $price = $prices[$product->id];
@endphp
<article class="group flex flex-col overflow-hidden rounded-2xl border border-line bg-white shadow-xs transition duration-200 hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-lg">
    <div class="relative">
        <a href="{{ route('dealer.catalog.show', $product) }}" class="flex aspect-square items-center justify-center overflow-hidden bg-linear-to-b from-white to-slate-50">
            @if ($product->primaryImage)
                <x-product-image :src="route('dealer.images.show', $product->primaryImage)" :alt="$product->name" zoom />
            @else
                <x-icon name="image" class="size-10 text-slate-300" />
            @endif
        </a>
        <div class="pointer-events-none absolute inset-x-3 top-3 flex items-start justify-between gap-2">
            <div class="flex flex-col items-start gap-1">
                <x-badge :tone="$level['tone']">{{ $level['label'] }}</x-badge>
                @if ($price->hasDiscount())
                    <span class="rounded-md bg-danger px-1.5 py-0.5 text-[11px] font-bold text-white">%{{ rtrim(rtrim(number_format((float) $price->discountPercent, 2, ',', ''), '0'), ',') }} indirim</span>
                @endif
            </div>
            <span class="rounded-md border border-emerald-200 bg-white/90 px-1.5 py-0.5 text-[11px] font-semibold text-emerald-700 backdrop-blur">+KDV {{ $taxLabel($product->taxRate?->rate) }}</span>
        </div>
        <a href="{{ route('dealer.catalog.show', $product) }}" class="absolute right-3 bottom-3 flex size-9 items-center justify-center rounded-full border border-line bg-white text-muted opacity-0 shadow-sm transition group-hover:opacity-100 hover:text-primary focus:opacity-100" aria-label="{{ $product->name }} ürününü incele">
            <x-icon name="eye" class="size-4" />
        </a>
    </div>

    <div class="flex flex-1 flex-col border-t border-line p-4">
        <p class="text-[11px] font-semibold tracking-wider text-muted uppercase">{{ $product->brand?->name }}</p>
        <a href="{{ route('dealer.catalog.show', $product) }}" class="mt-1 line-clamp-2 min-h-[2.75rem] font-semibold leading-snug text-ink hover:text-primary">{{ $product->name }}</a>
        <p class="mt-1 font-mono text-xs text-muted">{{ $product->sku }}@if ((float) $product->pack_quantity > 1)<span class="font-sans"> · Koli {{ Quantity::format($product->pack_quantity) }}</span>@endif</p>
        <div class="mt-3 flex items-end justify-between gap-2">
            <x-price-tag :price="$price" :display="$settings->price_display" />
            <span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-medium text-muted">{{ $product->unit->name }}</span>
        </div>
        <div class="mt-auto pt-4">
            @include('dealer.catalog._add-form', ['product' => $product, 'level' => $level, 'hasOpenRequest' => $openRequests->has($product->id)])
        </div>
    </div>
</article>
