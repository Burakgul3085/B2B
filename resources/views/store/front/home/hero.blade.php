{{--
    Kampanya alanı: ekranın iki yanına kadar uzanan kayan banner (3:1) ve altında kampanya kutuları (3:2).
    Çerçeveler yükleme ölçüsüyle aynı orandadır (StoreBanner::SIZES; yüklemede denetlenir): görsel kırpılmaz,
    kenarında boşluk kalmaz. Telefonda dikey (4:5) görsel yalnızca tüm banner'larda mobil görsel varsa kullanılır;
    yoksa masaüstü görseli bütün hâliyle gösterilir. Banner yoksa marka tanıtımlı açılış alanı.
--}}
@php
    $main = $banners['main'];
    $side = $banners['side'];
    $storeName = config('store.name');
    $mobileAll = $main->isNotEmpty() && $main->every(fn ($b) => $b->mobile_image_path);
    $sideCols = [1 => 'grid-cols-1', 2 => 'grid-cols-2', 3 => 'grid-cols-2 sm:grid-cols-3', 4 => 'grid-cols-2 lg:grid-cols-4'][max(1, $side->count())];
@endphp
<section aria-label="Kampanyalar">
    @if ($main->isNotEmpty())
        <div class="group/hero relative bg-navy" x-data="bannerRotator({{ $main->count() }})" x-init="start()" @mouseenter="stop()" @mouseleave="start()">
            <div @class(['relative w-full overflow-hidden', 'aspect-[4/5] sm:aspect-[3/1]' => $mobileAll, 'aspect-[3/1]' => ! $mobileAll])>
                @foreach ($main as $index => $banner)
                    @php $tag = $banner->link_url ? 'a' : 'div'; @endphp
                    <{{ $tag }} @if ($banner->link_url) href="{{ $banner->link_url }}" @endif x-show="i === {{ $index }}" @if ($index) x-cloak @endif
                        x-transition:enter="transition duration-700" x-transition:enter-start="opacity-0" x-transition:leave="transition duration-700 absolute inset-0" x-transition:leave-end="opacity-0"
                        class="absolute inset-0 block">
                        <picture>
                            @if ($mobileAll)<source media="(max-width: 639px)" srcset="{{ route('store.media.banner', ['storeBanner' => $banner, 'mobil' => 1, 'v' => $banner->updated_at?->timestamp]) }}">@endif
                            <img src="{{ route('store.media.banner', ['storeBanner' => $banner, 'v' => $banner->updated_at?->timestamp]) }}" alt="{{ $banner->title ?? $storeName }}" class="size-full object-cover"
                                @if ($index) loading="lazy" @else fetchpriority="high" @endif>
                        </picture>
                        @if ($banner->title || $banner->subtitle)
                            <span @class(['absolute inset-0 items-end bg-linear-to-t from-navy/85 via-navy/25 to-transparent sm:items-center sm:bg-linear-to-r sm:from-navy/85 sm:via-navy/40',
                                'flex' => $mobileAll, 'hidden sm:flex' => ! $mobileAll])>
                                <span class="mx-auto w-full max-w-7xl px-6 pb-14 sm:px-10 sm:pb-0 lg:px-12">
                                    <span class="block max-w-xl text-white">
                                        @if ($banner->title)<span class="block font-display text-3xl leading-[1.08] font-extrabold tracking-tight sm:text-4xl lg:text-6xl">{{ $banner->title }}</span>@endif
                                        @if ($banner->subtitle)<span class="mt-3 block text-sm text-white/85 sm:text-lg">{{ $banner->subtitle }}</span>@endif
                                        @if ($banner->button_text && $banner->link_url)
                                            <span class="mt-6 inline-flex items-center gap-2 rounded-xl bg-puzzle-sun px-6 py-3 text-sm font-bold text-navy shadow-lg shadow-black/20 transition hover:brightness-105">{{ $banner->button_text }} <x-icon name="arrow-right" class="size-4" /></span>
                                        @endif
                                    </span>
                                </span>
                            </span>
                        @endif
                    </{{ $tag }}>
                @endforeach
            </div>
            @if ($main->count() > 1)
                <button type="button" @click="prev()" class="absolute top-1/2 left-3 hidden size-12 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-navy opacity-0 shadow-lg transition group-hover/hero:opacity-100 hover:bg-white sm:flex lg:left-6" aria-label="Önceki banner"><x-icon name="arrow-right" class="size-5 rotate-180" /></button>
                <button type="button" @click="next()" class="absolute top-1/2 right-3 hidden size-12 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-navy opacity-0 shadow-lg transition group-hover/hero:opacity-100 hover:bg-white sm:flex lg:right-6" aria-label="Sonraki banner"><x-icon name="arrow-right" class="size-5" /></button>
                <div class="absolute inset-x-0 bottom-3 flex justify-center gap-2 sm:bottom-5">
                    @foreach ($main as $index => $banner)
                        <button type="button" @click="go({{ $index }})" class="h-2 rounded-full bg-white/50 shadow transition-all" :class="i === {{ $index }} ? 'w-8 bg-white' : 'w-2'" aria-label="Banner {{ $index + 1 }}"></button>
                    @endforeach
                </div>
            @endif
        </div>
    @else
        {{-- Banner yokken: marka tanıtımlı açılış (tam genişlik) --}}
        <div class="relative isolate overflow-hidden bg-navy text-white">
            <div class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(ellipse_at_top_right,rgba(250,204,21,0.12),transparent_55%)]" aria-hidden="true"></div>
            <div class="mx-auto grid max-w-7xl items-center gap-12 px-4 py-16 sm:px-6 sm:py-24 lg:grid-cols-[1.2fr_0.8fr] lg:px-8">
                <div class="max-w-2xl">
                    <p class="flex items-center gap-2 text-xs font-bold tracking-[0.25em] text-puzzle-sun uppercase">
                        <span class="inline-flex gap-0.5" aria-hidden="true"><span class="size-2 rounded-[3px] bg-puzzle-orange"></span><span class="size-2 rounded-[3px] bg-puzzle-green"></span><span class="size-2 rounded-[3px] bg-puzzle-blue"></span><span class="size-2 rounded-[3px] bg-puzzle-yellow"></span></span>
                        {{ $storeName }}
                    </p>
                    <h1 class="mt-5 font-display text-4xl leading-[1.06] font-extrabold tracking-tight sm:text-6xl">Kırtasiyede aradığınız <span class="text-puzzle-sun">her parça</span> burada.</h1>
                    <p class="mt-6 max-w-xl text-lg leading-relaxed text-white/75">{{ $settings['footer_about'] ?: 'Okul, ofis ve sanat malzemelerinde orijinal ürünler, uygun fiyatlar ve hızlı teslimat.' }}</p>
                    <div class="mt-9 flex flex-wrap gap-3">
                        <a href="{{ route('store.products') }}" class="inline-flex h-12 items-center gap-2 rounded-xl bg-puzzle-sun px-6 font-bold text-navy shadow-lg shadow-black/20 transition hover:brightness-105">Ürünlere göz at <x-icon name="arrow-right" class="size-4" /></a>
                        <a href="{{ route('store.brands') }}" class="inline-flex h-12 items-center gap-2 rounded-xl px-6 font-semibold text-white ring-1 ring-white/30 transition hover:bg-white/10">Markalar</a>
                    </div>
                </div>
                <div class="relative mx-auto hidden aspect-square w-full max-w-md lg:block" aria-hidden="true">
                    <div class="grid size-full rotate-6 grid-cols-2 gap-5">
                        <span class="rounded-[2.5rem] bg-puzzle-orange shadow-2xl shadow-black/30"></span><span class="rounded-[2.5rem] bg-puzzle-green shadow-2xl shadow-black/30"></span>
                        <span class="rounded-[2.5rem] bg-puzzle-blue shadow-2xl shadow-black/30"></span><span class="rounded-[2.5rem] bg-puzzle-sun shadow-2xl shadow-black/30"></span>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Kampanya kutuları (3:2) --}}
    @if ($side->isNotEmpty())
        <div class="mx-auto mt-4 grid max-w-7xl gap-3 px-4 sm:mt-6 sm:gap-4 sm:px-6 lg:mt-8 lg:gap-6 lg:px-8 {{ $sideCols }}">
            @foreach ($side as $banner)
                @php $tag = $banner->link_url ? 'a' : 'div'; @endphp
                <{{ $tag }} @if ($banner->link_url) href="{{ $banner->link_url }}" @endif class="group relative block aspect-[3/2] overflow-hidden rounded-2xl bg-navy sm:rounded-3xl shadow-sm transition hover:-translate-y-1 hover:shadow-xl hover:shadow-navy/15">
                    <img src="{{ route('store.media.banner', ['storeBanner' => $banner, 'w' => 1000, 'v' => $banner->updated_at?->timestamp]) }}" alt="{{ $banner->title ?? $storeName }}" loading="lazy" class="size-full object-cover">
                    @if ($banner->title)
                        <span class="absolute inset-0 flex flex-col justify-end bg-linear-to-t from-navy/85 via-navy/10 to-transparent p-3 text-white sm:p-6">
                            <span class="font-display text-sm leading-tight font-extrabold sm:text-2xl">{{ $banner->title }}</span>
                            @if ($banner->subtitle)<span class="mt-0.5 hidden text-sm text-white/80 sm:block">{{ $banner->subtitle }}</span>@endif
                            @if ($banner->button_text && $banner->link_url)<span class="mt-3 hidden w-fit items-center gap-1.5 sm:inline-flex text-sm font-bold text-puzzle-sun">{{ $banner->button_text }} <x-icon name="arrow-right" class="size-4 transition group-hover:translate-x-1" /></span>@endif
                        </span>
                    @endif
                </{{ $tag }}>
            @endforeach
        </div>
    @endif
</section>
