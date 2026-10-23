{{--
    Vitrin üst bilgisi: üst şerit (lacivert), logo + canlı arama + hesap/sepet/tema, geniş kategori menüsü, mobil çekmece.
    Kategori renkleri: Catalog::menu() her ana kategoriye bir yapboz rengi (palette) verir.
--}}
@props(['storeName', 'settings', 'menu', 'footer', 'pages', 'customer', 'cartCount'])
@php
    use App\Domain\Store\Storefront\Palette;

    $whatsapp = \App\Domain\Store\Storefront\ProductPage::whatsapp($settings['whatsapp']);
    $q = request()->routeIs('store.search') ? (string) request('q') : '';
    $roots = $menu->take(6);
    $navLink = 'inline-flex h-12 shrink-0 items-center gap-2 whitespace-nowrap px-3 text-sm font-semibold text-ink/80 transition hover:text-ink';
@endphp

{{-- Üst şerit --}}
@if ($footer['topbar'] || $settings['announcement'])
    <div class="bg-navy text-white">
        <div class="mx-auto flex min-h-10 max-w-7xl items-center gap-6 px-4 py-2 text-[13px] sm:px-6 lg:px-8">
            <ul class="hidden items-center gap-6 text-white/75 lg:flex">
                @foreach ($footer['topbar'] as [$icon, $text])
                    <li class="flex items-center gap-2 whitespace-nowrap"><x-icon :name="$icon" class="size-4 text-puzzle-sun" /> {{ $text }}</li>
                @endforeach
            </ul>
            @if ($settings['announcement'])
                <p class="flex-1 truncate text-center font-semibold text-amber-300">{{ $settings['announcement'] }}</p>
            @endif
            <div class="ml-auto hidden shrink-0 items-center gap-5 whitespace-nowrap text-white/75 md:flex">
                @if ($settings['phone'])
                    <a href="tel:{{ preg_replace('/[^\d+]/', '', $settings['phone']) }}" class="flex items-center gap-1.5 hover:text-white"><x-icon name="phone" class="size-3.5" /> {{ $settings['phone'] }}</a>
                @endif
                @if ($whatsapp)
                    <a href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener" class="flex items-center gap-1.5 hover:text-white"><x-icon name="message-circle" class="size-3.5" /> WhatsApp destek</a>
                @endif
                <a href="{{ $customer ? route('store.account') : route('store.login') }}" class="hover:text-white">Sipariş takibi</a>
            </div>
        </div>
    </div>
@endif

