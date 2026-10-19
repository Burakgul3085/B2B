@php
    use App\Domain\Support\Money;

    $balance = $summary['balance'];
    $dealerName = auth('dealer')->user()->dealer->legal_name;
    $features = [
        ['percent', 'Size özel fiyatlar', 'Grubunuza ve hesabınıza tanımlı fiyat ve indirimler her üründe hazır.'],
        ['layers', 'Anlık stok bilgisi', 'Ürünün stokta olup olmadığını sipariş vermeden önce görün.'],
        ['check-circle', 'Adım adım sipariş takibi', 'Onaydan teslime kadar siparişinizin her aşamasından haberdar olun.'],
        ['file', 'Cari hesabınız elinizde', 'Bakiye, hareketler ve ekstreniz 7/24 tek tıkla erişilebilir.'],
    ];
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
    <section class="mt-6 flex flex-col gap-4 rounded-2xl border border-line bg-white px-6 py-5 shadow-xs lg:flex-row lg:items-center lg:justify-between">
        <div class="flex items-center gap-4">
            <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-primary-soft text-lg font-bold text-primary">{{ mb_substr($dealerName, 0, 1) }}</span>
            <div>
                <p class="text-sm text-muted">Hoş geldiniz,</p>
                <p class="text-lg font-semibold text-ink">{{ $dealerName }}</p>
            </div>
        </div>
        <dl class="grid grid-cols-3 gap-2 sm:gap-3 lg:min-w-[40rem]">
            @foreach ([
                [route('dealer.account.statement'), 'Cari bakiye', Money::format($balance), bccomp($balance, '0', 2) > 0 ? 'text-danger' : 'text-ink', 'list'],
                [route('dealer.orders.index'), 'Devam eden sipariş', $summary['open_orders'], 'text-ink', 'file'],
                [route('dealer.cart'), 'Sepetim', $summary['cart'].' ürün', 'text-ink', 'cart'],
            ] as [$href, $label, $value, $tone, $icon])
                <a href="{{ $href }}" class="group flex items-center gap-3 rounded-xl bg-slate-50 px-3 py-3 ring-1 ring-line transition hover:bg-white hover:ring-primary/30 sm:px-4">
                    <span class="hidden size-9 shrink-0 items-center justify-center rounded-lg bg-white text-muted ring-1 ring-line group-hover:text-primary sm:flex"><x-icon :name="$icon" class="size-4" /></span>
                    <span class="min-w-0">
                        <dt class="truncate text-xs text-muted">{{ $label }}</dt>
                        <dd class="truncate font-semibold tabular-nums {{ $tone }}">{{ $value }}</dd>
                    </span>
                </a>
            @endforeach
        </dl>
    </section>

    {{-- ============ Ürün vitrinleri ============ --}}
    @if ($showcases->isNotEmpty())
        <section class="mt-12" x-data="{ tab: 0 }">
            <div class="flex flex-wrap items-end justify-between gap-x-6 gap-y-3 border-b border-line">
                <div class="-mb-px flex max-w-full gap-2 overflow-x-auto" role="tablist" aria-label="Ürün vitrinleri">
                    @foreach ($showcases as $t => $section)
                        <button type="button" role="tab" @click="tab = {{ $t }}" :aria-selected="tab === {{ $t }}"
                            class="shrink-0 border-b-[3px] px-4 pb-3 text-base font-semibold whitespace-nowrap transition sm:text-lg"
                            :class="tab === {{ $t }} ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-ink'">{{ $section['showcase']->name }}</button>
                    @endforeach
                </div>
                @foreach ($showcases as $t => $section)
                    <a x-show="tab === {{ $t }}" @if ($t) x-cloak @endif href="{{ $section['more_url'] }}" class="mb-3 inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-semibold text-primary transition hover:bg-primary-soft">Tümünü gör <x-icon name="arrow-right" class="size-4" /></a>
                @endforeach
            </div>

            @foreach ($showcases as $t => $section)
                <div x-show="tab === {{ $t }}" @if ($t) x-cloak @endif role="tabpanel" class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach ($section['products'] as $product)
                        @include('dealer.catalog._card', ['product' => $product, 'prices' => $section['prices']])
                    @endforeach
                </div>
            @endforeach
        </section>
    @endif

    {{-- ============ Ara bant ============ --}}
    @if ($strips->isNotEmpty())
        @include('dealer._slider', ['slides' => $strips, 'variant' => 'strip', 'class' => 'mt-12'])
    @endif

    {{-- ============ Neden biz ============ --}}
    <section class="mt-12">
        <h2 class="text-center text-sm font-semibold tracking-[0.2em] text-muted uppercase">{{ $currentBusiness->display_name }} ile çalışmanın kolaylıkları</h2>
        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($features as [$icon, $title, $text])
                <div class="flex items-start gap-4 rounded-2xl border border-line bg-white p-5 shadow-xs">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary-soft text-primary"><x-icon :name="$icon" class="size-5" /></span>
                    <div>
                        <p class="font-semibold text-ink">{{ $title }}</p>
                        <p class="mt-1 text-sm leading-relaxed text-muted">{{ $text }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ============ Tüm ürünler çağrısı ============ --}}
    <section class="relative mt-12 overflow-hidden rounded-3xl bg-linear-to-r from-primary-dark to-primary text-white shadow-lg">
        <div class="auth-grid absolute inset-0"></div>
        <div class="relative flex flex-col items-start gap-6 px-6 py-10 sm:px-12 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Aradığınızı bulamadınız mı?</h2>
                <p class="mt-2 text-white/75">Kategori, marka ve stok durumuna göre süzerek tüm ürünlerimize göz atın.</p>
            </div>
            <form method="GET" action="{{ route('dealer.catalog') }}" class="flex w-full max-w-xl gap-2" role="search">
                <input name="q" placeholder="Ürün adı, kodu veya barkod" aria-label="Ürün ara"
                    class="min-w-0 flex-1 rounded-full border-0 bg-white/15 px-6 py-3.5 text-white placeholder:text-white/60 focus:bg-white focus:text-ink focus:ring-2 focus:ring-white/50">
                <button class="shrink-0 rounded-full bg-amber-400 px-7 py-3.5 font-semibold text-slate-900 shadow hover:bg-amber-300">Ara</button>
            </form>
        </div>
    </section>

    {{-- ============ Alt bilgi ============ --}}
    <footer class="mt-12 rounded-3xl bg-primary-dark px-6 py-10 text-sm text-white/70 sm:px-12">
        <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
            <div class="lg:col-span-2">
                <p class="text-lg font-semibold text-white">{{ $currentBusiness->display_name }}</p>
                <p class="mt-2 max-w-md leading-relaxed">{{ $currentBusiness->legal_name }}@if ($currentBusiness->address)<br>{{ trim(implode(', ', array_filter([$currentBusiness->address, $currentBusiness->district, $currentBusiness->city]))) }}@endif</p>
            </div>
            <ul class="space-y-2">
                <li class="mb-3 text-xs font-semibold tracking-wider text-white uppercase">Alışveriş</li>
                <li><a href="{{ route('dealer.catalog') }}" class="hover:text-white">Tüm ürünler</a></li>
                <li><a href="{{ route('dealer.orders.index') }}" class="hover:text-white">Siparişlerim</a></li>
                <li><a href="{{ route('dealer.account.statement') }}" class="hover:text-white">Cari hesap</a></li>
                <li><a href="{{ route('dealer.dashboard') }}" class="hover:text-white">Hesap özeti</a></li>
            </ul>
            <ul class="space-y-2">
                <li class="mb-3 text-xs font-semibold tracking-wider text-white uppercase">İletişim</li>
                @if ($currentBusiness->phone)<li><a href="tel:{{ preg_replace('/[^0-9+]/', '', $currentBusiness->phone) }}" class="hover:text-white">{{ $currentBusiness->phone }}</a></li>@endif
                @if ($currentBusiness->email)<li><a href="mailto:{{ $currentBusiness->email }}" class="hover:text-white">{{ $currentBusiness->email }}</a></li>@endif
                <li><a href="{{ route('dealer.announcements.index') }}" class="hover:text-white">Duyurular</a></li>
                @if ($currentBusiness->moduleEnabled('messaging'))<li><a href="{{ route('dealer.messages.index') }}" class="hover:text-white">Bize yazın</a></li>@endif
            </ul>
        </div>
        <p class="mt-8 border-t border-white/10 pt-6 text-xs text-white/50">© {{ now()->year }} {{ $currentBusiness->display_name }} · Bayi sipariş platformu</p>
    </footer>
</x-layouts.panel>
