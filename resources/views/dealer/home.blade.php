@php
    use App\Domain\Support\Money;

    $balance = $summary['balance'];
    $dealerProfile = auth('dealer')->user()->dealer;
    $dealerName = $dealerProfile->legal_name;
    $owes = bccomp($balance, '0', 2) > 0;
    $features = [
        ['percent', 'Size özel fiyatlar', 'Grubunuza ve hesabınıza tanımlı fiyat ve indirimler her üründe hazır.'],
        ['layers', 'Anlık stok bilgisi', 'Ürünün stokta olup olmadığını sipariş vermeden önce görün.'],
        ['check-circle', 'Adım adım sipariş takibi', 'Onaydan teslime kadar siparişinizin her aşamasından haberdar olun.'],
        ['file', 'Cari hesabınız elinizde', 'Bakiye, hareketler ve ekstreniz 7/24 tek tıkla erişilebilir.'],
    ];
    $address = trim(implode(', ', array_filter([$currentBusiness->address, $currentBusiness->district, $currentBusiness->city])));
@endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Dealer" title="Ana Sayfa" wide>
    {{-- ============ Ana slayt ============ --}}
    @if ($banners->isNotEmpty())
        @include('dealer._slider', ['slides' => $banners, 'variant' => 'hero'])
    @else
        <section class="relative overflow-hidden rounded-3xl bg-linear-to-br from-primary-dark via-primary to-sky-700 px-6 py-16 text-white shadow-xl sm:px-14 lg:py-24">
            <div class="auth-grid absolute inset-0"></div>
            <div class="relative max-w-2xl">
                <p class="text-sm font-semibold tracking-wider text-white/70 uppercase">{{ $currentBusiness->display_name }}</p>
                <h1 class="mt-3 text-4xl font-bold tracking-tight sm:text-5xl">Toptan siparişleriniz tek panelde</h1>
                <p class="mt-4 text-lg text-white/80">Size özel fiyatlarla güncel ürünlerimizi inceleyin, siparişinizi birkaç tıkla verin.</p>
                <a href="{{ route('dealer.catalog') }}" class="mt-8 inline-flex items-center gap-2 rounded-full bg-amber-400 px-7 py-3.5 font-semibold text-slate-900 shadow-lg hover:bg-amber-300">Tüm ürünlere göz atın <x-icon name="arrow-right" class="size-4" /></a>
            </div>
        </section>
    @endif

    {{-- ============ Karşılama ve kısa hesap özeti ============ --}}
    <section class="mt-6 overflow-hidden rounded-2xl border border-line bg-white shadow-xs">
        <div class="flex flex-col lg:flex-row lg:items-stretch">
            <div class="flex items-center gap-4 px-6 py-5 lg:flex-1">
                @if ($dealerProfile->logo_path)
                    <span class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-line bg-white p-1.5 shadow-sm"><img src="{{ route('dealer.logo') }}?v={{ $dealerProfile->updated_at?->timestamp }}" alt="{{ $dealerName }} logosu" class="max-h-full max-w-full object-contain"></span>
                @else
                    <span class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-linear-to-br from-primary to-sky-500 text-xl font-bold text-white shadow-md shadow-primary/20">{{ mb_substr($dealerName, 0, 1) }}</span>
                @endif
                <div class="min-w-0">
                    <p class="text-sm text-muted">Hoş geldiniz,</p>
                    <p class="truncate text-xl font-bold tracking-tight text-ink">{{ $dealerName }}</p>
                    <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted">
                        <span>Bayi kodu <strong class="font-semibold text-ink">{{ $summary['code'] }}</strong></span>
                        @if ($summary['group'])<span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 font-semibold text-amber-700 ring-1 ring-amber-200">{{ $summary['group'] }}</span>@endif
                    </p>
                </div>
            </div>
            <dl class="grid grid-cols-3 divide-x divide-line border-t border-line bg-slate-50/60 lg:w-[44rem] lg:border-t-0 lg:border-l">
                @foreach ([
                    [route('dealer.account.statement'), 'Cari bakiye', Money::format($balance), $owes ? 'text-danger' : 'text-ink', $owes ? 'Ekstreyi gör' : 'Borcunuz yok'],
                    [route('dealer.orders.index'), 'Devam eden sipariş', $summary['open_orders'], 'text-ink', 'Siparişlerim'],
                    [route('dealer.cart'), 'Sepetim', $summary['cart'].' ürün', 'text-ink', 'Sepete git'],
                ] as [$href, $label, $value, $tone, $hint])
                    <a href="{{ $href }}" class="group flex min-w-0 flex-col justify-center px-3 py-4 transition hover:bg-white sm:px-6">
                        <dt class="text-xs font-medium text-muted">{{ $label }}</dt>
                        <dd class="mt-1 truncate text-sm font-bold tabular-nums sm:text-lg {{ $tone }}">{{ $value }}</dd>
                        <span class="mt-0.5 inline-flex items-center gap-1 text-xs font-medium text-primary opacity-70 transition group-hover:opacity-100">{{ $hint }} <x-icon name="arrow-right" class="size-3" /></span>
                    </a>
                @endforeach
            </dl>
        </div>
    </section>

    {{-- ============ Ürün vitrinleri ============ --}}
    @if ($showcases->isNotEmpty())
        <section class="mt-14" x-data="{ tab: 0 }">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="inline-flex items-center gap-2 text-xs font-bold tracking-[0.2em] text-primary uppercase"><span class="size-1.5 rounded-full bg-amber-400"></span>Vitrin</p>
                    <h2 class="mt-2 text-2xl font-bold tracking-tight text-ink sm:text-3xl">Sizin için seçtiklerimiz</h2>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex max-w-full gap-1 overflow-x-auto rounded-full border border-line bg-white p-1 shadow-xs" role="tablist" aria-label="Ürün vitrinleri">
                        @foreach ($showcases as $t => $section)
                            <button type="button" role="tab" @click="tab = {{ $t }}" :aria-selected="tab === {{ $t }}"
                                class="shrink-0 rounded-full px-4 py-2 text-sm font-semibold whitespace-nowrap transition"
                                :class="tab === {{ $t }} ? 'bg-primary text-white shadow-sm' : 'text-muted hover:bg-slate-50 hover:text-ink'">{{ $section['showcase']->name }}</button>
                        @endforeach
                    </div>
                    @foreach ($showcases as $t => $section)
                        <a x-show="tab === {{ $t }}" @if ($t) x-cloak @endif href="{{ $section['more_url'] }}" class="inline-flex items-center gap-1.5 rounded-full border border-line bg-white px-4 py-2.5 text-sm font-semibold text-ink shadow-xs transition hover:border-primary/40 hover:text-primary">Tümünü gör <x-icon name="arrow-right" class="size-4" /></a>
                    @endforeach
                </div>
            </div>

            @foreach ($showcases as $t => $section)
                <div x-show="tab === {{ $t }}" @if ($t) x-cloak @endif role="tabpanel" class="mt-7 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach ($section['products'] as $product)
                        @include('dealer.catalog._card', ['product' => $product, 'prices' => $section['prices']])
                    @endforeach
                </div>
            @endforeach
        </section>
    @endif

    {{-- ============ Ara bant ============ --}}
    @if ($strips->isNotEmpty())
        @include('dealer._slider', ['slides' => $strips, 'variant' => 'strip', 'class' => 'mt-14'])
    @endif

    {{-- ============ Neden biz ============ --}}
    <section class="mt-14">
        <div class="text-center">
            <p class="inline-flex items-center gap-2 text-xs font-bold tracking-[0.2em] text-primary uppercase"><span class="size-1.5 rounded-full bg-amber-400"></span>Neden {{ $currentBusiness->display_name }}</p>
            <h2 class="mt-2 text-2xl font-bold tracking-tight text-ink sm:text-3xl">Siparişten tahsilata, her şey tek panelde</h2>
        </div>
        <div class="mt-8 grid gap-px overflow-hidden rounded-3xl border border-line bg-line shadow-xs sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($features as [$icon, $title, $text])
                <div class="flex flex-col gap-4 bg-white p-7">
                    <span class="flex size-12 items-center justify-center rounded-2xl bg-primary-soft text-primary ring-1 ring-primary/10"><x-icon :name="$icon" class="size-5" /></span>
                    <div>
                        <p class="text-base font-semibold text-ink">{{ $title }}</p>
                        <p class="mt-1.5 text-sm leading-relaxed text-muted">{{ $text }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ============ Tüm ürünler çağrısı ============ --}}
    <section class="relative mt-14 overflow-hidden rounded-3xl bg-linear-to-r from-primary-dark via-primary-dark to-primary text-white shadow-xl">
        <div class="auth-grid absolute inset-0"></div>
        <div class="pointer-events-none absolute -top-10 -right-10 text-white/[0.06]">
            <x-puzzle-logo :animated="false" mono class="size-72 rotate-12" />
        </div>
        <div class="relative flex flex-col items-start gap-7 px-6 py-12 sm:px-14 lg:flex-row lg:items-center lg:justify-between">
            <div class="max-w-xl">
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Aradığınızı bulamadınız mı?</h2>
                <p class="mt-2 text-white/75">Kategori, marka ve stok durumuna göre süzerek tüm ürünlerimize göz atın.</p>
            </div>
            <form method="GET" action="{{ route('dealer.catalog') }}" class="flex w-full max-w-xl items-center gap-2 rounded-full bg-white p-1.5 shadow-lg" role="search">
                <x-icon name="search" class="ml-4 size-5 shrink-0 text-slate-400" />
                <input name="q" placeholder="Ürün adı, kodu veya barkod" aria-label="Ürün ara"
                    class="min-w-0 flex-1 border-0 bg-transparent px-2 py-2.5 text-ink placeholder:text-slate-400 focus:ring-0">
                <button class="shrink-0 rounded-full bg-amber-400 px-7 py-3 font-semibold text-slate-900 transition hover:bg-amber-300">Ara</button>
            </form>
        </div>
    </section>

    {{-- ============ Alt bilgi ============ --}}
    <footer class="mt-14 overflow-hidden rounded-3xl bg-primary-dark text-sm text-white/70">
        <div class="grid gap-10 px-6 py-12 sm:px-14 lg:grid-cols-[1.4fr_1fr_1fr]">
            <div>
                <div class="flex items-center gap-3">
                    <x-puzzle-logo :animated="false" class="size-11" />
                    <p class="text-lg font-bold tracking-tight text-white">{{ $currentBusiness->display_name }}</p>
                </div>
                <p class="mt-4 max-w-md leading-relaxed">Kırtasiye toptan sipariş platformu. Size özel fiyatlar, anlık stok bilgisi ve 7/24 sipariş imkânı.</p>
                <p class="mt-4 text-xs text-white/50">{{ $currentBusiness->legal_name }}</p>
            </div>
            <ul class="space-y-2.5">
                <li class="mb-4 text-xs font-bold tracking-[0.15em] text-white uppercase">Alışveriş</li>
                <li><a href="{{ route('dealer.catalog') }}" class="transition hover:text-white">Tüm ürünler</a></li>
                <li><a href="{{ route('dealer.brands.index') }}" class="transition hover:text-white">Markalar</a></li>
                <li><a href="{{ route('dealer.catalog', ['yeni' => 1]) }}" class="transition hover:text-white">Yeni ürünler</a></li>
                <li><a href="{{ route('dealer.orders.index') }}" class="transition hover:text-white">Siparişlerim</a></li>
                <li><a href="{{ route('dealer.account.statement') }}" class="transition hover:text-white">Cari hesap</a></li>
            </ul>
            <ul class="space-y-3">
                <li class="mb-4 text-xs font-bold tracking-[0.15em] text-white uppercase">İletişim</li>
                @if ($currentBusiness->phone)
                    <li><a href="tel:{{ preg_replace('/[^0-9+]/', '', $currentBusiness->phone) }}" class="inline-flex items-center gap-2.5 transition hover:text-white"><x-icon name="phone" class="size-4 text-amber-400" />{{ $currentBusiness->phone }}</a></li>
                @endif
                @if ($currentBusiness->email)
                    <li><a href="mailto:{{ $currentBusiness->email }}" class="inline-flex items-center gap-2.5 transition hover:text-white"><x-icon name="mail" class="size-4 text-amber-400" />{{ $currentBusiness->email }}</a></li>
                @endif
                @if ($address)
                    <li class="flex items-start gap-2.5"><x-icon name="map-pin" class="mt-0.5 size-4 shrink-0 text-amber-400" /><span class="leading-relaxed">{{ $address }}</span></li>
                @endif
                <li class="pt-1">
                    <a href="{{ route('dealer.announcements.index') }}" class="transition hover:text-white">Duyurular</a>
                    @if ($currentBusiness->moduleEnabled('messaging'))<span class="mx-2 text-white/30">·</span><a href="{{ route('dealer.messages.index') }}" class="transition hover:text-white">Bize yazın</a>@endif
                </li>
            </ul>
        </div>
        <div class="flex flex-col gap-2 border-t border-white/10 px-6 py-5 text-xs text-white/50 sm:flex-row sm:items-center sm:justify-between sm:px-14">
            <p>© {{ now()->year }} {{ $currentBusiness->display_name }}. Tüm hakları saklıdır.</p>
            <p class="inline-flex items-center gap-1.5"><x-icon name="lock" class="size-3.5" /> Güvenli bayi paneli · yalnızca yetkili hesaplar</p>
        </div>
    </footer>
</x-layouts.panel>
