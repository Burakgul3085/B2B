{{-- Kampanya kartı (Kampanyalar sayfası ve ana sayfa): $promotion, $remaining, $categories (id → ad); isteğe bağlı $compact --}}
@php
    $days = $promotion->ends_at ? (int) now('Europe/Istanbul')->startOfDay()->diffInDays($promotion->ends_at->timezone('Europe/Istanbul')->startOfDay()) : null;
    $names = collect($promotion->category_ids ?? [])->map(fn ($id) => $categories[$id] ?? null)->filter();
    $compact ??= false;
@endphp
<article class="group flex flex-col overflow-hidden rounded-2xl border border-line bg-white shadow-xs transition duration-200 hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-xl hover:shadow-slate-900/5">
    <a href="{{ route('dealer.promotions.show', $promotion) }}" class="relative block aspect-video overflow-hidden">
        @include('dealer.promotions._cover', ['promotion' => $promotion, 'width' => 640])
        @if ($days !== null)
            <span @class(['absolute top-3 right-3 rounded-full px-2.5 py-1 text-xs font-bold shadow-sm', 'bg-red-600 text-white' => $days <= 3, 'bg-white/95 text-ink' => $days > 3])>
                {{ $days === 0 ? 'Bugün son gün' : $days.' gün kaldı' }}
            </span>
        @endif
    </a>

    <div class="flex flex-1 flex-col p-5">
        <p class="inline-flex items-center gap-1.5 text-xs font-bold tracking-wide text-primary uppercase">
            <x-icon name="gift" class="size-3.5" />{{ $promotion->isCoupon() ? 'Kupon' : 'Kampanya' }}
        </p>
        <h2 class="mt-1 text-base font-semibold text-ink"><a href="{{ route('dealer.promotions.show', $promotion) }}" class="hover:text-primary">{{ $promotion->name }}</a></h2>
        <p class="mt-1 text-sm font-semibold text-ink">{{ $promotion->summary() }}</p>
        @if (! $compact && $promotion->description)
            <p class="mt-2 line-clamp-2 text-sm text-slate-700">{{ $promotion->description }}</p>
        @endif

        @unless ($compact)
            <dl class="mt-4 space-y-1.5 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-muted">Bitiş</dt><dd class="font-medium">{{ $promotion->ends_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i') ?? 'Süresiz' }}</dd></div>
                @if ($remaining !== null)
                    <div class="flex justify-between gap-3"><dt class="text-muted">Kalan kullanım hakkınız</dt><dd class="font-medium tabular-nums">{{ $remaining }}</dd></div>
                @endif
                <div class="flex justify-between gap-3"><dt class="text-muted">Geçerli ürünler</dt><dd class="text-right font-medium">
                    @if ($promotion->isScoped())
                        {{ $names->implode(', ') }}{{ $names->isNotEmpty() && $promotion->product_ids ? ' ve ' : '' }}{{ $promotion->product_ids ? count($promotion->product_ids).' seçili ürün' : '' }}
                    @else
                        Tüm ürünler
                    @endif
                </dd></div>
            </dl>
        @endunless

        <div class="mt-auto flex flex-wrap items-center gap-2 pt-5">
            @if ($promotion->isCoupon())
                <span class="rounded-lg border border-dashed border-primary/50 bg-primary-soft/40 px-3 py-1.5 font-mono text-sm font-semibold text-primary">{{ $promotion->code }}</span>
                <form method="POST" action="{{ route('dealer.cart.promotion') }}">
                    @csrf
                    <input type="hidden" name="code" value="{{ $promotion->code }}">
                    <input type="hidden" name="to_cart" value="1">
                    <x-button size="sm">Sepete uygula</x-button>
                </form>
            @endif
            <a href="{{ route('dealer.promotions.show', $promotion) }}" class="ml-auto inline-flex items-center gap-1 text-sm font-semibold text-primary hover:underline">Detay ve ürünler <x-icon name="arrow-right" class="size-3.5" /></a>
        </div>
    </div>
</article>
