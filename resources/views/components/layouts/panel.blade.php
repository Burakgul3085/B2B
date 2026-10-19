@props(['area', 'title', 'wide' => false])
@php
    use App\Domain\Auth\Area;

    /** @var Area $area */
    $user = auth($area->guard())->user();
    $currentBusiness ??= null;
    $dealerRelationships ??= collect();
    $currentRelationship ??= null;

    // Menüde yalnızca kullanıma açılmış modüller yer alır; sonraki aşamaların modülleri eklendikçe görünür.
    $nav = match ($area) {
        Area::Platform => [
            ['items' => [
                ['label' => 'Genel Durum', 'route' => 'platform.dashboard', 'icon' => 'home', 'active' => 'platform.dashboard'],
                ['label' => 'İşletmeler', 'route' => 'platform.businesses.index', 'icon' => 'building', 'active' => 'platform.businesses.*'],
                ['label' => 'Bayilik Başvuruları', 'route' => 'platform.applications.index', 'icon' => 'inbox', 'active' => 'platform.applications.*',
                    'badge' => \App\Models\DealerApplication::withoutBusinessScope()->whereIn('status', \App\Models\DealerApplication::OPEN)->count() ?: null],
                ['label' => 'Bayi Hesapları', 'route' => 'platform.dealer-accounts.index', 'icon' => 'users', 'active' => 'platform.dealer-accounts.*'],
                ['label' => 'E-posta Altyapısı', 'route' => 'platform.email-status', 'icon' => 'inbox', 'active' => 'platform.email-status'],
                ['label' => 'Denetim Kayıtları', 'route' => 'platform.audit-logs', 'icon' => 'file', 'active' => 'platform.audit-logs'],
            ]],
        ],
        Area::Business => [
            ['items' => [
                ['label' => 'Dashboard', 'route' => 'business.dashboard', 'icon' => 'home', 'active' => 'business.dashboard'],
            ]],
            ['label' => 'Satış', 'items' => array_values(array_filter([
                ['label' => 'Siparişler', 'route' => 'business.orders.index', 'icon' => 'file', 'active' => 'business.orders.*',
                    'badge' => \App\Models\Order::whereIn('status', ['submitted', 'cancel_requested'])->count()],
                ($currentBusiness?->moduleEnabled('product_requests') ?? false) ? ['label' => 'Ürün Talepleri', 'route' => 'business.product-requests.index', 'icon' => 'inbox', 'active' => 'business.product-requests.*',
                    'badge' => \App\Models\ProductRequest::where('status', 'new')->count()] : null,
            ]))],
            ['label' => 'Katalog', 'items' => [
                ['label' => 'Ürünler', 'route' => 'business.products.index', 'icon' => 'package', 'active' => 'business.products.*'],
                ['label' => 'Kategori ve Markalar', 'route' => 'business.catalog.structure', 'icon' => 'list', 'active' => 'business.catalog.structure'],
                ['label' => 'Fiyatlandırma', 'route' => 'business.pricing', 'icon' => 'percent', 'active' => ['business.pricing', 'business.price-lists.*']],
                ['label' => 'Vitrin', 'route' => 'business.storefront.index', 'icon' => 'image', 'active' => 'business.storefront.*'],
            ]],
            ['label' => 'Bayiler', 'items' => [
                ['label' => 'Bayiler', 'route' => 'business.dealers.index', 'icon' => 'users', 'active' => 'business.dealers.*'],
                ['label' => 'Başvurular', 'route' => 'business.applications.index', 'icon' => 'inbox', 'active' => 'business.applications.*',
                    'badge' => \App\Models\DealerApplication::whereIn('status', ['new', 'waiting_info'])->count()],
                ['label' => 'Bayi Grupları', 'route' => 'business.dealer-groups.index', 'icon' => 'layers', 'active' => 'business.dealer-groups.*'],
            ]],
            ['label' => 'Stok', 'items' => [
                ['label' => 'Stok Durumu', 'route' => 'business.stock.index', 'icon' => 'layers', 'active' => 'business.stock.*'],
                ['label' => 'Stok Hareketleri', 'route' => 'business.stock-movements.index', 'icon' => 'refresh', 'active' => 'business.stock-movements.*'],
                ['label' => 'Depolar ve Sayım', 'route' => 'business.warehouses.index', 'icon' => 'warehouse', 'active' => ['business.warehouses.*', 'business.stock-counts.*']],
            ]],
            ['label' => 'İletişim', 'items' => array_values(array_filter([
                ($currentBusiness?->moduleEnabled('messaging') ?? false) ? ['label' => 'Mesajlar', 'route' => 'business.messages.index', 'icon' => 'inbox', 'active' => 'business.messages.*',
                    'badge' => \App\Domain\Messaging\MessagingService::unreadScope(\App\Models\Conversation::query(), 'business')->count()] : null,
                ['label' => 'Duyurular', 'route' => 'business.announcements.index', 'icon' => 'file', 'active' => 'business.announcements.*'],
            ]))],
            ['label' => 'Finans', 'items' => [
                ['label' => 'Cari Hesaplar', 'route' => 'business.accounts.index', 'icon' => 'users', 'active' => 'business.accounts.*'],
                ['label' => 'Tahsilatlar', 'route' => 'business.collections.index', 'icon' => 'check-circle', 'active' => 'business.collections.*'],
            ]],
            ['items' => array_values(array_filter([
                ($currentBusiness?->moduleEnabled('reports') ?? false) ? ['label' => 'Raporlar', 'route' => 'business.reports.index', 'icon' => 'layers', 'active' => 'business.reports.*'] : null,
            ]))],
            ['label' => 'Ayarlar', 'items' => [
                ['label' => 'İşletme Ayarları', 'route' => 'business.settings.edit', 'icon' => 'settings', 'active' => 'business.settings.*'],
                ['label' => 'E-posta Ayarları', 'route' => 'business.email.settings', 'icon' => 'refresh', 'active' => 'business.email.*'],
                ['label' => 'KDV ve Birimler', 'route' => 'business.catalog.settings', 'icon' => 'file', 'active' => 'business.catalog.settings'],
                ['label' => 'Profil ve Güvenlik', 'route' => 'business.account', 'icon' => 'user', 'active' => 'business.account*'],
            ]],
        ],
        Area::Dealer => [
            ['items' => array_values(array_filter([
                ['label' => 'Ana Sayfa', 'route' => 'dealer.home', 'icon' => 'home', 'active' => 'dealer.home'],
                ['label' => 'Tüm Ürünler', 'route' => 'dealer.catalog', 'icon' => 'package', 'active' => 'dealer.catalog*',
                    'current' => request()->routeIs('dealer.catalog*') && ! request()->boolean('yeni')],
                ['label' => 'Markalar', 'route' => 'dealer.brands.index', 'icon' => 'layers', 'active' => 'dealer.brands.*'],
                $currentBusiness ? ['label' => 'Yeni Ürünler', 'route' => 'dealer.catalog', 'params' => ['yeni' => 1], 'icon' => 'plus', 'active' => 'dealer.catalog',
                    'current' => request()->routeIs('dealer.catalog') && request()->boolean('yeni'),
                    'badge' => \App\Domain\Catalog\NewProducts::count($currentBusiness->settings()->first())] : null,
                ['label' => 'Siparişlerim', 'route' => 'dealer.orders.index', 'icon' => 'file', 'active' => 'dealer.orders.*'],
                ['label' => 'Cari Hesap', 'route' => 'dealer.account.statement', 'icon' => 'list', 'active' => 'dealer.account.statement'],
            ]))],
        ],
    };

    // Bayi: üst menüde yalnızca alışveriş akışı; diğer sayfalar profil menüsünde.
    $dealerMenu = $area === Area::Dealer ? array_values(array_filter([
        ['label' => 'Duyurular', 'route' => 'dealer.announcements.index', 'icon' => 'file', 'active' => 'dealer.announcements.*'],
        ($currentBusiness?->moduleEnabled('messaging') ?? false) ? ['label' => 'Mesajlar', 'route' => 'dealer.messages.index', 'icon' => 'inbox', 'active' => 'dealer.messages.*'] : null,
        ($currentBusiness?->moduleEnabled('reports') ?? false) ? ['label' => 'Raporlar', 'route' => 'dealer.reports.index', 'icon' => 'layers', 'active' => 'dealer.reports.*'] : null,
        ($currentBusiness?->moduleEnabled('product_requests') ?? false) ? ['label' => 'Taleplerim', 'route' => 'dealer.requests.index', 'icon' => 'package', 'active' => 'dealer.requests.*'] : null,
        ['label' => 'Hesap Özeti', 'route' => 'dealer.dashboard', 'icon' => 'home', 'active' => 'dealer.dashboard'],
    ])) : [];

    $isPlatform = $area === Area::Platform;
    $brand = $isPlatform ? 'B2B Platform' : ($currentBusiness?->display_name ?? config('app.name'));
