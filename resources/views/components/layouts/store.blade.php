@props([
    'title' => null,          // sayfa başlığı (mağaza adı sona eklenir); boşsa ana sayfa başlığı
    'description' => null,
    'canonical' => null,
    'noindex' => false,
    'ogImage' => null,
    'ogType' => 'website',
    'jsonLd' => [],           // yapılandırılmış veri blokları (dizi)
])
@php
    /** @var \Illuminate\Support\Collection $storeMenu  @var array $storeSettings  @var \Illuminate\Support\Collection $footerPages */
    $storeName = config('store.name');
    $fullTitle = $title ? "{$title} · {$storeName}" : ($storeSettings['meta_title'] ?: $storeName);
    $metaDescription = \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) ($description ?: $storeSettings['meta_description'])))), 300, '…');
    $preview = $storePreview ?? false;
    $b2bLogin = rtrim(config('app.url'), '/').'/bayi/login';
@endphp
@php $themeMode = in_array(request()->cookie('tema'), ['dark', 'auto'], true) ? request()->cookie('tema') : 'light'; @endphp
<!DOCTYPE html>
<html lang="tr" @class(['dark' => $themeMode === 'dark'])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $fullTitle }}</title>
    @if ($metaDescription !== '')<meta name="description" content="{{ $metaDescription }}">@endif
    <meta name="robots" content="{{ $noindex || $preview ? 'noindex, follow' : 'index, follow' }}">
    <link rel="canonical" href="{{ $canonical ?? url()->current() }}">
    <meta property="og:site_name" content="{{ $storeName }}">
    <meta property="og:locale" content="tr_TR">
    <meta property="og:type" content="{{ $ogType }}">
    <meta property="og:title" content="{{ $title ?? $fullTitle }}">
    @if ($metaDescription !== '')<meta property="og:description" content="{{ $metaDescription }}">@endif
    <meta property="og:url" content="{{ $canonical ?? url()->current() }}">
    @if ($ogImage)<meta property="og:image" content="{{ $ogImage }}"><meta name="twitter:card" content="summary_large_image">@endif
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @foreach ($jsonLd as $block)
        <script type="application/ld+json">{!! json_encode($block, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
    @endforeach
</head>
<body class="flex min-h-screen flex-col bg-white text-ink" x-data="{ menu: false }" @keydown.escape.window="menu = false">
    @if ($preview)
        <div class="bg-amber-400 px-4 py-2 text-center text-sm font-medium text-slate-900">
            Önizleme modu: mağaza ziyaretçilere kapalı, bu sayfayı yalnızca siz görüyorsunuz.
            <a href="{{ route('store.preview.end') }}" class="ml-2 underline">Önizlemeyi kapat</a>
        </div>
    @endif
    @if ($storeSettings['announcement'])
        <div class="bg-primary-dark px-4 py-2 text-center text-xs font-medium text-white sm:text-sm">{{ $storeSettings['announcement'] }}</div>
    @endif

    {{-- Üst bölüm --}}
    <header class="sticky top-0 z-30 border-b border-line bg-white/95 backdrop-blur">
        <div class="mx-auto flex h-16 max-w-7xl items-center gap-3 px-4 sm:h-20 sm:gap-6 sm:px-6 lg:px-8">
            <button type="button" class="-ml-1 rounded-lg p-2 text-ink hover:bg-slate-100 lg:hidden" @click="menu = true" aria-label="Menüyü aç"><x-icon name="menu" /></button>
            <a href="{{ route('store.home') }}" class="flex shrink-0 items-center gap-2.5" aria-label="{{ $storeName }} ana sayfa">
                <x-puzzle-logo :animated="false" class="size-9 sm:size-11" />
                <span class="text-lg font-bold tracking-tight sm:text-xl">{{ $storeName }}</span>
            </a>
            <form method="GET" action="{{ route('store.search') }}" role="search" class="relative ml-auto hidden max-w-xl flex-1 md:block">
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-slate-400" />
                <input name="q" value="{{ request()->routeIs('store.search') ? request('q') : '' }}" placeholder="Ürün, marka veya ürün kodu arayın" aria-label="Mağazada ara"
                    class="w-full rounded-full border border-line bg-surface py-2.5 pr-4 pl-11 text-sm focus:border-primary focus:bg-white focus:ring-2 focus:ring-primary/20 focus:outline-none">
            </form>
            <div class="ml-auto flex items-center gap-1 md:ml-0">
                @if ($storeSettings['phone'])
                    <a href="tel:{{ preg_replace('/[^\d+]/', '', $storeSettings['phone']) }}" class="mr-3 hidden items-center gap-2 text-sm font-medium text-ink hover:text-primary xl:flex">
                        <x-icon name="phone" class="size-4" /> {{ $storeSettings['phone'] }}
                    </a>
                @endif
                <a href="{{ $storeCustomer ? route('store.account') : route('store.login') }}" class="flex items-center gap-2 rounded-lg p-2 text-sm font-medium hover:bg-slate-100 sm:px-3"
                    aria-label="{{ $storeCustomer ? 'Hesabım' : 'Giriş yap' }}">
                    <x-icon name="user" class="size-5" />
                    <span class="hidden max-w-32 truncate sm:inline">{{ $storeCustomer ? $storeCustomer->first_name : 'Giriş yap' }}</span>
                </a>
                <a href="{{ route('store.cart') }}" class="relative flex items-center gap-2 rounded-lg p-2 text-sm font-medium hover:bg-slate-100 sm:px-3" aria-label="Sepet ({{ $cartCount }} ürün)">
                    <x-icon name="cart" class="size-5" />
                    <span class="hidden sm:inline">Sepet</span>
                    @if ($cartCount)<span class="absolute top-0 right-0 min-w-5 rounded-full bg-primary px-1 text-center text-[11px] leading-5 font-bold text-white tabular-nums sm:static">{{ $cartCount }}</span>@endif
                </a>
                {{-- Tema: açık ↔ koyu (tercih "tema" çerezinde) --}}
                <button type="button" x-data="themeSwitch" @click="toggle" class="rounded-lg p-2 hover:bg-slate-100" :aria-label="isDark ? 'Açık temaya geç' : 'Koyu temaya geç'" aria-label="Temayı değiştir">
                    <span x-show="! isDark"><x-icon name="moon" class="size-5" /></span>
                    <span x-show="isDark" x-cloak><x-icon name="sun" class="size-5" /></span>
                </button>
            </div>
        </div>
        {{-- Mobil arama --}}
        <form method="GET" action="{{ route('store.search') }}" role="search" class="px-4 pb-3 md:hidden">
            <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-slate-400" />
                <input name="q" value="{{ request()->routeIs('store.search') ? request('q') : '' }}" placeholder="Ürün, marka veya ürün kodu arayın" aria-label="Mağazada ara"
                    class="w-full rounded-full border border-line bg-surface py-2.5 pr-4 pl-11 text-sm focus:border-primary focus:bg-white focus:outline-none">
            </div>
        </form>
        {{-- Masaüstü kategori menüsü --}}
        <nav class="hidden border-t border-line lg:block" aria-label="Kategoriler">
            <ul class="mx-auto flex max-w-7xl items-center gap-1 px-4 sm:px-6 lg:px-8">
                @foreach ($storeMenu->take(8) as $root)
                    <li class="group relative">
                        <a href="{{ route('store.category', $root->slug) }}" @class([
                            'inline-flex items-center gap-1 rounded-lg px-3 py-3 text-sm font-medium hover:text-primary',
                            'text-primary' => request()->is('kategori/'.$root->slug),
                        ])>{{ $root->name }}@if ($root->children->isNotEmpty())<x-icon name="chevron-down" class="size-3.5 text-muted" />@endif</a>
                        @if ($root->children->isNotEmpty())
                            <div class="invisible absolute top-full left-0 z-40 w-64 translate-y-1 rounded-xl border border-line bg-white p-2 opacity-0 shadow-xl transition group-focus-within:visible group-focus-within:translate-y-0 group-focus-within:opacity-100 group-hover:visible group-hover:translate-y-0 group-hover:opacity-100">
                                @foreach ($root->children as $child)
                                    <a href="{{ route('store.category', $child->slug) }}" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-50 hover:text-primary">{{ $child->name }}</a>
                                @endforeach
                                <a href="{{ route('store.category', $root->slug) }}" class="mt-1 block border-t border-line px-3 pt-2.5 pb-1.5 text-sm font-medium text-primary">Tüm {{ $root->name }} →</a>
                            </div>
                        @endif
                    </li>
                @endforeach
                <li class="ml-auto"><a href="{{ route('store.products', ['sirala' => 'yeni']) }}" class="inline-flex px-3 py-3 text-sm font-medium hover:text-primary">Yeni ürünler</a></li>
                <li><a href="{{ route('store.brands') }}" class="inline-flex px-3 py-3 text-sm font-medium hover:text-primary">Markalar</a></li>
            </ul>
        </nav>
    </header>

    {{-- Mobil menü --}}
    <div x-cloak x-show="menu" class="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true" aria-label="Menü">
        <div class="absolute inset-0 bg-slate-900/40" @click="menu = false" x-show="menu" x-transition.opacity></div>
        <nav class="absolute inset-y-0 left-0 flex w-80 max-w-[85%] flex-col overflow-y-auto bg-white shadow-xl" x-show="menu" x-transition:enter="transition" x-transition:enter-start="-translate-x-full" x-transition:leave="transition" x-transition:leave-end="-translate-x-full">
            <div class="flex items-center justify-between border-b border-line px-4 py-3">
                <span class="font-semibold">Kategoriler</span>
                <button type="button" class="rounded-lg p-2 hover:bg-slate-100" @click="menu = false" aria-label="Menüyü kapat"><x-icon name="x" /></button>
            </div>
            <ul class="divide-y divide-line">
                @foreach ($storeMenu as $root)
                    <li x-data="{ open: false }">
                        <div class="flex items-center">
                            <a href="{{ route('store.category', $root->slug) }}" class="flex-1 px-4 py-3 text-sm font-medium">{{ $root->name }}</a>
                            @if ($root->children->isNotEmpty())
                                <button type="button" class="px-4 py-3 text-muted" @click="open = ! open" :aria-expanded="open" aria-label="{{ $root->name }} alt kategorileri">
                                    <x-icon name="chevron-down" class="size-4 transition" ::class="open && 'rotate-180'" />
                                </button>
                            @endif
                        </div>
                        @if ($root->children->isNotEmpty())
                            <ul x-show="open" x-cloak class="bg-slate-50 pb-2">
                                @foreach ($root->children as $child)
                                    <li><a href="{{ route('store.category', $child->slug) }}" class="block px-8 py-2 text-sm text-slate-700">{{ $child->name }}</a></li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
                <li><a href="{{ route('store.products', ['sirala' => 'yeni']) }}" class="block px-4 py-3 text-sm font-medium">Yeni ürünler</a></li>
                <li><a href="{{ route('store.brands') }}" class="block px-4 py-3 text-sm font-medium">Markalar</a></li>
            </ul>
        </nav>
    </div>

    {{-- Bildirimler --}}
    @if (session('status') || $errors->has('cart'))
        <div class="mx-auto max-w-7xl px-4 pt-4 sm:px-6 lg:px-8" role="status">
            @if (session('status'))
                <p class="flex flex-wrap items-center gap-3 rounded-xl border border-green-200 bg-success-soft px-4 py-3 text-sm text-green-900">
                    <x-icon name="check-circle" class="size-4" /> {{ session('status') }}
                    @if (session('cart_added'))<a href="{{ route('store.cart') }}" class="ml-auto font-semibold underline">Sepete git</a>@endif
                </p>
            @endif
            @error('cart')<p class="flex items-center gap-3 rounded-xl border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900"><x-icon name="alert" class="size-4" /> {{ $message }}</p>@enderror
        </div>
    @endif

    <main class="flex-1">{{ $slot }}</main>

    {{-- Alt bilgi --}}
    {{-- Başa dön: aşağı inince sol altta; halka okunan oranı gösterir --}}
    <button type="button" x-data="scrollTop" x-show="visible" x-transition.opacity x-cloak @click="up" aria-label="Sayfanın başına dön"
        class="fixed bottom-5 left-5 z-40 flex size-12 items-center justify-center rounded-full bg-[#0b1631] text-white shadow-lg shadow-black/25 transition hover:scale-105 print:hidden">
        <svg class="absolute inset-0 size-12 -rotate-90" viewBox="0 0 40 40" aria-hidden="true">
            <circle cx="20" cy="20" r="18" fill="none" stroke="rgb(255 255 255 / 0.15)" stroke-width="3" />
            <circle cx="20" cy="20" r="18" fill="none" stroke="#fbbf24" stroke-width="3" stroke-linecap="round" stroke-dasharray="113.1" :stroke-dashoffset="dash" />
        </svg>
        <x-icon name="chevron-down" class="relative size-5 rotate-180" />
    </button>

    <x-store.footer :storeName="$storeName" :settings="$storeSettings" :menu="$storeMenu" :pages="$footerPages" :footer="$storeFooter" :b2bLogin="$b2bLogin" />

    {{-- Çerez tercihi (Gizlilik ve Çerez Politikası). Yalnızca zorunlu çerezler kullanılır. --}}
    <div x-data="cookieConsent" x-show="open" x-cloak role="dialog" aria-label="Çerez tercihi"
        class="fixed inset-x-0 bottom-0 z-50 border-t border-line bg-white/95 shadow-2xl backdrop-blur sm:inset-x-auto sm:bottom-4 sm:left-4 sm:max-w-md sm:rounded-2xl sm:border">
        <div class="p-5">
            <p class="text-sm font-semibold">Çerezler</p>
            <p class="mt-1 text-sm text-muted">Sitemizin çalışması için zorunlu çerezler (oturum, sepet, güvenlik) kullanıyoruz. Analiz veya reklam çerezi kullanmıyoruz; ileride kullanırsak yalnızca onayınızla etkinleşir.
                <a href="{{ route('store.legal', 'gizlilik-cerez') }}" class="font-medium text-primary underline">Çerez Politikası</a></p>
            <div class="mt-4 flex flex-wrap gap-2">
                <button type="button" @click="choose('all')" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-hover">Kabul et</button>
                <button type="button" @click="choose('essential')" class="rounded-lg border border-line px-4 py-2 text-sm font-medium hover:bg-slate-50">Yalnızca zorunlu</button>
            </div>
        </div>
    </div>
</body>
</html>
