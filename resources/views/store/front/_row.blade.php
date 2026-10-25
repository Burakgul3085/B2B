{{-- Liste görünümü satırı: $product (_card ile aynı ilişkiler yüklü). Fiyat ve stok sunucudaki veriden. --}}
@php
    use App\Domain\Store\Storefront\Catalog;
    use App\Domain\Support\Money;

    $variant = $product->cheapestVariant();
    $inStock = $product->inStock();
    $stockLeft = (int) $product->activeVariants->sum('stock_quantity');
    $url = route('store.product', $product->slug);
    $single = $product->activeVariants->count() === 1;
@endphp
<article class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center">
    <a href="{{ $url }}" class="flex size-24 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-line bg-[#fff]">
        @if ($product->primaryImage)
            <x-product-image :src="route('store.media.product', ['storeImage' => $product->primaryImage, 'w' => 320])" :alt="$product->name" />
        @else
            <x-icon name="image" class="size-7 text-slate-300" />
        @endif
    </a>
    <div class="min-w-0 flex-1">
        @if ($product->brand)<p class="text-[11px] font-bold tracking-[0.12em] text-muted uppercase">{{ $product->brand->name }}</p>@endif
        <a href="{{ $url }}" class="font-semibold text-ink hover:text-navy-ink">{{ $product->name }}</a>
        @if ($single && $variant?->sku)<p class="mt-0.5 font-mono text-xs text-muted">{{ $variant->sku }}</p>@endif
        <div class="mt-2 flex flex-wrap gap-1.5">
            @if (! $inStock)
                <x-badge tone="neutral">Tükendi</x-badge>
            @elseif ($stockLeft <= 5)
                <x-badge tone="warning">Son {{ $stockLeft }} ürün</x-badge>
            @else
                <x-badge tone="success">Stokta</x-badge>
            @endif
            @if (Catalog::isNew($product))<span class="rounded-md bg-navy px-1.5 py-0.5 text-[11px] font-bold tracking-wide text-white uppercase">Yeni</span>@endif
            @if ($variant?->hasDiscount())<span class="rounded-md bg-puzzle-orange px-1.5 py-0.5 text-[11px] font-extrabold text-white">%{{ $variant->discountPercent() }} indirim</span>@endif
            @if ($campaign = \App\Domain\Store\Discounts\CampaignBadges::for($product, $variant?->price))<span class="rounded-md bg-green-600 px-1.5 py-0.5 text-[11px] font-bold text-white">{{ $campaign }}</span>@endif
            @if (! $single)<span class="rounded-md border border-line px-1.5 py-0.5 text-[11px] font-semibold text-muted">{{ $product->activeVariants->count() }} seçenek</span>@endif
        </div>
    </div>
    @if ($variant)
        <p class="leading-tight sm:w-36 sm:text-right">
            @if ($variant->hasDiscount())<span class="block text-xs text-muted line-through tabular-nums">{{ Money::format($variant->compare_at_price) }}</span>@endif
            <span @class(['font-display text-xl font-extrabold tabular-nums', 'text-puzzle-orange' => $variant->hasDiscount()])>{{ Money::format($variant->price) }}</span>
            @if ($product->hasPriceRange())<span class="block text-[11px] text-muted">başlayan fiyatlarla</span>@endif
        </p>
        <div class="sm:w-44">
            @if (! $inStock)
                <a href="{{ $url }}" class="flex h-10 w-full items-center justify-center rounded-xl border border-line text-sm font-semibold text-muted transition hover:border-navy hover:text-ink">Ürünü incele</a>
            @elseif ($single)
                <form method="POST" action="{{ route('store.cart.add') }}" x-data="addToCart" @submit.prevent="submit">
                    @csrf
                    <input type="hidden" name="variant_id" value="{{ $variant->id }}">
                    <button class="flex h-10 w-full items-center justify-center gap-2 rounded-xl bg-navy text-sm font-semibold text-white shadow-sm transition hover:bg-puzzle-orange disabled:opacity-60" :disabled="busy">
                        <x-icon name="cart" class="size-4" /> Sepete ekle
                    </button>
                </form>
            @else
                <a href="{{ $url }}" class="flex h-10 w-full items-center justify-center gap-2 rounded-xl border border-line text-sm font-semibold transition hover:border-navy hover:bg-navy hover:text-white">Seçenekleri gör <x-icon name="arrow-right" class="size-4" /></a>
            @endif
        </div>
    @endif
</article>