<header class="sticky top-0 z-30 bg-white/95 shadow-[0_1px_0_var(--color-line)] backdrop-blur">
    <div class="mx-auto flex h-16 max-w-7xl items-center gap-3 px-4 sm:h-[76px] sm:gap-6 sm:px-6 lg:px-8">
        <button type="button" class="-ml-1 rounded-lg p-2 text-ink hover:bg-slate-100 lg:hidden" @click="menu = true" aria-label="Menüyü aç"><x-icon name="menu" /></button>
        <a href="{{ route('store.home') }}" class="flex shrink-0 items-center gap-2.5" aria-label="{{ $storeName }} ana sayfa">
            <x-puzzle-logo :animated="false" class="size-10 sm:size-12" />
            <span class="leading-tight">
                <span class="block font-display text-lg font-extrabold tracking-tight text-navy-ink sm:text-[22px] dark:text-white">{{ $storeName }}</span>
                @if ($footer['tagline'])<span class="hidden text-[10px] font-bold tracking-[0.28em] text-puzzle-orange uppercase sm:block">{{ $footer['tagline'] }}</span>@endif
            </span>
        </a>

        {{-- Canlı arama (masaüstü) --}}
        <form method="GET" action="{{ route('store.search') }}" role="search" class="relative ml-auto hidden max-w-2xl flex-1 md:block"
            x-data="liveSearch(@alpine(route('store.search.suggest')), @alpine($q))" @click.outside="close" @keydown.escape="close">
            @include('store.front._search-box', ['inputId' => 'arama'])
        </form>

        <div class="ml-auto flex items-center gap-0.5 md:ml-0 sm:gap-1">
            <a href="{{ $customer ? route('store.account') : route('store.login') }}" class="flex items-center gap-2.5 rounded-xl p-2 hover:bg-slate-100 sm:px-3"
                aria-label="{{ $customer ? 'Hesabım' : 'Giriş yap' }}">
                <x-icon name="user" class="size-6" />
                <span class="hidden text-left leading-tight lg:block">
                    <span class="block text-[11px] text-muted">{{ $customer ? 'Merhaba, '.$customer->first_name : 'Hoş geldiniz' }}</span>
                    <span class="block text-sm font-semibold">{{ $customer ? 'Hesabım' : 'Giriş yap' }}</span>
                </span>
            </a>
            <a href="{{ $customer ? route('store.favorites') : route('store.login') }}" class="hidden rounded-xl p-2 hover:bg-slate-100 sm:block" aria-label="Favorilerim">
                <x-icon name="heart" class="size-6" />
            </a>
            <a href="{{ route('store.cart') }}" x-data="cartBadge({{ $cartCount }})" @click.prevent="openCart"
                class="relative flex items-center gap-2.5 rounded-xl p-2 hover:bg-slate-100 sm:px-3" aria-label="Sepet ({{ $cartCount }} ürün)">
                <span class="relative">
                    <x-icon name="cart" class="size-6" />
                    <span x-show="count > 0" x-text="count" @if (! $cartCount) x-cloak @endif
                        class="absolute -top-2 -right-2.5 min-w-5 rounded-full bg-puzzle-orange px-1 text-center text-[11px] leading-5 font-bold text-white tabular-nums">{{ $cartCount }}</span>
                </span>
                <span class="hidden text-left leading-tight lg:block">
                    <span class="block text-[11px] text-muted">Alışveriş</span>
                    <span class="block text-sm font-semibold">Sepetim</span>
                </span>
            </a>
            <button type="button" x-data="themeSwitch" @click="toggle" class="rounded-xl p-2 hover:bg-slate-100" :aria-label="isDark ? 'Açık temaya geç' : 'Koyu temaya geç'" aria-label="Temayı değiştir">
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
        <div class="mx-auto flex max-w-7xl items-center gap-1 px-4 sm:px-6 lg:px-8">
            @if ($menu->isNotEmpty())
                <button type="button" @click="toggle" :aria-expanded="open" aria-expanded="false"
                    class="mr-2 inline-flex h-10 shrink-0 items-center gap-2 whitespace-nowrap rounded-xl bg-navy px-4 text-sm font-semibold text-white transition hover:bg-navy-2">
                    <x-icon name="grid" class="size-4" /> Tüm kategoriler <x-icon name="chevron-down" class="size-4 transition" ::class="open && 'rotate-180'" />
                </button>
            @endif
            @foreach ($roots as $root)
                @php $c = Palette::classes($root->palette); @endphp
                <a href="{{ route('store.category', $root->slug) }}" @class([$navLink, 'group relative', 'hidden xl:inline-flex' => $loop->index >= 4])>
                    <span class="size-2 rounded-full {{ $c['solid'] }}"></span>{{ $root->name }}
                    <span class="absolute inset-x-3 bottom-0 h-0.5 origin-left scale-x-0 rounded-full transition group-hover:scale-x-100 {{ $c['solid'] }}"></span>
                </a>
            @endforeach
            <span class="ml-auto"></span>
            <a href="{{ route('store.products', ['indirim' => 1]) }}" class="{{ $navLink }}"><x-icon name="percent" class="size-4 text-puzzle-orange" /> İndirimler</a>
            <a href="{{ route('store.products', ['sirala' => 'yeni']) }}" class="{{ $navLink }}"><x-icon name="star" class="size-4 text-puzzle-yellow" /> Yeni gelenler</a>
            <a href="{{ route('store.brands') }}" class="{{ $navLink }}">Markalar</a>
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
                                    <ul class="mt-4 grid grid-cols-2 gap-x-6 gap-y-1">
                                        @foreach ($root->children as $child)
                                            <li><a href="{{ route('store.category', $child->slug) }}" class="block rounded-lg px-2 py-2 text-sm text-ink/80 hover:bg-surface hover:text-ink">{{ $child->name }}</a></li>
                                        @endforeach
                                    </ul>
                                @else
                                    <p class="mt-4 text-sm text-muted">Bu kategorideki tüm ürünleri görmek için tıklayın.</p>
                                @endif
                            </div>
                            <a href="{{ route('store.category', $root->slug) }}" class="group relative flex min-h-56 flex-col justify-end overflow-hidden rounded-2xl p-5 {{ $c['soft'] }}">
                                @if ($root->image_path)
                                    <img src="{{ route('store.media.category', ['storeCategory' => $root->id, 'w' => 640]) }}" alt="" loading="lazy" class="absolute inset-0 size-full object-cover transition duration-500 group-hover:scale-105">
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
                                <li><a href="{{ route('store.category', $child->slug) }}" class="block py-2 text-sm text-ink/80">{{ $child->name }}</a></li>
                            @endforeach
                        </ul>
                    @endif
                </li>
            @endforeach
        </ul>
        <div class="mt-4 space-y-1 border-t border-line px-4 py-4 text-sm font-medium">
            <a href="{{ route('store.products', ['indirim' => 1]) }}" class="block py-2">İndirimler</a>
            <a href="{{ route('store.products', ['sirala' => 'yeni']) }}" class="block py-2">Yeni gelenler</a>
            <a href="{{ route('store.brands') }}" class="block py-2">Markalar</a>
            @foreach ($pages as $page)<a href="{{ $page['url'] }}" class="block py-2">{{ $page['title'] }}</a>@endforeach
        </div>
        @if ($settings['phone'] || $whatsapp)
            <div class="mt-auto space-y-2 bg-surface px-4 py-4 text-sm">
                @if ($settings['phone'])<a href="tel:{{ preg_replace('/[^\d+]/', '', $settings['phone']) }}" class="flex items-center gap-2 font-semibold"><x-icon name="phone" class="size-4 text-puzzle-orange" /> {{ $settings['phone'] }}</a>@endif
                @if ($whatsapp)<a href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener" class="flex items-center gap-2 font-semibold"><x-icon name="message-circle" class="size-4 text-puzzle-green" /> WhatsApp destek</a>@endif
            </div>
        @endif
    </nav>
</div>
