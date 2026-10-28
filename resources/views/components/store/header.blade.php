{{--
    Vitrin üst bilgisi: üst şerit (lacivert), logo + canlı arama + hesap/sepet/tema, menü çubuğu, mobil çekmece.
    Menü çubuğu: Tüm kategoriler (açılır menü, 3 seviye; içeriği store.front._menu-desktop / _menu-mobile, ilk açılışta yüklenir) › Tüm ürünler › Markalar › Yeni gelenler › İndirimler › Fotokopi (açıksa) › Kampanyalar (yayında varsa) › yayındaki
    sabit sayfalar (Hakkımızda, İletişim) › Blog (yazı varsa). Kategori renkleri: Catalog::menu() her ana kategoriye bir yapboz rengi verir.
--}}
@props(['storeName', 'settings', 'menu', 'footer', 'pages', 'customer', 'cartCount'])
@php
    use App\Domain\Store\Storefront\Palette;

    $whatsapp = \App\Domain\Store\Storefront\ProductPage::whatsapp($settings['whatsapp']);
    $q = request()->routeIs('store.search') ? (string) request('q') : '';
    $listing = request()->routeIs('store.products');
    $campaignList = $listing && request()->filled('kampanya');
    $links = [
        ['Tüm ürünler', route('store.products'), $listing && ! $campaignList && ! request()->boolean('yeni') && ! request()->boolean('indirim'), null],
        ['Markalar', route('store.brands'), request()->routeIs('store.brands'), null],
        ['Yeni gelenler', route('store.products', ['yeni' => 1]), $listing && ! $campaignList && request()->boolean('yeni'), ['star', 'text-puzzle-yellow']],
        ['İndirimler', route('store.products', ['indirim' => 1]), $listing && ! $campaignList && request()->boolean('indirim') && ! request()->boolean('yeni'), ['percent', 'text-puzzle-orange']],
    ];
    if (app(\App\Domain\Store\Print\PrintSettings::class)->enabled()) {
        $links[] = ['Fotokopi', route('store.print'), request()->routeIs('store.print*'), ['printer', 'text-puzzle-blue']];
    }
    if (\App\Domain\Store\Discounts\CampaignPage::any()) {
        $links[] = ['Kampanyalar', route('store.campaigns'), request()->routeIs('store.campaigns') || $campaignList, ['gift', 'text-green-600']];
    }
    array_push($links, ...array_map(fn ($page) => [$page['title'], $page['url'], url()->current() === $page['url'], null], $pages));
    if (\App\Models\Store\StoreBlogPost::anyLive()) {
        $links[] = ['Blog', route('store.blog'), request()->routeIs('store.blog*'), null];
    }
    $navLink = 'relative z-10 inline-flex h-10 shrink-0 items-center gap-2 whitespace-nowrap rounded-xl px-3 text-sm font-semibold transition-colors duration-200 hover:text-navy-ink xl:px-3.5';
    $phones = \App\Domain\Store\Storefront\StorePhones::list($settings);
    // Kategori menüsünün içeriği ilk açılışta yüklenir; adres kategori sürümünü taşır, tarayıcı önbellekte tutar.
    $menuVersion = $menu->isNotEmpty() ? app(\App\Domain\Store\Storefront\Catalog::class)->menuVersion() : null;
    $menuUrl = fn (string $part) => route('store.category-menu', ['part' => $part, 'v' => $menuVersion]);
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
    <nav class="relative hidden border-t border-line lg:block" aria-label="Kategoriler" x-data="megaMenu(@alpine($menuUrl('masaustu')))" @click.outside="close" @keydown.escape.window="close">
        <div class="mx-auto flex h-14 max-w-7xl items-center gap-4 px-4 sm:px-6 lg:px-8">
            @if ($menu->isNotEmpty())
                <button type="button" @click="toggle" @mouseenter="load" @focus="load" :aria-expanded="open" aria-expanded="false"
                    class="group inline-flex h-10 shrink-0 items-center gap-2 whitespace-nowrap rounded-xl bg-navy px-4 text-sm font-semibold text-white shadow-md shadow-navy/20 transition duration-200 hover:-translate-y-px hover:bg-navy-2 hover:shadow-lg">
                    <x-icon name="grid" class="size-4 transition duration-300 group-hover:rotate-90" /> Tüm kategoriler <x-icon name="chevron-down" class="size-4 transition duration-300" ::class="open && 'rotate-180'" />
                </button>
            @endif

            {{-- Sekmeler satırın kalanına eşit aralıkla yayılır; vurgu fareyle gezilen sekmeye kayar. --}}
            <div class="relative flex min-w-0 flex-1 items-center justify-between" x-data="navIndicator" @mouseleave="reset">
                <span class="pointer-events-none absolute inset-y-2 left-0 rounded-xl bg-puzzle-orange/10" :class="[shown ? 'opacity-100' : 'opacity-0', ready ? 'transition-all duration-300 ease-out' : '']" :style="style" aria-hidden="true"></span>
                <span class="pointer-events-none absolute bottom-0 left-0 h-[3px] rounded-full bg-puzzle-orange" :class="[shown ? 'opacity-100' : 'opacity-0', ready ? 'transition-all duration-300 ease-out' : '']" :style="style" aria-hidden="true"></span>
                @foreach ($links as [$label, $url, $active, $icon])
                    <a href="{{ $url }}" @mouseenter="move" @focus="move" @class([$navLink, 'group', 'text-navy-ink' => $active, 'text-ink/75' => ! $active]) @if ($active) aria-current="page" @endif>
                        @if ($icon)<x-icon :name="$icon[0]" class="size-4 transition duration-300 group-hover:-translate-y-0.5 group-hover:scale-125 {{ $icon[1] }}" />@endif{{ $label }}
                    </a>
                @endforeach
            </div>

            @if ($phones || $whatsapp)
                <a href="{{ $whatsapp ? 'https://wa.me/'.$whatsapp : $phones[0]['tel'] }}" @if ($whatsapp) target="_blank" rel="noopener" @endif
                    class="group hidden h-10 shrink-0 items-center gap-2.5 rounded-xl border border-line pr-3.5 pl-3 text-sm transition duration-200 hover:-translate-y-px hover:border-puzzle-green hover:shadow-md xl:inline-flex">
                    <span class="relative flex size-2.5" aria-hidden="true">
                        <span class="absolute inline-flex size-full animate-ping rounded-full bg-puzzle-green opacity-60"></span>
                        <span class="relative inline-flex size-2.5 rounded-full bg-puzzle-green"></span>
                    </span>
                    <span class="leading-tight">
                        <span class="block text-[10px] font-medium tracking-wide text-muted uppercase">Hızlı destek</span>
                        <span class="block font-semibold text-ink group-hover:text-puzzle-green">{{ $whatsapp ? 'WhatsApp' : $phones[0]['number'] }}</span>
                    </span>
                </a>
            @endif
        </div>

        @if ($menu->isNotEmpty())
            {{-- Açılır menü sabit yükseklikte: sol liste ve sağ panel kendi içinde kayar; yüzlerce kategoride de ekrandan taşmaz. --}}
            <div x-show="open" x-cloak
                x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="-translate-y-1 opacity-0" x-transition:enter-end="translate-y-0 opacity-100"
                x-transition:leave="transition duration-150 ease-in" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                class="absolute inset-x-0 top-full z-40 border-t border-line bg-white shadow-2xl shadow-navy/15">
                <div x-ref="menuBody"></div>
                <div x-show="! loaded" class="mx-auto flex h-[min(72vh,600px)] max-w-7xl items-center justify-center gap-3 text-sm text-muted">
                    <span x-show="! failed" class="size-5 animate-spin rounded-full border-2 border-line border-t-puzzle-orange" aria-hidden="true"></span>
                    <span x-show="! failed">Kategoriler yükleniyor…</span>
                    <a x-show="failed" x-cloak href="{{ route('store.products') }}" class="font-semibold text-primary hover:underline">Kategoriler yüklenemedi; tüm ürünlere göz atın →</a>
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
        {{-- Kategoriler adım adım: ana kategoriye dokununca alt kategorileri, onlara dokununca 3. seviye açılır. --}}
        <div x-data="drillMenu(@alpine($menuVersion ? $menuUrl('mobil') : null))" class="flex flex-1 flex-col">
            <div x-ref="menuBody"></div>
            <div x-show="! loaded && url" class="flex items-center gap-3 px-4 py-6 text-sm text-muted">
                <span x-show="! failed" class="size-5 animate-spin rounded-full border-2 border-line border-t-puzzle-orange" aria-hidden="true"></span>
                <span x-show="! failed">Kategoriler yükleniyor…</span>
                <a x-show="failed" x-cloak href="{{ route('store.products') }}" class="font-semibold text-primary">Kategoriler yüklenemedi; tüm ürünler →</a>
            </div>

        <div x-show="isScreen('')" class="mt-4 space-y-1 border-t border-line px-4 py-4 text-sm font-medium">
            @foreach ($links as [$label, $url, $active])
                <a href="{{ $url }}" @class(['block py-2', 'text-puzzle-orange' => $active])>{{ $label }}</a>
            @endforeach
        </div>
        @if ($phones || $whatsapp)
            <div class="mt-auto space-y-2 bg-surface px-4 py-4 text-sm">
                @foreach ($phones as $phone)<a href="{{ $phone['tel'] }}" class="flex items-center gap-2 font-semibold"><x-icon name="phone" class="size-4 text-puzzle-orange" /> {{ $phone['number'] }}@if (count($phones) > 1) <span class="font-normal text-muted">· {{ $phone['label'] }}</span>@endif</a>@endforeach
                @if ($whatsapp)<a href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener" class="flex items-center gap-2 font-semibold"><x-icon name="message-circle" class="size-4 text-puzzle-green" /> WhatsApp destek</a>@endif
            </div>
        @endif
        </div>
    </nav>
</div>
