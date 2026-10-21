@php
    $storeName = config('store.name');
    $orgJson = ['@context' => 'https://schema.org', '@type' => 'Store', 'name' => $storeName, 'url' => route('store.home')];
    $siteJson = ['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => $storeName, 'url' => route('store.home'),
        'potentialAction' => ['@type' => 'SearchAction', 'target' => route('store.search').'?q={search_term_string}', 'query-input' => 'required name=search_term_string']];
@endphp
<x-layouts.store :canonical="route('store.home')" :json-ld="[$orgJson, $siteJson]">
    {{-- Banner'lar --}}
    @if ($banners->isNotEmpty())
        <section class="mx-auto max-w-7xl px-4 pt-6 sm:px-6 lg:px-8" aria-label="Kampanyalar"
            x-data="{ i: 0, n: {{ $banners->count() }}, timer: null, start() { if (this.n > 1) this.timer = setInterval(() => this.i = (this.i + 1) % this.n, 6000) }, stop() { clearInterval(this.timer) } }"
            x-init="start()" @mouseenter="stop()" @mouseleave="start()">
            <div class="relative overflow-hidden rounded-3xl bg-slate-100">
                @foreach ($banners as $index => $banner)
                    @php $tag = $banner->link_url ? 'a' : 'div'; @endphp
                    <{{ $tag }} @if ($banner->link_url) href="{{ $banner->link_url }}" @endif x-show="i === {{ $index }}" @if ($index) x-cloak @endif
                        x-transition:enter="transition duration-500" x-transition:enter-start="opacity-0" class="relative block">
                        <picture>
                            @if ($banner->mobile_image_path)<source media="(max-width: 640px)" srcset="{{ route('store.media.banner', ['storeBanner' => $banner, 'mobil' => 1]) }}">@endif
                            <img src="{{ route('store.media.banner', $banner) }}" alt="{{ $banner->title ?? $storeName }}" class="aspect-[4/5] w-full object-cover sm:aspect-[21/8]"
                                @if ($index) loading="lazy" @else fetchpriority="high" @endif>
                        </picture>
                        @if ($banner->title || $banner->subtitle)
                            <span class="absolute inset-0 flex items-end bg-linear-to-t from-slate-950/60 via-slate-950/10 to-transparent p-6 sm:items-center sm:bg-linear-to-r sm:p-12">
                                <span class="max-w-lg text-white">
                                    @if ($banner->title)<span class="block text-2xl leading-tight font-bold sm:text-4xl">{{ $banner->title }}</span>@endif
                                    @if ($banner->subtitle)<span class="mt-2 block text-sm text-white/85 sm:text-base">{{ $banner->subtitle }}</span>@endif
                                    @if ($banner->button_text && $banner->link_url)<span class="mt-5 inline-flex rounded-full bg-white px-5 py-2.5 text-sm font-semibold text-ink">{{ $banner->button_text }}</span>@endif
                                </span>
                            </span>
                        @endif
                    </{{ $tag }}>
                @endforeach
                @if ($banners->count() > 1)
                    <div class="absolute inset-x-0 bottom-3 flex justify-center gap-2">
                        @foreach ($banners as $index => $banner)
                            <button type="button" @click="i = {{ $index }}" class="h-2 rounded-full bg-white/60 transition-all" :class="i === {{ $index }} ? 'w-6 bg-white' : 'w-2'" aria-label="Banner {{ $index + 1 }}"></button>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    @endif

    <div class="mx-auto max-w-7xl space-y-16 px-4 py-10 sm:px-6 lg:px-8">
        {{-- Kategoriler --}}
        @if ($categories->isNotEmpty())
            <section aria-labelledby="kategoriler">
                <h2 id="kategoriler" class="text-xl font-bold tracking-tight">Kategoriler</h2>
                <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    @foreach ($categories as $root)
                        <a href="{{ route('store.category', $root->slug) }}" class="group flex flex-col justify-between rounded-2xl border border-line bg-surface p-4 transition hover:border-primary/40 hover:bg-primary-soft">
                            <span class="flex size-10 items-center justify-center rounded-xl bg-white text-lg font-bold text-primary shadow-xs">{{ mb_substr($root->name, 0, 1) }}</span>
                            <span class="mt-6 text-sm font-semibold group-hover:text-primary">{{ $root->name }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        @foreach ([['one-cikanlar', 'Öne çıkan ürünler', $featured, route('store.products')], ['yeniler', 'Yeni ürünler', $newest, route('store.products', ['sirala' => 'yeni'])]] as [$id, $heading, $items, $more])
            @if ($items->isNotEmpty())
                <section aria-labelledby="{{ $id }}">
                    <div class="flex items-end justify-between gap-4">
                        <h2 id="{{ $id }}" class="text-xl font-bold tracking-tight">{{ $heading }}</h2>
                        <a href="{{ $more }}" class="text-sm font-medium text-primary hover:underline">Tümünü gör</a>
                    </div>
                    <div class="mt-5 grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 lg:grid-cols-4">
                        @foreach ($items as $product)
                            @include('store.front._card', ['product' => $product])
                        @endforeach
                    </div>
                </section>
            @endif
        @endforeach

        @if ($featured->isEmpty() && $newest->isEmpty())
            <div class="rounded-2xl border border-dashed border-line px-6 py-16 text-center text-muted">Ürünlerimiz çok yakında burada.</div>
        @endif

        {{-- Markalar --}}
        @if ($brands->isNotEmpty())
            <section aria-labelledby="markalar">
                <div class="flex items-end justify-between gap-4">
                    <h2 id="markalar" class="text-xl font-bold tracking-tight">Markalar</h2>
                    <a href="{{ route('store.brands') }}" class="text-sm font-medium text-primary hover:underline">Tüm markalar</a>
                </div>
                <div class="mt-5 grid grid-cols-3 gap-3 sm:grid-cols-4 lg:grid-cols-8">
                    @foreach ($brands as $brand)
                        <a href="{{ route('store.brand', $brand->slug) }}" class="flex aspect-[3/2] items-center justify-center rounded-xl border border-line bg-white p-3 transition hover:border-primary/40" title="{{ $brand->name }}">
                            <img src="{{ route('store.media.brand', $brand) }}?v={{ $brand->updated_at?->timestamp }}" alt="{{ $brand->name }}" class="max-h-full max-w-full object-contain" loading="lazy">
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-layouts.store>
