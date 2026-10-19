{{-- Izgara ürün kartı: $product (available_qty seçili), $prices, $settings, $openRequests --}}
@php
    use App\Domain\Catalog\NewProducts;
    use App\Domain\Catalog\StockLevel;
    use App\Domain\Inventory\Quantity;

    $level = StockLevel::for((string) $product->available_qty, (string) $product->critical_stock_level, $settings->stock_display, $product->unit->name);
    $price = $prices[$product->id];
    $dot = ['success' => 'bg-emerald-500', 'warning' => 'bg-amber-500', 'danger' => 'bg-red-500'][$level['tone']] ?? 'bg-slate-400';
    $text = ['success' => 'text-emerald-700', 'warning' => 'text-amber-700', 'danger' => 'text-red-700'][$level['tone']] ?? 'text-muted';
@endphp
<article class="group flex flex-col rounded-2xl border border-line bg-white p-3 shadow-xs transition duration-200 hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-xl hover:shadow-slate-900/5">
    <div class="relative">
        <a href="{{ route('dealer.catalog.show', $product) }}" class="flex aspect-[5/4] items-center justify-center overflow-hidden rounded-xl bg-slate-50">
            @if ($product->primaryImage)
                <x-product-image :src="route('dealer.images.show', $product->primaryImage)" :alt="$product->name" zoom />
            @else
                <x-icon name="image" class="size-10 text-slate-300" />
            @endif
        </a>
        {{-- Rozetler: yalnızca "Yeni" ve indirim (stok ve KDV bilgisi aşağıda) --}}
        <div class="pointer-events-none absolute top-3 left-3 flex flex-col items-start gap-1.5">
            @if (NewProducts::isNew($product, $settings))
                <span class="rounded-full bg-violet-600 px-2.5 py-1 text-[10px] font-bold tracking-wider text-white uppercase shadow-sm">Yeni</span>
            @endif
            @if ($price->hasDiscount())
                <span class="rounded-full bg-red-600 px-2.5 py-1 text-[10px] font-bold tracking-wide text-white shadow-sm">%{{ rtrim(rtrim(number_format((float) $price->discountPercent, 2, ',', ''), '0'), ',') }} indirim</span>
            @endif
        </div>
        <a href="{{ route('dealer.catalog.show', $product) }}" class="absolute right-3 bottom-3 flex size-9 items-center justify-center rounded-full bg-white/95 text-muted opacity-0 shadow-md ring-1 ring-line transition group-hover:opacity-100 hover:text-primary focus:opacity-100" aria-label="{{ $product->name }} ürününü incele">
            <x-icon name="eye" class="size-4" />
        </a>
    </div>

    <div class="flex flex-1 flex-col px-1.5 pt-4 pb-1">
        <div class="flex items-center justify-between gap-2">
            <p class="truncate text-[11px] font-bold tracking-wider text-muted uppercase">{{ $product->brand?->name }}</p>
            <span class="inline-flex shrink-0 items-center gap-1.5 text-xs font-medium {{ $text }}"><span class="size-1.5 rounded-full {{ $dot }}"></span>{{ $level['label'] }}</span>
        </div>
        <a href="{{ route('dealer.catalog.show', $product) }}" class="mt-1.5 line-clamp-2 min-h-[2.75rem] font-semibold leading-snug text-ink transition hover:text-primary">{{ $product->name }}</a>
        <p class="mt-1 text-xs text-muted"><span class="font-mono">{{ $product->sku }}</span> · {{ $product->unit->name }}@if ((float) $product->pack_quantity > 1) · Koli {{ Quantity::format($product->pack_quantity) }}@endif</p>
        <x-price-tag :price="$price" :display="$settings->price_display" class="mt-3" />
        <div class="mt-auto pt-4">
            @include('dealer.catalog._add-form', ['product' => $product, 'level' => $level, 'hasOpenRequest' => $openRequests->has($product->id)])
        </div>
    </div>
</article>