@endphp
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} · {{ $brand }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-surface" x-data="{ nav: false }">

@if ($area === Area::Dealer)
    {{-- Bayi: katalog odaklı üst menü --}}
    <header class="relative bg-primary-dark text-white" @if ($currentBusiness?->accent_color) style="border-top: 4px solid {{ $currentBusiness->accent_color }}" @endif>
        <div class="mx-auto flex h-[72px] max-w-[1840px] items-center gap-4 px-4 sm:px-6 lg:px-8">
            <button type="button" class="-ml-1 rounded-md p-1.5 hover:bg-white/10 md:hidden" @click="nav = !nav" aria-label="Menü"><x-icon name="menu" /></button>
            <a href="{{ route('dealer.home') }}" class="flex shrink-0 items-center gap-2.5">
                <x-puzzle-logo :animated="false" class="size-10" />
                <span class="truncate text-base font-bold tracking-tight">{{ $brand }}</span>
            </a>
            <nav class="ml-6 hidden items-center gap-1 md:flex" aria-label="Ana menü">
                @foreach ($nav as $group)
                    @foreach ($group['items'] as $item)
                        @php $current = $item['current'] ?? request()->routeIs(...(array) $item['active']); @endphp
                        <a href="{{ route($item['route'], $item['params'] ?? []) }}" @class([
                            'inline-flex items-center gap-1.5 rounded-lg px-3.5 py-2 text-sm font-semibold whitespace-nowrap transition',
                            'bg-white/15 text-white shadow-inner shadow-black/10' => $current,
                            'text-white/75 hover:bg-white/10 hover:text-white' => ! $current,
                        ])>{{ $item['label'] }}@if (! empty($item['badge']))<span class="rounded-full bg-amber-400 px-1.5 py-px text-[11px] font-bold text-primary-dark tabular-nums">{{ $item['badge'] }}</span>@endif</a>
                    @endforeach
                @endforeach
            </nav>
            @if ($currentRelationship)
                @php $cartCount = app(\App\Domain\Ordering\CartService::class)->count($currentRelationship); @endphp
                <form method="GET" action="{{ route('dealer.catalog') }}" class="relative ml-auto hidden max-w-md flex-1 lg:block" role="search">
                    <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-white/50" />
                    <input name="q" value="{{ request()->routeIs('dealer.catalog') ? request('q') : '' }}" placeholder="Ürün adı, kodu veya barkod ile arayın" aria-label="Katalogda ara"
                        class="peer w-full rounded-full border-0 bg-white/10 py-2.5 pr-4 pl-10 text-sm text-white ring-1 ring-white/10 placeholder:text-white/50 focus:bg-white focus:text-ink focus:ring-2 focus:ring-amber-300">
                </form>
                <div class="ml-auto lg:ml-0"><x-notification-bell side="dealer" :relationship-id="$currentRelationship->id" dark /></div>
                <a href="{{ route('dealer.cart') }}" class="relative rounded-lg p-2 hover:bg-white/10" aria-label="Sepet ({{ $cartCount }} ürün)">
                    <x-icon name="cart" />
                    @if ($cartCount)<span class="absolute -top-0.5 -right-0.5 rounded-full bg-amber-400 px-1.5 text-[11px] font-bold text-primary-dark tabular-nums">{{ $cartCount }}</span>@endif
                </a>
            @endif
            <div @class(['flex items-center gap-2', 'ml-auto' => ! $currentRelationship]) x-data="{ open: false }" @click.outside="open = false">
                <button type="button" @click="open = !open" class="flex items-center gap-2.5 rounded-full py-1 pr-3 pl-1 text-sm ring-1 ring-white/15 transition hover:bg-white/10" :aria-expanded="open">
                    @if ($user->dealer->logo_path)
                        <span class="flex size-8 items-center justify-center overflow-hidden rounded-full bg-white p-0.5 ring-2 ring-white/25"><img src="{{ route('dealer.logo') }}?v={{ $user->dealer->updated_at?->timestamp }}" alt="{{ $user->dealer->legal_name }} logosu" class="max-h-full max-w-full rounded-full object-contain"></span>
                    @else
                        <span class="flex size-8 items-center justify-center rounded-full bg-amber-400 text-sm font-bold text-primary-dark">{{ mb_substr($user->dealer->legal_name, 0, 1) }}</span>
                    @endif
                    <span class="hidden max-w-44 truncate font-medium sm:inline">{{ $user->dealer->legal_name }}</span>
                    <x-icon name="chevron-down" class="size-4 text-white/70" />
                </button>
                <div x-cloak x-show="open" x-transition class="absolute right-4 top-[68px] z-30 w-64 rounded-xl border border-line bg-white py-1 text-sm text-ink shadow-lg">
                    @if ($currentRelationship ?? null)
                        <div class="border-b border-line py-1">
                            @foreach ($dealerMenu as $item)
                                <a href="{{ route($item['route']) }}" @class([
                                    'flex items-center gap-3 px-4 py-2 hover:bg-slate-50',
                                    'bg-primary-soft font-medium text-primary' => request()->routeIs($item['active']),
                                ])><x-icon :name="$item['icon']" class="size-4 text-muted" /> {{ $item['label'] }}</a>
                            @endforeach
                        </div>
                        <a href="{{ route('dealer.addresses.index') }}" class="block px-4 py-2 hover:bg-slate-50">Sevk Adresleri</a>
                        <a href="{{ route('dealer.profile') }}" class="block px-4 py-2 hover:bg-slate-50">İşletme Profili</a>
                    @endif
                    <a href="{{ route('dealer.account') }}" class="block px-4 py-2 hover:bg-slate-50">Profil ve Güvenlik</a>
                    @if ($dealerRelationships->count() > 1)
                        <a href="{{ route('dealer.select-business') }}" class="block px-4 py-2 hover:bg-slate-50">Toptancı Değiştir</a>
                    @endif
                    <form method="POST" action="{{ route('dealer.logout') }}" class="border-t border-line">
                        @csrf
                        <button class="block w-full px-4 py-2 text-left text-danger hover:bg-slate-50">Çıkış Yap</button>
                    </form>
                </div>
            </div>
        </div>
        <nav x-cloak x-show="nav" class="border-t border-white/10 px-4 py-2 md:hidden" aria-label="Mobil menü">
            @foreach ($nav as $group)
                @foreach ($group['items'] as $item)
                    <a href="{{ route($item['route'], $item['params'] ?? []) }}" class="flex items-center gap-2 rounded-md px-3 py-2 text-sm text-white/90 hover:bg-white/10">{{ $item['label'] }}@if (! empty($item['badge']))<span class="rounded-full bg-amber-400 px-1.5 text-[11px] font-bold text-primary-dark">{{ $item['badge'] }}</span>@endif</a>
                @endforeach
            @endforeach
        </nav>
    </header>

    <main @class(['mx-auto px-4 py-8 sm:px-6', 'max-w-[1840px] lg:px-8' => $wide, 'max-w-7xl' => ! $wide])>
        <x-flash />
        {{ $slot }}
    </main>
@else
    {{-- Platform ve toptancı: sol menü --}}
    <div class="flex min-h-screen">
        <div x-cloak x-show="nav" class="fixed inset-0 z-30 bg-slate-900/40 lg:hidden" @click="nav = false"></div>
        <aside :class="nav ? 'translate-x-0' : '-translate-x-full'"
            @class([
                'fixed inset-y-0 left-0 z-40 flex w-64 flex-col transition-transform lg:static lg:translate-x-0',
                'bg-graphite text-slate-200' => $isPlatform,
                'border-r border-line bg-white' => ! $isPlatform,
            ])>
            <div @class(['flex h-16 items-center gap-2 px-5', 'border-b border-white/10' => $isPlatform, 'border-b border-line' => ! $isPlatform])>
                @if ($isPlatform)
                    <x-icon name="shield" class="size-5 text-white" />
                    <span class="font-semibold text-white">B2B Platform</span>
                    <span class="ml-auto rounded bg-white/10 px-1.5 py-0.5 text-[10px] font-semibold tracking-wider text-white uppercase">Platform</span>
                @else
                    <span class="flex size-8 items-center justify-center rounded-lg bg-primary text-sm font-semibold text-white">{{ mb_substr($brand, 0, 1) }}</span>
                    <span class="truncate font-semibold text-ink">{{ $brand }}</span>
                @endif
            </div>

            <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-5" aria-label="Ana menü">
                @foreach ($nav as $group)
                    <div>
                        @isset($group['label'])
                            <p @class(['mb-2 px-3 text-[11px] font-semibold tracking-wider uppercase', 'text-slate-500' => $isPlatform, 'text-muted' => ! $isPlatform])>{{ $group['label'] }}</p>
                        @endisset
                        <ul class="space-y-0.5">
                            @foreach ($group['items'] as $item)
                                @php $active = request()->routeIs(...(array) $item['active']); @endphp
                                <li>
                                    <a href="{{ route($item['route']) }}" @if ($active) aria-current="page" @endif @class([
                                        'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium',
                                        'bg-white/10 text-white' => $isPlatform && $active,
                                        'text-slate-300 hover:bg-white/5 hover:text-white' => $isPlatform && ! $active,
                                        'bg-primary-soft text-primary' => ! $isPlatform && $active,
                                        'text-slate-600 hover:bg-slate-50 hover:text-ink' => ! $isPlatform && ! $active,
                                    ])>
                                        <x-icon :name="$item['icon']" class="size-4.5" />
                                        <span class="flex-1">{{ $item['label'] }}</span>
                                        @if (($item['badge'] ?? 0) > 0)
                                            <span class="rounded-full bg-primary px-2 py-0.5 text-[11px] font-semibold text-white tabular-nums">{{ $item['badge'] }}</span>
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </nav>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="relative flex h-16 items-center gap-3 border-b border-line bg-white px-4 sm:px-6">
                <button type="button" class="rounded-md p-1.5 text-muted hover:bg-slate-100 lg:hidden" @click="nav = true" aria-label="Menüyü aç"><x-icon name="menu" /></button>
                @if ($area === Area::Business)
                    <div class="ml-auto"><x-notification-bell side="business" /></div>
                @endif
                <div @class(['flex items-center gap-1', 'ml-auto' => $area !== Area::Business]) x-data="{ open: false }" @click.outside="open = false">
                    <button type="button" @click="open = !open" class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-ink hover:bg-slate-100" :aria-expanded="open">
                        <span class="flex size-8 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-muted">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                        <span class="hidden sm:inline">{{ $user->name }}</span>
                        <x-icon name="chevron-down" class="size-4 text-muted" />
                    </button>
                    <div x-cloak x-show="open" x-transition class="absolute right-4 top-14 z-30 w-56 rounded-xl border border-line bg-white py-1 text-sm shadow-lg">
                        <a href="{{ route($area->value.'.account') }}" class="block px-4 py-2 hover:bg-slate-50">Profil ve Güvenlik</a>
                        <form method="POST" action="{{ route($area->value.'.logout') }}" class="border-t border-line">
                            @csrf
                            <button class="block w-full px-4 py-2 text-left text-danger hover:bg-slate-50">Çıkış Yap</button>
                        </form>
                    </div>
                </div>
            </header>

            <main class="flex-1 px-4 py-8 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-6xl">
                    <x-flash />
                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>
@endif
</body>
</html>
