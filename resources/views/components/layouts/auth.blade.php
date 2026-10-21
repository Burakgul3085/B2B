@props(['area', 'title', 'subtitle' => null])
@php
    use App\Domain\Auth\Area;

    /** @var Area $area */
    $brand = config('b2b.brand.name');
    $tagline = config('b2b.brand.tagline');

    // Her alanın kendi tonu ve tanıtım metni; düzen ortaktır.
    $theme = match ($area) {
        Area::Dealer => [
            'icon' => 'package',
            'eyebrow' => 'Bayi Paneli',
            'badge' => 'Bayi Girişi',
            'badgeClass' => 'bg-blue-50 text-blue-800 ring-blue-200',
            'headline' => 'Toptan siparişleriniz tek panelde',
            'lead' => 'Size özel fiyatlarla katalogu inceleyin, siparişinizi verin, cari hesabınızı anlık takip edin.',
            'features' => [
                ['layers', 'Size özel fiyatlarla güncel katalog'],
                ['check-circle', 'Sipariş durumunu adım adım takip'],
                ['file', 'Cari hesap ekstresi ve raporlar'],
            ],
            'bg' => 'from-[#0b1640] via-primary-dark to-primary',
            'glow' => 'bg-sky-400/25',
        ],
        Area::Business => [
            'icon' => 'building',
            'eyebrow' => 'Toptancı Paneli',
            'badge' => 'Toptancı Girişi',
            'badgeClass' => 'bg-teal-50 text-teal-800 ring-teal-200',
            'headline' => 'İşletmenizi tek merkezden yönetin',
            'lead' => 'Ürün, stok, bayi, fiyat ve sipariş süreçleri; anlık raporlarla birlikte.',
            'features' => [
                ['warehouse', 'Çok depolu stok ve sayım yönetimi'],
                ['users', 'Bayi, grup ve özel fiyat listeleri'],
                ['file', 'Sipariş, tahsilat ve kurumsal raporlar'],
            ],
            'bg' => 'from-[#04201f] via-[#0b3b3a] to-[#115e59]',
            'glow' => 'bg-emerald-400/20',
        ],
        Area::Platform => [
            'icon' => 'shield',
            'eyebrow' => 'Platform Yönetimi',
            'badge' => 'Platform Yönetici Girişi',
            'badgeClass' => 'bg-slate-100 text-slate-800 ring-slate-300',
            'headline' => 'Yetkili yönetim alanı',
            'lead' => 'Bu alan yalnızca platform yöneticileri içindir. Tüm girişler iki adımlı doğrulama ile korunur ve kayıt altına alınır.',
            'features' => [
                ['building', 'İşletme, lisans ve modül yönetimi'],
                ['key', 'İki adımlı doğrulama zorunlu'],
                ['list', 'Denetim kayıtları ve e-posta durumu'],
            ],
            'bg' => 'from-[#050608] via-[#0f141c] to-graphite',
            'glow' => 'bg-amber-400/15',
        ],
        Area::StoreAdmin => [
            'icon' => 'cart',
            'eyebrow' => 'Mağaza Yönetimi',
            'badge' => 'Mağaza Yönetim Girişi',
            'badgeClass' => 'bg-violet-50 text-violet-800 ring-violet-200',
            'headline' => 'Online mağazanızı tek yerden yönetin',
            'lead' => 'Ürünler, stok, fiyatlar, siparişler, ödeme ve teslimat; mağazanın her şeyi bu panelde.',
            'features' => [
                ['package', 'Mağazaya özel ürün, fiyat ve stok'],
                ['file', 'Sipariş, ödeme ve teslimat takibi'],
                ['shield', 'Yönetici hesapları ve işlem geçmişi'],
            ],
            'bg' => 'from-[#1a0b33] via-[#2e1065] to-[#5b21b6]',
            'glow' => 'bg-fuchsia-400/20',
        ],
    };
    if ($area === Area::StoreAdmin) {
        $brand = config('store.name');
        $tagline = 'Mağaza yönetim paneli';
    }

    // İsteğe bağlı arka plan fotoğrafı: önce alana özel, sonra ortak dosya.
    $photo = collect([$area->prefix(), null])
        ->flatMap(fn ($suffix) => collect(['jpg', 'jpeg', 'webp', 'png'])->map(fn ($ext) => 'images/giris'.($suffix ? "-{$suffix}" : '').".{$ext}"))
        ->first(fn ($path) => is_file(public_path($path)));
