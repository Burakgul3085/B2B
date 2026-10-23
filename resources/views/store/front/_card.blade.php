{{-- Vitrin ürün kartı: $product (brand, primaryImage, cardImages, activeVariants yüklü). Rozetler yalnızca gerçek veriden. --}}
@php
    use App\Domain\Support\Money;

    $variant = $product->cheapestVariant();
    $inStock = $product->inStock();
    $isNew = $product->published_at && $product->published_at->gt(now()->subDays(30));
    $url = route('store.product', $product->slug);
    $stockLeft = (int) $product->activeVariants->sum('stock_quantity');
    $second = $product->relationLoaded('cardImages') ? $product->cardImages->get(1) : null;
@endphp
<article class="group relative flex flex-col rounded-2xl border border-line bg-white p-2.5 transition hover:-translate-y-0.5 hover:border-transparent hover:shadow-xl hover:shadow-navy/10 sm:p-3">
    <a href="{{ $url }}" class="relative block aspect-square overflow-hidden rounded-xl bg-[#fff]">
        @if ($product->primaryImage)
            <span @class(['absolute inset-0 transition duration-500', 'group-hover:opacity-0' => $second])>
                <x-product-image :src="route('store.media.product', ['storeImage' => $product->primaryImage, 'w' => 640])" :alt="$product->name" zoom />
            </span>
            @if ($second)
                <img src="{{ route('store.media.product', ['storeImage' => $second, 'w' => 640]) }}" alt="" loading="lazy" class="absolute inset-0 size-full object-contain p-2 opacity-0 transition duration-500 group-hover:opacity-100">
            @endif
        @else
            <span class="flex size-full items-center justify-center bg-surface"><x-icon name="image" class="size-10 text-slate-300" /></span>
        @endif
        <span class="pointer-events-none absolute top-2 left-2 flex flex-col items-start gap-1.5">
            @if ($variant?->hasDiscount())<span class="rounded-lg bg-puzzle-orange px-2 py-1 text-[11px] font-extrabold text-white shadow-sm">%{{ $variant->discountPercent() }}</span>@endif
            @if ($isNew)<span class="rounded-lg bg-navy px-2 py-1 text-[11px] font-bold text-white shadow-sm">Yeni</span>@endif
        </span>
        @if (! $inStock)
            <span class="absolute inset-x-2 bottom-2 rounded-lg bg-white/90 py-1.5 text-center text-xs font-semibold text-muted ring-1 ring-line">Tükendi</span>
        @elseif ($stockLeft <= 5)
            <span class="absolute inset-x-2 bottom-2 rounded-lg bg-white/90 py-1.5 text-center text-xs font-bold text-puzzle-orange ring-1 ring-puzzle-orange/30">Son {{ $stockLeft }} ürün</span>
        @endif
    </a>
    <div class="flex flex-1 flex-col px-1 pt-3">
        @if ($product->brand)<p class="truncate text-[11px] font-bold tracking-[0.12em] text-muted uppercase">{{ $product->brand->name }}</p>@endif
        <h3 class="mt-1 line-clamp-2 min-h-[2.5rem] text-sm leading-snug font-medium"><a href="{{ $url }}" class="hover:text-navy-ink">{{ $product->name }}</a></h3>
        @if ($variant)
            <div class="mt-auto flex items-end justify-between gap-2 pt-3">
                <p class="leading-tight">
                    @if ($variant->hasDiscount())<span class="block text-xs text-muted line-through tabular-nums">{{ Money::format($variant->compare_at_price) }}</span>@endif
                    <span @class(['font-display text-lg font-extrabold tabular-nums', 'text-puzzle-orange' => $variant->hasDiscount()])>{{ Money::format($variant->price) }}</span>
                    @if ($product->hasPriceRange())<span class="block text-[11px] text-muted">başlayan fiyatlarla</span>@endif
                </p>
                @if ($inStock)
                    {{-- Tek seçenekli ürün doğrudan sepete eklenir; seçenekli ürünlerde seçim ürün sayfasında yapılır. --}}
                    @if ($product->activeVariants->count() === 1)
                        <form method="POST" action="{{ route('store.cart.add') }}" x-data="addToCart" @submit.prevent="submit">
                            @csrf
                            <input type="hidden" name="variant_id" value="{{ $variant->id }}">
                            <button class="flex size-10 items-center justify-center rounded-xl bg-navy text-white shadow-sm transition hover:bg-puzzle-orange disabled:opacity-60" :disabled="busy" aria-label="{{ $product->name }} sepete ekle"><x-icon name="cart" class="size-[18px]" /></button>
                        </form>
                    @else
                        <a href="{{ $url }}" class="flex size-10 items-center justify-center rounded-xl border border-line text-ink transition hover:border-navy hover:bg-navy hover:text-white" aria-label="{{ $product->name }} seçenekleri"><x-icon name="arrow-right" class="size-4" /></a>
                    @endif
                @endif
            </div>
        @endif
    </div>
</article>
