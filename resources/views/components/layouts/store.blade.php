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
    <meta name="csrf-token" content="{{ csrf_token() }}">
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
<body class="flex min-h-screen flex-col bg-white pb-16 text-ink lg:pb-0" x-data="{ menu: false }" @keydown.escape.window="menu = false">
    @if ($preview)
        <div class="bg-amber-400 px-4 py-2 text-center text-sm font-medium text-slate-900">
            Önizleme modu: mağaza ziyaretçilere kapalı, bu sayfayı yalnızca siz görüyorsunuz.
            <a href="{{ route('store.preview.end') }}" class="ml-2 underline">Önizlemeyi kapat</a>
        </div>
    @endif
    <x-store.header :storeName="$storeName" :settings="$storeSettings" :menu="$storeMenu" :footer="$storeFooter" :pages="$footerPages" :customer="$storeCustomer" :cartCount="$cartCount" />

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
        class="fixed bottom-20 left-4 z-40 flex size-12 lg:bottom-5 lg:left-5 items-center justify-center rounded-full bg-[#0b1631] text-white shadow-lg shadow-black/25 transition hover:scale-105 print:hidden">
        <svg class="absolute inset-0 size-12 -rotate-90" viewBox="0 0 40 40" aria-hidden="true">
            <circle cx="20" cy="20" r="18" fill="none" stroke="rgb(255 255 255 / 0.15)" stroke-width="3" />
            <circle cx="20" cy="20" r="18" fill="none" stroke="#fbbf24" stroke-width="3" stroke-linecap="round" stroke-dasharray="113.1" :stroke-dashoffset="dash" />
        </svg>
        <x-icon name="chevron-down" class="relative size-5 rotate-180" />
    </button>

    <x-store.footer :storeName="$storeName" :settings="$storeSettings" :menu="$storeMenu" :pages="$footerPages" :footer="$storeFooter" :b2bLogin="$b2bLogin" />

    {{-- Mini sepet (sağdan açılır) --}}
    <div x-data="miniCart('{{ route('store.cart.drawer') }}')" x-show="open" x-cloak class="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-label="Sepetim" @keydown.escape.window="close">
        <div class="absolute inset-0 bg-navy/50" @click="close" x-show="open" x-transition.opacity></div>
        <aside class="absolute inset-y-0 right-0 flex w-full max-w-md flex-col bg-white shadow-2xl" x-show="open"
            x-transition:enter="transition duration-200" x-transition:enter-start="translate-x-full" x-transition:leave="transition duration-150" x-transition:leave-end="translate-x-full">
            <div class="flex items-center justify-between border-b border-line px-5 py-4">
                <p class="font-display text-lg font-extrabold">Sepetim</p>
                <button type="button" class="rounded-lg p-2 hover:bg-slate-100" @click="close" aria-label="Sepeti kapat"><x-icon name="x" /></button>
            </div>
            <div class="flex min-h-0 flex-1 flex-col" x-ref="body"></div>
        </aside>
    </div>

    {{-- Bildirim balonu (sepete eklenemedi vb.) --}}
    <div x-data="toast" x-show="visible" x-cloak x-transition role="status" class="fixed top-24 right-4 left-4 z-[60] sm:left-auto sm:w-96">
        <div :class="error ? 'border-red-200 bg-danger-soft text-red-900' : 'border-green-200 bg-success-soft text-green-900'" class="flex items-start gap-3 rounded-2xl border px-4 py-3 text-sm shadow-lg">
            <span class="flex-1" x-text="message"></span>
            <button type="button" @click="hide" aria-label="Kapat"><x-icon name="x" class="size-4" /></button>
        </div>
    </div>

    {{-- Mobil alt menü --}}
    <nav class="fixed inset-x-0 bottom-0 z-40 grid h-16 grid-cols-5 border-t border-line bg-white/95 backdrop-blur lg:hidden print:hidden" aria-label="Hızlı menü">
        @php $tab = 'flex flex-col items-center justify-center gap-0.5 text-[11px] font-medium text-ink/70'; @endphp
        <a href="{{ route('store.home') }}" @class([$tab, 'text-navy-ink!' => request()->routeIs('store.home')])><x-icon name="home" class="size-5" />Ana sayfa</a>
        <button type="button" @click="menu = true" class="{{ $tab }}"><x-icon name="grid" class="size-5" />Kategoriler</button>
        <label for="arama-mobil" class="{{ $tab }} cursor-pointer"><x-icon name="search" class="size-5" />Ara</label>
        <a href="{{ route('store.cart') }}" x-data="cartBadge({{ $cartCount }})" @click.prevent="openCart" class="{{ $tab }}">
            <span class="relative"><x-icon name="cart" class="size-5" /><span x-show="count > 0" x-text="count" @if (! $cartCount) x-cloak @endif class="absolute -top-1.5 -right-2.5 min-w-4 rounded-full bg-puzzle-orange px-1 text-center text-[10px] leading-4 font-bold text-white">{{ $cartCount }}</span></span>Sepetim
        </a>
        <a href="{{ $storeCustomer ? route('store.account') : route('store.login') }}" class="{{ $tab }}"><x-icon name="user" class="size-5" />Hesabım</a>
    </nav>

    <x-store.cookie-consent />
</body>
</html>