@endphp
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $theme['badge'] }} · {{ $brand }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-graphite">
    <div class="relative isolate flex min-h-screen overflow-hidden bg-linear-to-br {{ $theme['bg'] }}">
        {{-- Arka plan: fotoğraf (varsa) + koyu katman + ince ızgara + ışık --}}
        @if ($photo)
            <div class="absolute inset-0 -z-20 bg-cover bg-center" style="background-image: url('{{ asset($photo) }}')"></div>
            <div class="absolute inset-0 -z-10 bg-linear-to-r from-slate-950/85 via-slate-950/60 to-slate-950/30"></div>
        @else
            <div class="auth-grid absolute inset-0 -z-10"></div>
            <div class="absolute -top-40 -left-32 -z-10 size-[34rem] rounded-full blur-3xl {{ $theme['glow'] }}"></div>
            <div class="absolute -right-40 -bottom-48 -z-10 size-[30rem] rounded-full bg-white/5 blur-3xl"></div>
        @endif
        <div class="pointer-events-none absolute -right-24 -bottom-28 -z-10 hidden text-white/[0.04] lg:block">
            <x-puzzle-logo :animated="false" mono class="puzzle-float size-[34rem]" />
        </div>

        <div class="mx-auto flex w-full max-w-6xl flex-col px-4 py-6 sm:px-8 lg:py-10">
            {{-- Üst şerit: marka --}}
            <header class="flex items-center justify-between gap-4 text-white">
                <div class="flex items-center gap-3 lg:invisible">
                    <x-puzzle-logo class="size-14 shrink-0" />
                    <div class="leading-tight">
                        <p class="text-lg font-semibold tracking-tight">{{ $brand }}</p>
                        <p class="text-xs text-white/60">{{ $tagline }}</p>
                    </div>
                </div>
                <span class="hidden items-center gap-2 rounded-full bg-white/10 px-3.5 py-1.5 text-xs font-semibold ring-1 ring-white/20 backdrop-blur sm:inline-flex">
                    <x-icon :name="$theme['icon']" class="size-3.5" /> {{ $theme['badge'] }}
                </span>
            </header>

            <main class="grid flex-1 items-center gap-10 py-10 lg:grid-cols-[1.1fr_minmax(0,28rem)] lg:gap-16">
                {{-- Tanıtım (geniş ekran) --}}
                <section class="hidden text-white lg:block">
                    {{-- Marka: animasyonlu yapboz logosu --}}
                    <div class="mb-8 flex items-center gap-4">
                        <span class="relative size-32 shrink-0">
                            <span class="absolute inset-4 -z-10 rounded-full bg-white/20 blur-2xl"></span>
                            <x-puzzle-logo class="size-full" />
                        </span>
                        <div>
                            <p class="text-3xl leading-none font-bold tracking-tight">{{ $brand }}</p>
                            <p class="mt-2.5 inline-flex items-center gap-2 text-sm text-white/65">
                                <span class="h-px w-6 bg-white/40"></span>{{ $tagline }}
                            </p>
                        </div>
                    </div>

                    <p class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold tracking-wider uppercase ring-1 ring-white/15">
                        <span class="size-1.5 rounded-full bg-emerald-400"></span>{{ $theme['eyebrow'] }}
                    </p>
                    <h2 class="mt-6 max-w-lg text-4xl leading-tight font-semibold tracking-tight xl:text-5xl">{{ $theme['headline'] }}</h2>
                    <p class="mt-5 max-w-md text-base leading-relaxed text-white/70">{{ $theme['lead'] }}</p>

                    <ul class="mt-10 space-y-4">
                        @foreach ($theme['features'] as [$icon, $text])
                            <li class="flex items-center gap-3 text-sm text-white/85">
                                <span class="flex size-9 items-center justify-center rounded-lg bg-white/10 ring-1 ring-white/15">
                                    <x-icon :name="$icon" class="size-4" />
                                </span>
                                {{ $text }}
                            </li>
                        @endforeach
                    </ul>
                </section>

                {{-- Giriş kartı --}}
                <section class="w-full">
                    <div class="rounded-2xl bg-white/95 p-6 shadow-2xl ring-1 shadow-black/30 ring-white/40 backdrop-blur-xl sm:p-9">
                        <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold ring-1 {{ $theme['badgeClass'] }}">
                            <x-icon :name="$theme['icon']" class="size-3.5" /> {{ $theme['badge'] }}
                        </span>
                        <h1 class="mt-4 text-2xl font-semibold tracking-tight text-ink">{{ $title }}</h1>
                        @if ($subtitle)
                            <p class="mt-1.5 text-sm text-muted">{{ $subtitle }}</p>
                        @endif

                        <div class="mt-7">
                            <x-flash :summary="false" />
                            {{ $slot }}
                        </div>

                        @isset($footer)
                            <div class="mt-7 border-t border-line pt-6 text-sm text-muted">{{ $footer }}</div>
                        @endisset
                    </div>
                </section>
            </main>

            <footer class="flex flex-wrap items-center justify-between gap-2 text-xs text-white/50">
                <p>© {{ now()->year }} {{ $brand }}</p>
                <p class="inline-flex items-center gap-1.5"><x-icon name="lock" class="size-3.5" /> Yalnızca yetkili hesaplar erişebilir</p>
            </footer>
        </div>
    </div>
</body>
</html>
