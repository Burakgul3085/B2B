@php
    use App\Domain\Support\Money;

    $days = $promotion->ends_at ? (int) now('Europe/Istanbul')->startOfDay()->diffInDays($promotion->ends_at->timezone('Europe/Istanbul')->startOfDay()) : null;
    $names = collect($promotion->category_ids ?? [])->map(fn ($id) => $categories[$id] ?? null)->filter();
    $images = $promotion->images;
    $catalogUrl = route('dealer.catalog', ['kampanya' => $promotion->id]);
@endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Dealer" :title="$promotion->name" wide>
    @error('cart')<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror
    @error('request')<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror

    <x-page-header :title="$promotion->name" :subtitle="$promotion->summary()">
        <x-slot:breadcrumb><a href="{{ route('dealer.promotions.index') }}" class="hover:underline">Kampanyalar</a></x-slot:breadcrumb>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-5">
        {{-- ============ Galeri ============ --}}
        <div class="lg:col-span-3">
            @if ($images->isEmpty())
                <div class="group relative aspect-video overflow-hidden rounded-2xl border border-line shadow-xs">
                    @include('dealer.promotions._cover', ['width' => 1000])
                </div>
            @else
                <div x-data="gallery({{ $images->count() }})" @keydown.window="key($event)">
                    <div class="group relative aspect-video overflow-hidden rounded-2xl border border-line bg-slate-100 shadow-xs">
                        @foreach ($images as $i => $image)
                            <button type="button" x-show="active === {{ $i }}" @if ($i) x-cloak @endif @click="open = true" class="block size-full cursor-zoom-in" aria-label="Fotoğrafı büyüt">
                                <img src="{{ route('dealer.promotions.image', ['promotionImage' => $image, 'w' => 1000]) }}" alt="{{ $promotion->name }} {{ $i + 1 }}" class="size-full object-cover" @if ($i) loading="lazy" @endif>
                            </button>
                        @endforeach
                        <span class="pointer-events-none absolute bottom-3 left-3 rounded-xl bg-red-600 px-3 py-1.5 text-lg font-extrabold text-white shadow-lg">{{ $promotion->badge() }}</span>
                        @if ($images->count() > 1)
                            <button type="button" @click="prev()" class="absolute top-1/2 left-3 flex size-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-ink shadow-md hover:bg-white" aria-label="Önceki fotoğraf"><x-icon name="arrow-right" class="size-5 rotate-180" /></button>
                            <button type="button" @click="next()" class="absolute top-1/2 right-3 flex size-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-ink shadow-md hover:bg-white" aria-label="Sonraki fotoğraf"><x-icon name="arrow-right" class="size-5" /></button>
                        @endif
                    </div>
                    @if ($images->count() > 1)
                        <div class="mt-3 flex gap-2 overflow-x-auto pb-1">
                            @foreach ($images as $i => $image)
                                <button type="button" @click="show({{ $i }})" class="size-20 shrink-0 overflow-hidden rounded-lg border-2" :class="active === {{ $i }} ? 'border-primary' : 'border-line'" aria-label="Fotoğraf {{ $i + 1 }}">
                                    <img src="{{ route('dealer.promotions.image', ['promotionImage' => $image, 'w' => 320]) }}" alt="" class="size-full object-cover" loading="lazy">
                                </button>
                            @endforeach
                        </div>
                    @endif

                    {{-- Tam ekran --}}
                    <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/90 p-4" @click.self="open = false" role="dialog" aria-modal="true" aria-label="{{ $promotion->name }} fotoğrafları">
                        @foreach ($images as $i => $image)
                            <img x-show="active === {{ $i }}" x-cloak src="{{ route('dealer.promotions.image', ['promotionImage' => $image]) }}" alt="{{ $promotion->name }} {{ $i + 1 }}" class="max-h-full max-w-full rounded-lg object-contain" loading="lazy">
                        @endforeach
                        <button type="button" @click="open = false" class="absolute top-4 right-4 flex size-10 items-center justify-center rounded-full bg-white/15 text-white hover:bg-white/25" aria-label="Kapat"><x-icon name="x" class="size-5" /></button>
                        @if ($images->count() > 1)
                            <button type="button" @click="prev()" class="absolute top-1/2 left-4 flex size-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/15 text-white hover:bg-white/25" aria-label="Önceki fotoğraf"><x-icon name="arrow-right" class="size-6 rotate-180" /></button>
                            <button type="button" @click="next()" class="absolute top-1/2 right-4 flex size-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/15 text-white hover:bg-white/25" aria-label="Sonraki fotoğraf"><x-icon name="arrow-right" class="size-6" /></button>
                        @endif
                    </div>
                </div>
            @endif

            @if ($promotion->description)
                <x-card title="Kampanya hakkında" class="mt-6"><p class="text-sm leading-relaxed whitespace-pre-line text-slate-700">{{ $promotion->description }}</p></x-card>
            @endif
        </div>

        {{-- ============ Koşullar ============ --}}
        <div class="space-y-6 lg:col-span-2">
            <x-card>
                <p class="inline-flex items-center gap-1.5 text-xs font-bold tracking-wide text-primary uppercase"><x-icon name="gift" class="size-3.5" />{{ $promotion->isCoupon() ? 'Kupon' : 'Otomatik kampanya' }}</p>
                <p class="mt-2 text-2xl font-extrabold tracking-tight text-ink">{{ $promotion->summary() }}</p>
                @if ($days !== null)
                    <p @class(['mt-2 inline-flex rounded-full px-2.5 py-1 text-xs font-bold', 'bg-red-600 text-white' => $days <= 3, 'bg-slate-100 text-slate-700' => $days > 3])>{{ $days === 0 ? 'Bugün son gün' : $days.' gün kaldı' }}</p>
                @endif

                <dl class="mt-5 space-y-2.5 border-t border-line pt-4 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-muted">En düşük tutar</dt><dd class="text-right font-medium">{{ $promotion->min_subtotal ? Money::format($promotion->min_subtotal).' (KDV hariç)' : 'Yok' }}</dd></div>
                    @if ($promotion->type === 'percent' && $promotion->max_discount)
                        <div class="flex justify-between gap-3"><dt class="text-muted">En fazla indirim</dt><dd class="font-medium">{{ Money::format($promotion->max_discount) }}</dd></div>
                    @endif
                    <div class="flex justify-between gap-3"><dt class="text-muted">Başlangıç</dt><dd class="font-medium">{{ $promotion->starts_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i') ?? 'Başladı' }}</dd></div>
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

                @if ($promotion->isCoupon())
                    <div class="mt-5 rounded-xl border border-dashed border-primary/50 bg-primary-soft/30 p-4">
                        <p class="text-xs font-medium text-muted">Kupon kodu</p>
                        <p class="mt-1 font-mono text-xl font-bold tracking-wider text-primary">{{ $promotion->code }}</p>
                        <form method="POST" action="{{ route('dealer.cart.promotion') }}" class="mt-3">
                            @csrf
                            <input type="hidden" name="code" value="{{ $promotion->code }}">
                            <input type="hidden" name="to_cart" value="1">
                            <x-button class="w-full" icon="check">Sepete uygula</x-button>
                        </form>
                    </div>
                @else
                    <p class="mt-5 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-900">Kod gerekmez: koşulu tutan siparişinize sepette kendiliğinden uygulanır.</p>
                @endif
                <p class="mt-3 text-xs text-muted">İndirim KDV hariç tutardan düşülür. Siparişte tek kupon veya kampanya geçerlidir; toptancınız daha az miktar onaylarsa indirim onaylanan tutara göre yeniden hesaplanır.</p>
            </x-card>
        </div>
    </div>

    {{-- ============ Kampanyadaki ürünler ============ --}}
    @if ($promotion->isScoped())
        <section class="mt-14">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="inline-flex items-center gap-2 text-xs font-bold tracking-[0.2em] text-primary uppercase"><span class="size-1.5 rounded-full bg-amber-400"></span>Kampanya ürünleri</p>
                    <h2 class="mt-2 text-2xl font-bold tracking-tight text-ink">Bu kampanyadaki ürünler</h2>
                </div>
                @if ($productTotal > $products->count())
                    <a href="{{ $catalogUrl }}" class="inline-flex items-center gap-1.5 rounded-full border border-line bg-white px-4 py-2.5 text-sm font-semibold text-ink shadow-xs transition hover:border-primary/40 hover:text-primary">
                        Tümünü gör ({{ number_format($productTotal, 0, ',', '.') }}) <x-icon name="arrow-right" class="size-4" />
                    </a>
                @endif
            </div>
            @if ($products->isEmpty())
                <x-card class="mt-5"><x-empty-state icon="package" title="Şu an sipariş verilebilir ürün yok" text="Kampanya kapsamındaki ürünler satışa açıldığında burada görünür." /></x-card>
            @else
                <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach ($products as $product)
                        @include('dealer.catalog._card', ['product' => $product])
                    @endforeach
                </div>
            @endif
        </section>
    @else
        <div class="mt-10 flex flex-col items-center gap-3 rounded-2xl border border-line bg-white px-6 py-8 text-center shadow-xs">
            <x-icon name="package" class="size-8 text-primary" />
            <p class="text-lg font-semibold text-ink">Tüm ürünlerde geçerli</p>
            <p class="max-w-md text-sm text-muted">Koşulu tutan her siparişte uygulanır. Dilediğiniz ürünü sepete ekleyin.</p>
            <x-button icon="arrow-right" :href="route('dealer.catalog')">Ürünlere göz at</x-button>
        </div>
    @endif
</x-layouts.panel>
