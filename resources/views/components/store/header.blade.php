{{--
    Vitrin üst bilgisi: üst şerit (lacivert), logo + canlı arama + hesap/sepet/tema, menü çubuğu, mobil çekmece.
    Menü çubuğu: Tüm kategoriler (açılır menü, 3 seviye) › Tüm ürünler › Markalar › Yeni gelenler › İndirimler › yayındaki
    sabit sayfalar (Hakkımızda, İletişim) › Blog (yazı varsa). Kategori renkleri: Catalog::menu() her ana kategoriye bir yapboz rengi verir.
--}}
@props(['storeName', 'settings', 'menu', 'footer', 'pages', 'customer', 'cartCount'])
@php
    use App\Domain\Store\Storefront\Palette;

    $whatsapp = \App\Domain\Store\Storefront\ProductPage::whatsapp($settings['whatsapp']);
    $q = request()->routeIs('store.search') ? (string) request('q') : '';
    $listing = request()->routeIs('store.products');
    $links = [
        ['Tüm ürünler', route('store.products'), $listing && ! request()->boolean('yeni') && ! request()->boolean('indirim'), null],
        ['Markalar', route('store.brands'), request()->routeIs('store.brands'), null],
        ['Yeni gelenler', route('store.products', ['yeni' => 1]), $listing && request()->boolean('yeni'), ['star', 'text-puzzle-yellow']],
        ['İndirimler', route('store.products', ['indirim' => 1]), $listing && request()->boolean('indirim') && ! request()->boolean('yeni'), ['percent', 'text-puzzle-orange']],
        ...array_map(fn ($page) => [$page['title'], $page['url'], url()->current() === $page['url'], null], $pages),
    ];
    if (\App\Models\Store\StoreBlogPost::anyLive()) {
        $links[] = ['Blog', route('store.blog'), request()->routeIs('store.blog*'), null];
    }
    $navLink = 'relative inline-flex h-12 shrink-0 items-center gap-2 whitespace-nowrap px-3 text-sm font-semibold transition hover:text-ink';
@endphp

@include('components.store._marquee', ['items' => $footer['marquee'], 'color' => $footer['marquee_color'], 'speed' => $footer['marquee_speed']])

