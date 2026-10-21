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
    $whatsapp = $storeSettings['whatsapp'] ? preg_replace('/\D/', '', $storeSettings['whatsapp']) : null;
    $b2bLogin = rtrim(config('app.url'), '/').'/bayi/login';
@endphp
<!DOCTYPE html>
<html lang="tr">
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
            @if ($storeSettings['phone'])
                <a href="tel:{{ preg_replace('/[^\d+]/', '', $storeSettings['phone']) }}" class="ml-auto hidden items-center gap-2 text-sm font-medium text-ink hover:text-primary md:ml-0 lg:flex">
                    <x-icon name="phone" class="size-4" /> {{ $storeSettings['phone'] }}
                </a>
            @endif
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

    <main class="flex-1">{{ $slot }}</main>

    {{-- Alt bilgi --}}
    <footer class="mt-16 border-t border-line bg-surface">
        <div class="mx-auto grid max-w-7xl gap-10 px-4 py-12 sm:grid-cols-2 sm:px-6 lg:grid-cols-4 lg:px-8">
            <div>
                <a href="{{ route('store.home') }}" class="flex items-center gap-2.5">
                    <x-puzzle-logo :animated="false" class="size-10" />
                    <span class="text-lg font-bold">{{ $storeName }}</span>
                </a>
                @if ($storeSettings['footer_about'])<p class="mt-4 text-sm leading-relaxed text-muted">{{ $storeSettings['footer_about'] }}</p>@endif
                <div class="mt-4 flex gap-3">
                    @if ($storeSettings['instagram'])<a href="{{ $storeSettings['instagram'] }}" target="_blank" rel="noopener" class="text-sm font-medium text-primary hover:underline">Instagram</a>@endif
                    @if ($storeSettings['facebook'])<a href="{{ $storeSettings['facebook'] }}" target="_blank" rel="noopener" class="text-sm font-medium text-primary hover:underline">Facebook</a>@endif
                </div>
            </div>
            <div>
                <p class="text-sm font-semibold">Kategoriler</p>
                <ul class="mt-4 space-y-2 text-sm text-muted">
                    @foreach ($storeMenu->take(8) as $root)
                        <li><a href="{{ route('store.category', $root->slug) }}" class="hover:text-primary">{{ $root->name }}</a></li>
                    @endforeach
                    <li><a href="{{ route('store.brands') }}" class="hover:text-primary">Markalar</a></li>
                </ul>
            </div>
            <div>
                <p class="text-sm font-semibold">Kurumsal</p>
                <ul class="mt-4 space-y-2 text-sm text-muted">
                    @foreach ($footerPages as $page)
                        <li><a href="{{ route('store.page', $page->slug) }}" class="hover:text-primary">{{ $page->title }}</a></li>
                    @endforeach
                    <li><a href="{{ $b2bLogin }}" class="hover:text-primary">Bayi girişi</a></li>
                </ul>
            </div>
            <div>
                <p class="text-sm font-semibold">İletişim</p>
                <ul class="mt-4 space-y-3 text-sm text-muted">
                    @if ($storeSettings['phone'])<li class="flex gap-2"><x-icon name="phone" class="mt-0.5 size-4 shrink-0" /><a href="tel:{{ preg_replace('/[^\d+]/', '', $storeSettings['phone']) }}" class="hover:text-primary">{{ $storeSettings['phone'] }}</a></li>@endif
                    @if ($whatsapp)<li class="flex gap-2"><x-icon name="inbox" class="mt-0.5 size-4 shrink-0" /><a href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener" class="hover:text-primary">WhatsApp</a></li>@endif
                    @if ($storeSettings['email'])<li class="flex gap-2"><x-icon name="mail" class="mt-0.5 size-4 shrink-0" /><a href="mailto:{{ $storeSettings['email'] }}" class="break-all hover:text-primary">{{ $storeSettings['email'] }}</a></li>@endif
                    @if ($storeSettings['address'])<li class="flex gap-2"><x-icon name="map-pin" class="mt-0.5 size-4 shrink-0" /><span>{{ $storeSettings['address'] }}</span></li>@endif
                </ul>
            </div>
        </div>
        <div class="border-t border-line">
            <p class="mx-auto max-w-7xl px-4 py-5 text-xs text-muted sm:px-6 lg:px-8">© {{ now()->year }} {{ $storeName }}. Fiyatlara KDV dahildir.</p>
        </div>
    </footer>
</body>
</html>
