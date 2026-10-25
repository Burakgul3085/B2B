{{-- Kampanya alanı: büyük kayan banner + yan küçük banner'lar. Banner yoksa marka tanıtımlı açılış alanı. --}}
@php
    $main = $banners['main'];
    $side = $banners['side'];
    $storeName = config('store.name');
    // Ana banner çerçevesi ilk banner'ın kendi oranını alır (aşırı uçlar sınırlanır): görsel kırpılmaz, bant kalmaz.
    // Mobilde varsa mobil görselin oranı kullanılır. Oranı farklı diğer banner'lar çerçeveye bütünüyle sığdırılır.
    $first = $main->first();
    $ratio = fn (?string $path, float $fallback, float $min, float $max) => max($min, min($max, \App\Domain\Store\Storefront\ImageVariants::ratio($path) ?? $fallback));
    $mainRatio = $first ? $ratio($first->image_path, $side->isNotEmpty() ? 16 / 9 : 21 / 8, $side->isNotEmpty() ? 1.4 : 1.8, 3.2) : null;
    $mobileRatio = $first ? ($first->mobile_image_path ? $ratio($first->mobile_image_path, 0.8, 0.6, 1.5) : max(1.0, min(2.0, $mainRatio))) : null;
@endphp
<section class="mx-auto max-w-7xl px-4 pt-6 sm:px-6 lg:px-8" aria-label="Kampanyalar">
    @if ($main->isNotEmpty() || $side->isNotEmpty())
        <div @class(['grid gap-4', 'lg:grid-cols-3' => $side->isNotEmpty()])>
            @if ($main->isNotEmpty())
                <div @class(['relative overflow-hidden rounded-3xl bg-navy', 'lg:col-span-2' => $side->isNotEmpty()])
                    x-data="bannerRotator({{ $main->count() }})" x-init="start()" @mouseenter="stop()" @mouseleave="start()">
                    @foreach ($main as $index => $banner)
                        @php $tag = $banner->link_url ? 'a' : 'div'; @endphp
                        <{{ $tag }} @if ($banner->link_url) href="{{ $banner->link_url }}" @endif x-show="i === {{ $index }}" @if ($index) x-cloak @endif
                            x-transition:enter="transition duration-500" x-transition:enter-start="opacity-0" class="relative block h-full">
                            {{-- Banner kırpılmaz: görsel bütünüyle sığdırılır, oranı alana uymazsa kenarları aynı görselin bulanık hâli doldurur. --}}
                            <span class="relative block aspect-(--banner-m) w-full sm:aspect-(--banner)" style="--banner-m: {{ round($mobileRatio, 4) }}; --banner: {{ round($mainRatio, 4) }}">
                                @foreach ([true, false] as $backdrop)
                                    <picture class="absolute inset-0" @if ($backdrop) aria-hidden="true" @endif>
                                        @if ($banner->mobile_image_path)<source media="(max-width: 640px)" srcset="{{ route('store.media.banner', ['storeBanner' => $banner, 'mobil' => 1]) }}">@endif
                                        <img src="{{ route('store.media.banner', $banner) }}" alt="{{ $backdrop ? '' : ($banner->title ?? $storeName) }}"
                                            @class(['size-full', 'scale-110 object-cover opacity-60 blur-xl' => $backdrop, 'object-contain' => ! $backdrop])
                                            @if ($index) loading="lazy" @elseif (! $backdrop) fetchpriority="high" @endif>
                                    </picture>
                                @endforeach
                            </span>
                            @if ($banner->title || $banner->subtitle)
                                <span class="absolute inset-0 flex items-end bg-linear-to-t from-navy/80 via-navy/20 to-transparent p-6 sm:items-center sm:bg-linear-to-r sm:from-navy/85 sm:via-navy/35 sm:p-12">
                                    <span class="max-w-lg text-white">
                                        @if ($banner->title)<span class="block font-display text-3xl leading-[1.1] font-extrabold sm:text-5xl">{{ $banner->title }}</span>@endif
                                        @if ($banner->subtitle)<span class="mt-3 block text-sm text-white/85 sm:text-lg">{{ $banner->subtitle }}</span>@endif
                                        @if ($banner->button_text && $banner->link_url)
                                            <span class="mt-6 inline-flex items-center gap-2 rounded-xl bg-puzzle-sun px-5 py-3 text-sm font-bold text-navy shadow-lg shadow-black/20">{{ $banner->button_text }} <x-icon name="arrow-right" class="size-4" /></span>
                                        @endif
                                    </span>
                                </span>
                            @endif
                        </{{ $tag }}>
                    @endforeach
                    @if ($main->count() > 1)
                        <div class="absolute inset-x-0 bottom-4 flex justify-center gap-2">
                            @foreach ($main as $index => $banner)
                                <button type="button" @click="i = {{ $index }}" class="h-2 rounded-full bg-white/50 transition-all" :class="i === {{ $index }} ? 'w-7 bg-white' : 'w-2'" aria-label="Banner {{ $index + 1 }}"></button>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
            @if ($side->isNotEmpty())
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
                    @foreach ($side as $banner)
                        @php $tag = $banner->link_url ? 'a' : 'div'; @endphp
                        <{{ $tag }} @if ($banner->link_url) href="{{ $banner->link_url }}" @endif class="group relative block overflow-hidden rounded-3xl bg-navy">
                            <span class="relative block aspect-[3/2] w-full lg:aspect-auto lg:h-full lg:min-h-44">
                                <x-store.fit-image :src="route('store.media.banner', ['storeBanner' => $banner, 'w' => 900])" :alt="$banner->title ?? $storeName" fill />
                            </span>
                            @if ($banner->title)
                                <span class="absolute inset-0 flex flex-col justify-end bg-linear-to-t from-navy/80 to-transparent p-5 text-white">
                                    <span class="font-display text-xl font-extrabold">{{ $banner->title }}</span>
                                    @if ($banner->subtitle)<span class="text-sm text-white/80">{{ $banner->subtitle }}</span>@endif
                                </span>
                            @endif
                        </{{ $tag }}>
                    @endforeach
                </div>
            @endif
        </div>
    @else
        {{-- Banner yokken: marka tanıtımlı açılış --}}
        <div class="relative overflow-hidden rounded-3xl bg-navy px-6 py-14 text-white sm:px-14 sm:py-20">
            <div class="pointer-events-none absolute -top-16 -right-10 hidden size-80 rotate-12 opacity-90 sm:block" aria-hidden="true">
                <div class="grid size-full grid-cols-2 gap-3">
                    <span class="rounded-3xl bg-puzzle-orange"></span><span class="rounded-3xl bg-puzzle-green"></span>
                    <span class="rounded-3xl bg-puzzle-blue"></span><span class="rounded-3xl bg-puzzle-sun"></span>
                </div>
            </div>
            <div class="relative max-w-2xl">
                <p class="text-xs font-bold tracking-[0.25em] text-puzzle-sun uppercase">{{ $storeName }}</p>
                <h1 class="mt-4 font-display text-4xl leading-[1.08] font-extrabold sm:text-6xl">Kırtasiyede aradığınız <span class="text-puzzle-sun">her parça</span> burada.</h1>
                <p class="mt-5 max-w-xl text-lg text-white/75">{{ $settings['footer_about'] ?: 'Okul, ofis ve sanat malzemelerinde orijinal ürünler, uygun fiyatlar ve hızlı teslimat.' }}</p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('store.products') }}" class="inline-flex items-center gap-2 rounded-xl bg-puzzle-sun px-6 py-3.5 font-bold text-navy">Ürünlere göz at <x-icon name="arrow-right" class="size-4" /></a>
                    <a href="{{ route('store.brands') }}" class="inline-flex items-center gap-2 rounded-xl px-6 py-3.5 font-semibold text-white ring-1 ring-white/30 hover:bg-white/10">Markalar</a>
                </div>
            </div>
        </div>
    @endif
</section>
