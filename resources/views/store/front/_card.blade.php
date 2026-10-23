{{-- Vitrin ürün kartı: $product (brand, primaryImage, activeVariants yüklü) --}}
@php
    use App\Domain\Support\Money;

    $variant = $product->cheapestVariant();
    $inStock = $product->inStock();
    $isNew = $product->published_at && $product->published_at->gt(now()->subDays(30));
    $url = route('store.product', $product->slug);
@endphp
<article class="group relative flex flex-col">
    <a href="{{ $url }}" class="relative block aspect-square overflow-hidden rounded-2xl border border-line bg-white">
        @if ($product->primaryImage)
            <x-product-image :src="route('store.media.product', ['storeImage' => $product->primaryImage, 'w' => 640])" :alt="$product->name" zoom />
        @else
            <span class="flex size-full items-center justify-center"><x-icon name="image" class="size-10 text-slate-300" /></span>
        @endif
        <span class="pointer-events-none absolute top-2.5 left-2.5 flex flex-col items-start gap-1.5">
            @if ($variant?->hasDiscount())<span class="rounded-full bg-red-600 px-2.5 py-1 text-[11px] font-bold text-white shadow-sm">%{{ $variant->discountPercent() }}</span>@endif
            @if ($isNew)<span class="rounded-full bg-primary px-2.5 py-1 text-[11px] font-bold text-white shadow-sm">Yeni</span>@endif
        </span>
        @unless ($inStock)
            <span class="absolute inset-x-2.5 bottom-2.5 rounded-lg bg-white/90 py-1.5 text-center text-xs font-semibold text-muted ring-1 ring-line">Tükendi</span>
        @endunless
    </a>
    <div class="flex flex-1 flex-col pt-3">
        @if ($product->brand)<p class="truncate text-[11px] font-semibold tracking-wider text-muted uppercase">{{ $product->brand->name }}</p>@endif
        <h3 class="mt-1 line-clamp-2 text-sm leading-snug font-medium"><a href="{{ $url }}" class="hover:text-primary">{{ $product->name }}</a></h3>
        @if ($variant)
            <div class="mt-auto flex items-end justify-between gap-2 pt-2">
                <p class="flex flex-wrap items-baseline gap-x-2">
                    <span class="text-base font-bold tabular-nums">{{ Money::format($variant->price) }}</span>
                    @if ($variant->hasDiscount())<span class="text-xs text-muted line-through tabular-nums">{{ Money::format($variant->compare_at_price) }}</span>@endif
                    @if ($product->hasPriceRange())<span class="w-full text-xs text-muted">başlayan fiyatlarla</span>@endif
                </p>
                @if ($inStock)
                    {{-- Tek seçenekli ürün doğrudan sepete eklenir; seçenekli ürünlerde seçim ürün sayfasında yapılır. --}}
                    @if ($product->activeVariants->count() === 1)
                        <form method="POST" action="{{ route('store.cart.add') }}" x-data="addToCart" @submit.prevent="submit">
                            @csrf
                            <input type="hidden" name="variant_id" value="{{ $variant->id }}">
                            <button class="flex size-9 items-center justify-center rounded-full bg-primary text-white shadow-sm transition hover:bg-primary-hover" aria-label="{{ $product->name }} sepete ekle"><x-icon name="cart" class="size-4" /></button>
                        </form>
                    @else
                        <a href="{{ $url }}" class="flex size-9 items-center justify-center rounded-full border border-line text-ink transition hover:border-primary hover:text-primary" aria-label="{{ $product->name }} seçenekleri"><x-icon name="arrow-right" class="size-4" /></a>
                    @endif
                @endif
            </div>
        @endif
    </div>
</article>
