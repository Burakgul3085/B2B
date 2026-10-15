@props(['area', 'title'])
@php
    use App\Domain\Auth\Area;

    /** @var Area $area */
    $user = auth($area->guard())->user();

    // Menüde yalnızca kullanıma açılmış modüller yer alır; sonraki aşamaların modülleri eklendikçe görünür.
    $nav = match ($area) {
        Area::Platform => [
            ['items' => [
                ['label' => 'Genel Durum', 'route' => 'platform.dashboard', 'icon' => 'home', 'active' => 'platform.dashboard'],
                ['label' => 'İşletmeler', 'route' => 'platform.businesses.index', 'icon' => 'building', 'active' => 'platform.businesses.*'],
                ['label' => 'Denetim Kayıtları', 'route' => 'platform.audit-logs', 'icon' => 'file', 'active' => 'platform.audit-logs'],
            ]],
        ],
        Area::Business => [
            ['items' => [
                ['label' => 'Dashboard', 'route' => 'business.dashboard', 'icon' => 'home', 'active' => 'business.dashboard'],
            ]],
            ['label' => 'Katalog', 'items' => [
                ['label' => 'Ürünler', 'route' => 'business.products.index', 'icon' => 'package', 'active' => 'business.products.*'],
                ['label' => 'Kategori ve Markalar', 'route' => 'business.catalog.structure', 'icon' => 'list', 'active' => 'business.catalog.structure'],
            ]],
            ['label' => 'Stok', 'items' => [
                ['label' => 'Stok Durumu', 'route' => 'business.stock.index', 'icon' => 'layers', 'active' => 'business.stock.*'],
                ['label' => 'Stok Hareketleri', 'route' => 'business.stock-movements.index', 'icon' => 'refresh', 'active' => 'business.stock-movements.*'],
                ['label' => 'Depolar ve Sayım', 'route' => 'business.warehouses.index', 'icon' => 'warehouse', 'active' => ['business.warehouses.*', 'business.stock-counts.*']],
            ]],
            ['label' => 'Ayarlar', 'items' => [
                ['label' => 'İşletme Ayarları', 'route' => 'business.settings.edit', 'icon' => 'settings', 'active' => 'business.settings.*'],
                ['label' => 'KDV ve Birimler', 'route' => 'business.catalog.settings', 'icon' => 'percent', 'active' => 'business.catalog.settings'],
                ['label' => 'Profil ve Güvenlik', 'route' => 'business.account', 'icon' => 'user', 'active' => 'business.account*'],
            ]],
        ],
        Area::Dealer => [
            ['items' => [
                ['label' => 'Ana Sayfa', 'route' => 'dealer.dashboard', 'icon' => 'home', 'active' => 'dealer.dashboard'],
            ]],
        ],
    };

    $currentBusiness ??= null;
    $dealerRelationships ??= collect();
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
        <div class="mx-auto flex h-16 max-w-7xl items-center gap-4 px-4 sm:px-6">
            <button type="button" class="-ml-1 rounded-md p-1.5 hover:bg-white/10 md:hidden" @click="nav = !nav" aria-label="Menü"><x-icon name="menu" /></button>
            <a href="{{ route('dealer.dashboard') }}" class="truncate text-base font-semibold">{{ $brand }}</a>
            <nav class="ml-6 hidden items-center gap-1 md:flex" aria-label="Ana menü">
                @foreach ($nav as $group)
                    @foreach ($group['items'] as $item)
                        <a href="{{ route($item['route']) }}" @class([
                            'rounded-md px-3 py-2 text-sm font-medium',
                            'bg-white/15 text-white' => request()->routeIs(...(array) $item['active']),
                            'text-white/80 hover:bg-white/10 hover:text-white' => ! request()->routeIs(...(array) $item['active']),
                        ])>{{ $item['label'] }}</a>
                    @endforeach
                @endforeach
            </nav>
            <div class="ml-auto flex items-center gap-2" x-data="{ open: false }" @click.outside="open = false">
                <button type="button" @click="open = !open" class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-white/10" :aria-expanded="open">
                    <span class="hidden max-w-48 truncate sm:inline">{{ $user->dealer->legal_name }}</span>
                    <x-icon name="chevron-down" class="size-4" />
                </button>
                <div x-cloak x-show="open" x-transition class="absolute right-4 top-14 z-30 w-60 rounded-xl border border-line bg-white py-1 text-sm text-ink shadow-lg">
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
                    <a href="{{ route($item['route']) }}" class="block rounded-md px-3 py-2 text-sm text-white/90 hover:bg-white/10">{{ $item['label'] }}</a>
                @endforeach
            @endforeach
        </nav>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
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
                                        {{ $item['label'] }}
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
                <div class="ml-auto flex items-center gap-1" x-data="{ open: false }" @click.outside="open = false">
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