{{-- Duyuru: yalnızca Mağaza Ayarları'nda yazılmışsa, ince bir şerit --}}
@if ($settings['announcement'])
    <div class="bg-navy px-4 py-2 text-center text-[13px] font-semibold text-amber-300">{{ $settings['announcement'] }}</div>
@endif

<header class="sticky top-0 z-30 bg-white/95 shadow-[0_1px_0_var(--color-line)] backdrop-blur">
    <div class="mx-auto flex h-16 max-w-7xl items-center gap-3 px-4 sm:h-[76px] sm:gap-6 sm:px-6 lg:px-8">
        <button type="button" class="-ml-1 rounded-lg p-2 text-ink hover:bg-slate-100 lg:hidden" @click="menu = true" aria-label="Menüyü aç"><x-icon name="menu" /></button>
        <a href="{{ route('store.home') }}" class="flex shrink-0 items-center gap-2.5" aria-label="{{ $storeName }} ana sayfa">
            <x-puzzle-logo :animated="false" class="size-10 sm:size-12" />
            <x-store.wordmark :name="$storeName" :tagline="$footer['tagline']" nameClass="font-display text-lg font-extrabold tracking-tight text-navy-ink sm:text-[22px] dark:text-white"
                taglineClass="hidden text-puzzle-orange sm:block" :taglineSize="10" :nameSize="22" />
        </a>

        {{-- Canlı arama (masaüstü) --}}
        <form method="GET" action="{{ route('store.search') }}" role="search" class="relative ml-auto hidden max-w-2xl flex-1 md:block"
            x-data="liveSearch(@alpine(route('store.search.suggest')), @alpine($q))" @click.outside="close" @keydown.escape="close">
            @include('store.front._search-box', ['inputId' => 'arama'])
        </form>

        {{-- Sağ: (üyeye) favoriler · hesap · sepet │ tema --}}
        <div class="ml-auto flex shrink-0 items-center gap-1 md:ml-0 sm:gap-2">
            @if ($customer)
                <a href="{{ route('store.favorites') }}" class="hidden size-11 items-center justify-center rounded-xl text-ink/80 transition hover:bg-surface hover:text-puzzle-orange sm:flex" aria-label="Favorilerim" title="Favorilerim">
                    <x-icon name="heart" class="size-6" />
                </a>
            @endif
            <a href="{{ $customer ? route('store.account') : route('store.login') }}" class="flex h-10 items-center gap-2.5 rounded-xl px-0.5 transition hover:bg-surface sm:h-11 sm:px-3"
                aria-label="{{ $customer ? 'Hesabım' : 'Giriş yap' }}">
                <span class="flex size-9 items-center justify-center rounded-full bg-surface text-navy-ink ring-1 ring-line"><x-icon name="user" class="size-[18px]" /></span>
                <span class="hidden text-left leading-tight lg:block">
                    <span class="block text-[11px] text-muted">{{ $customer ? 'Merhaba, '.$customer->first_name : 'Hoş geldiniz' }}</span>
                    <span class="block text-sm font-semibold">{{ $customer ? 'Hesabım' : 'Giriş yap' }}</span>
                </span>
            </a>
            <a href="{{ route('store.cart') }}" x-data="cartBadge({{ $cartCount }})" @click.prevent="openCart"
                class="relative flex h-10 items-center gap-2.5 rounded-xl bg-navy px-2.5 text-white sm:h-11 sm:px-3 shadow-md shadow-navy/20 transition hover:bg-puzzle-orange lg:px-4" aria-label="Sepet ({{ $cartCount }} ürün)">
                {{-- Ürün sayısı: dar ekranda simgenin köşesinde, geniş ekranda "Sepetim" yazısının yanında (yazıyla çakışmaz). --}}
                <span class="relative">
                    <x-icon name="cart" class="size-5" />
                    <span x-show="count > 0" x-text="count" @if (! $cartCount) x-cloak @endif
                        class="absolute -top-2 -right-2.5 min-w-[18px] rounded-full bg-puzzle-orange px-1 text-center text-[10px] leading-[18px] font-bold text-white tabular-nums ring-2 ring-navy lg:hidden">{{ $cartCount }}</span>
                </span>
                <span class="hidden text-sm font-semibold lg:block">Sepetim</span>
                <span x-show="count > 0" x-text="count" @if (! $cartCount) x-cloak @endif
                    class="hidden min-w-6 rounded-full bg-puzzle-orange px-1.5 text-center text-xs leading-6 font-bold text-white tabular-nums lg:inline-block">{{ $cartCount }}</span>
            </a>
            <span class="mx-1 hidden h-8 w-px bg-line sm:block" aria-hidden="true"></span>
            <button type="button" x-data="themeSwitch" @click="toggle" class="flex size-10 items-center justify-center rounded-xl text-ink/70 ring-1 ring-line transition sm:size-11 hover:bg-surface hover:text-ink" :aria-label="isDark ? 'Açık temaya geç' : 'Koyu temaya geç'" aria-label="Temayı değiştir">
                <span x-show="! isDark"><x-icon name="moon" class="size-5" /></span>
                <span x-show="isDark" x-cloak><x-icon name="sun" class="size-5" /></span>
            </button>
        </div>
    </div>

    {{-- Mobil arama --}}
    <form method="GET" action="{{ route('store.search') }}" role="search" class="relative px-4 pb-3 md:hidden"
        x-data="liveSearch(@alpine(route('store.search.suggest')), @alpine($q))" @click.outside="close" @keydown.escape="close">
        @include('store.front._search-box', ['inputId' => 'arama-mobil'])
    </form>

    {{-- Geniş kategori menüsü (masaüstü) --}}
    <nav class="relative hidden border-t border-line lg:block" aria-label="Kategoriler" x-data="megaMenu" @click.outside="close" @keydown.escape.window="close">
        <div class="mx-auto flex max-w-7xl items-center gap-1 overflow-hidden px-4 sm:px-6 lg:px-8">
            @if ($menu->isNotEmpty())
                <button type="button" @click="toggle" :aria-expanded="open" aria-expanded="false"
                    class="mr-2 inline-flex h-10 shrink-0 items-center gap-2 whitespace-nowrap rounded-xl bg-navy px-4 text-sm font-semibold text-white transition hover:bg-navy-2">
                    <x-icon name="grid" class="size-4" /> Tüm kategoriler <x-icon name="chevron-down" class="size-4 transition" ::class="open && 'rotate-180'" />
                </button>
            @endif
            @foreach ($links as [$label, $url, $active, $icon])
                <a href="{{ $url }}" @class([$navLink, 'group', 'text-ink' => $active, 'text-ink/80' => ! $active]) @if ($active) aria-current="page" @endif>
                    @if ($icon)<x-icon :name="$icon[0]" class="size-4 {{ $icon[1] }}" />@endif{{ $label }}
                    <span @class(['absolute inset-x-3 bottom-0 h-0.5 origin-left rounded-full bg-puzzle-orange transition group-hover:scale-x-100', 'scale-x-0' => ! $active])></span>
                </a>
            @endforeach
        </div>

        @if ($menu->isNotEmpty())
            <div x-show="open" x-cloak x-transition.opacity.duration.150ms class="absolute inset-x-0 top-full z-40 border-t border-line bg-white shadow-2xl shadow-navy/10">
                <div class="mx-auto grid max-w-7xl grid-cols-12 px-4 sm:px-6 lg:px-8">
                    <ul class="col-span-3 border-r border-line py-4 pr-4">
                        @foreach ($menu as $i => $root)
                            @php $c = Palette::classes($root->palette); @endphp
                            <li>
                                <a href="{{ route('store.category', $root->slug) }}" @mouseenter="show({{ $i }})" @focus="show({{ $i }})"
                                    :class="isActive({{ $i }}) ? '{{ $c['soft'] }} text-ink' : 'text-ink/75'"
                                    class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition">
                                    <span class="flex size-8 items-center justify-center rounded-lg font-display text-sm font-extrabold text-white {{ $c['solid'] }}">{{ mb_substr($root->name, 0, 1) }}</span>
                                    <span class="flex-1">{{ $root->name }}</span>
                                    <x-icon name="chevron-down" class="size-4 -rotate-90 text-muted" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                    @foreach ($menu as $i => $root)
                        @php $c = Palette::classes($root->palette); @endphp
                        <div x-show="isActive({{ $i }})" @if ($i) x-cloak @endif class="col-span-9 grid grid-cols-3 gap-6 py-6 pl-8">
                            <div class="col-span-2">
                                <div class="flex items-end justify-between gap-4 border-b border-line pb-3">
                                    <div>
                                        <p class="font-display text-xl font-extrabold">{{ $root->name }}</p>
                                        @if ($root->tagline)<p class="text-sm text-muted">{{ $root->tagline }}</p>@endif
                                    </div>
                                    <a href="{{ route('store.category', $root->slug) }}" class="shrink-0 text-sm font-semibold {{ $c['text'] }} hover:underline">Tümünü gör →</a>
                                </div>
                                @if ($root->children->isNotEmpty())
                                    {{-- Alt kategoriler başlık, onların alt kategorileri (3. seviye) altında liste --}}
                                    <ul class="filter-scroll mt-4 grid max-h-[60vh] grid-cols-2 gap-x-6 gap-y-4 overflow-y-auto pr-1">
                                        @foreach ($root->children as $child)
                                            <li>
                                                <a href="{{ route('store.category', $child->slug) }}" class="block rounded-lg px-2 py-1.5 text-sm font-semibold hover:bg-surface {{ $c['text'] }}">{{ $child->name }}</a>
                                                @if ($child->children->isNotEmpty())
                                                    <ul class="mt-0.5 ml-2 border-l-2 pl-2 {{ $c['border'] }}">
                                                        @foreach ($child->children as $grandchild)
                                                            <li><a href="{{ route('store.category', $grandchild->slug) }}" class="block rounded-lg px-2 py-1 text-sm text-ink/75 hover:bg-surface hover:text-ink">{{ $grandchild->name }}</a></li>
                                                        @endforeach
                                                    </ul>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    <p class="mt-4 text-sm text-muted">Bu kategorideki tüm ürünleri görmek için tıklayın.</p>
                                @endif
                            </div>
                            <a href="{{ route('store.category', $root->slug) }}" class="group relative flex min-h-56 flex-col justify-end overflow-hidden rounded-2xl p-5 {{ $c['soft'] }}">
                                @if ($root->image_path)
                                    <x-store.fit-image :src="route('store.media.category', ['storeCategory' => $root->id, 'w' => 640])" fill />
                                    <span class="absolute inset-0 bg-gradient-to-t from-navy/80 via-navy/10 to-transparent"></span>
                                @endif
                                <span @class(['relative font-display text-lg font-extrabold', 'text-white' => $root->image_path])>{{ $root->name }}</span>
                                <span @class(['relative mt-1 inline-flex items-center gap-1 text-sm font-semibold', 'text-white/85' => $root->image_path, $c['text'] => ! $root->image_path])>Alışverişe başla <x-icon name="arrow-right" class="size-4" /></span>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </nav>
</header>

{{-- Mobil menü çekmecesi --}}
<div x-cloak x-show="menu" class="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true" aria-label="Menü">
    <div class="absolute inset-0 bg-navy/50" @click="menu = false" x-show="menu" x-transition.opacity></div>
    <nav class="absolute inset-y-0 left-0 flex w-80 max-w-[88%] flex-col overflow-y-auto bg-white shadow-xl" x-show="menu"
        x-transition:enter="transition" x-transition:enter-start="-translate-x-full" x-transition:leave="transition" x-transition:leave-end="-translate-x-full">
        <div class="flex items-center justify-between bg-navy px-4 py-4 text-white">
            <span class="flex items-center gap-2.5"><x-puzzle-logo :animated="false" class="size-9" /><span class="font-display text-lg font-extrabold">{{ $storeName }}</span></span>
            <button type="button" class="rounded-lg p-2 hover:bg-white/10" @click="menu = false" aria-label="Menüyü kapat"><x-icon name="x" /></button>
        </div>
        <p class="px-4 pt-5 pb-2 text-[11px] font-bold tracking-[0.2em] text-muted uppercase">Kategoriler</p>
        <ul class="px-2">
            @foreach ($menu as $root)
                @php $c = Palette::classes($root->palette); @endphp
                <li x-data="{ open: false }">
                    <div class="flex items-center">
                        <a href="{{ route('store.category', $root->slug) }}" class="flex flex-1 items-center gap-3 rounded-xl px-2 py-2.5 text-sm font-semibold">
                            <span class="flex size-8 items-center justify-center rounded-lg font-display text-sm font-extrabold text-white {{ $c['solid'] }}">{{ mb_substr($root->name, 0, 1) }}</span>{{ $root->name }}
                        </a>
                        @if ($root->children->isNotEmpty())
                            <button type="button" class="rounded-lg p-3 text-muted" @click="open = ! open" :aria-expanded="open" aria-label="{{ $root->name }} alt kategorileri">
                                <x-icon name="chevron-down" class="size-4 transition" ::class="open && 'rotate-180'" />
                            </button>
                        @endif
                    </div>
                    @if ($root->children->isNotEmpty())
                        <ul x-show="open" x-cloak class="mb-2 ml-6 border-l-2 pl-3 {{ $c['border'] }}">
                            @foreach ($root->children as $child)
                                <li>
                                    <a href="{{ route('store.category', $child->slug) }}" class="block py-2 text-sm text-ink/80">{{ $child->name }}</a>
                                    @foreach ($child->children as $grandchild)
                                        <a href="{{ route('store.category', $grandchild->slug) }}" class="block py-1.5 pl-3 text-[13px] text-muted">{{ $grandchild->name }}</a>
                                    @endforeach
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </li>
            @endforeach
        </ul>
        <div class="mt-4 space-y-1 border-t border-line px-4 py-4 text-sm font-medium">
            @foreach ($links as [$label, $url, $active])
                <a href="{{ $url }}" @class(['block py-2', 'text-puzzle-orange' => $active])>{{ $label }}</a>
            @endforeach
        </div>
        @php $phones = \App\Domain\Store\Storefront\StorePhones::list($settings); @endphp
        @if ($phones || $whatsapp)
            <div class="mt-auto space-y-2 bg-surface px-4 py-4 text-sm">
                @foreach ($phones as $phone)<a href="{{ $phone['tel'] }}" class="flex items-center gap-2 font-semibold"><x-icon name="phone" class="size-4 text-puzzle-orange" /> {{ $phone['number'] }}@if (count($phones) > 1) <span class="font-normal text-muted">· {{ $phone['label'] }}</span>@endif</a>@endforeach
                @if ($whatsapp)<a href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener" class="flex items-center gap-2 font-semibold"><x-icon name="message-circle" class="size-4 text-puzzle-green" /> WhatsApp destek</a>@endif
            </div>
        @endif
    </nav>
</div>
