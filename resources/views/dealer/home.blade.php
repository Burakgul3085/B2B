@php
    use App\Domain\Support\Money;

    $positions = [
        'left' => ['justify-start text-left', 'items-start', 'bg-linear-to-r from-slate-950/80 via-slate-950/35 to-transparent'],
        'center' => ['justify-center text-center', 'items-center', 'bg-slate-950/45'],
        'right' => ['justify-end text-right', 'items-end', 'bg-linear-to-l from-slate-950/80 via-slate-950/35 to-transparent'],
    ];
    $balance = $summary['balance'];
@endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Dealer" title="Ana Sayfa">
    {{-- ============ Slaytlar ============ --}}
    @if ($banners->isNotEmpty())
        <section x-data="heroSlider({{ $banners->count() }})" @mouseenter="pause()" @mouseleave="resume()" @focusin="pause()" @focusout="resume()"
            @touchstart.passive="touchStart($event)" @touchend="touchEnd($event)"
            class="group/slider relative overflow-hidden rounded-3xl bg-slate-900 shadow-xl ring-1 shadow-slate-900/10 ring-black/5"
            aria-roledescription="carousel" aria-label="Tanıtımlar">
            <div class="relative aspect-[4/3] sm:aspect-[16/7] lg:aspect-[3/1]">
                @foreach ($banners as $n => $banner)
                    @php [$justify, $align, $scrim] = $positions[$banner->text_position] ?? $positions['left']; $hasText = $banner->title || $banner->subtitle; @endphp
                    <div class="absolute inset-0 transition-opacity duration-700 ease-out" :class="i === {{ $n }} ? 'z-10 opacity-100' : 'pointer-events-none z-0 opacity-0'"
                        role="group" aria-roledescription="slayt" aria-label="{{ $n + 1 }} / {{ $banners->count() }}" :aria-hidden="i !== {{ $n }}">
                        <x-banner-media :banner="$banner" route="dealer.storefront.media" :index="$n" :eager="$n === 0" :loop="$banners->count() === 1" />

                        @if ($hasText)
                            <div class="absolute inset-0 {{ $scrim }}"></div>
                            <div class="absolute inset-0 flex {{ $justify }} px-6 sm:px-12 lg:px-16">
                                <div class="flex max-w-xl flex-col justify-center {{ $align }} gap-3 py-8 text-white sm:gap-4">
                                    @if ($banner->title)
                                        <h2 class="text-2xl leading-tight font-bold tracking-tight drop-shadow-sm sm:text-4xl lg:text-5xl">{{ $banner->title }}</h2>
                                    @endif
                                    @if ($banner->subtitle)
                                        <p class="hidden max-w-md text-sm leading-relaxed text-white/85 sm:block sm:text-base lg:text-lg">{{ $banner->subtitle }}</p>
                                    @endif
                                    @if ($banner->url && $banner->button_label)
                                        <a href="{{ $banner->url }}" class="mt-1 inline-flex items-center gap-2 rounded-full bg-white px-5 py-2.5 text-sm font-semibold text-ink shadow-lg transition hover:bg-amber-300 sm:px-6 sm:py-3">
                                            {{ $banner->button_label }} <x-icon name="arrow-right" class="size-4" />
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endif
                        @if ($banner->url && ! ($hasText && $banner->button_label))
                            <a href="{{ $banner->url }}" class="absolute inset-0" aria-label="{{ $banner->title ?: 'Kampanyayı görüntüle' }}"></a>
                        @endif
                    </div>
                @endforeach
            </div>

            @if ($banners->count() > 1)
                {{-- Oklar: masaüstünde üzerine gelince belirir --}}
                @foreach (['prev' => ['left-3 sm:left-5', 'Önceki', 'rotate-180'], 'next' => ['right-3 sm:right-5', 'Sonraki', '']] as $action => [$pos, $label, $rotate])
                    <button type="button" @click="{{ $action }}()" aria-label="{{ $label }} slayt"
                        class="absolute top-1/2 {{ $pos }} z-20 hidden size-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/15 text-white ring-1 ring-white/30 backdrop-blur-md transition hover:bg-white/30 sm:flex sm:opacity-0 sm:group-hover/slider:opacity-100 focus-visible:opacity-100">
                        <x-icon name="arrow-right" class="size-5 {{ $rotate }}" />
                    </button>
                @endforeach

                {{-- Noktalar: etkin olanda ilerleme çubuğu --}}
                <div class="absolute inset-x-0 bottom-4 z-20 flex justify-center gap-2" role="tablist">
                    @foreach ($banners as $n => $banner)
                        <button type="button" @click="go({{ $n }})" role="tab" :aria-selected="i === {{ $n }}" aria-label="{{ $n + 1 }}. slayt"
                            class="relative h-2 overflow-hidden rounded-full bg-white/40 transition-all duration-300" :class="i === {{ $n }} ? 'w-10' : 'w-2 hover:bg-white/70'">
                            <span x-show="i === {{ $n }}" class="slider-progress absolute inset-y-0 left-0 rounded-full bg-white"
                                :style="`animation-duration: ${duration}ms; animation-play-state: ${paused || reduced ? 'paused' : 'running'}`"></span>
                        </button>
                    @endforeach
                </div>
            @endif
        </section>
    @else
        <section class="relative overflow-hidden rounded-3xl bg-linear-to-br from-primary-dark via-primary to-sky-700 px-6 py-14 text-white shadow-xl sm:px-12">
            <div class="auth-grid absolute inset-0"></div>
            <div class="relative max-w-xl">
                <p class="text-sm font-semibold tracking-wider text-white/70 uppercase">{{ $currentBusiness->display_name }}</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">Toptan siparişleriniz tek panelde</h1>
                <p class="mt-3 text-white/80">Size özel fiyatlarla güncel ürünlerimizi inceleyin, siparişinizi birkaç tıkla verin.</p>
                <a href="{{ route('dealer.catalog') }}" class="mt-6 inline-flex items-center gap-2 rounded-full bg-white px-6 py-3 text-sm font-semibold text-ink shadow-lg hover:bg-amber-300">Tüm ürünlere göz atın <x-icon name="arrow-right" class="size-4" /></a>
            </div>
        </section>
    @endif

    {{-- ============ Karşılama ve kısa hesap özeti ============ --}}
    <section class="mt-6 flex flex-col gap-4 rounded-2xl border border-line bg-white p-5 shadow-xs lg:flex-row lg:items-center lg:justify-between">
        <div>
            <p class="text-sm text-muted">Hoş geldiniz,</p>
            <p class="text-lg font-semibold text-ink">{{ auth('dealer')->user()->dealer->legal_name }}</p>
        </div>
        <dl class="grid grid-cols-3 divide-x divide-line rounded-xl bg-slate-50 ring-1 ring-line lg:min-w-[34rem]">
            <a href="{{ route('dealer.account.statement') }}" class="group px-4 py-3 transition hover:bg-white">
                <dt class="text-xs text-muted">Cari bakiye</dt>
                <dd @class(['mt-0.5 font-semibold tabular-nums', 'text-danger' => bccomp($balance, '0', 2) > 0, 'text-ink' => bccomp($balance, '0', 2) <= 0])>{{ Money::format($balance) }}</dd>
            </a>
            <a href="{{ route('dealer.orders.index') }}" class="group px-4 py-3 transition hover:bg-white">
                <dt class="text-xs text-muted">Devam eden sipariş</dt>
                <dd class="mt-0.5 font-semibold text-ink tabular-nums">{{ $summary['open_orders'] }}</dd>
            </a>
            <a href="{{ route('dealer.cart') }}" class="group px-4 py-3 transition hover:bg-white">
                <dt class="text-xs text-muted">Sepetim</dt>
                <dd class="mt-0.5 font-semibold text-ink tabular-nums">{{ $summary['cart'] }} ürün</dd>
            </a>
        </dl>
    </section>

    {{-- ============ Ürün vitrinleri ============ --}}
    @if ($showcases->isNotEmpty())
        <section class="mt-10" x-data="{ tab: 0 }">
            <div class="flex flex-wrap items-end justify-between gap-4 border-b border-line">
                <div class="-mb-px flex max-w-full gap-1 overflow-x-auto" role="tablist" aria-label="Ürün vitrinleri">
                    @foreach ($showcases as $t => $section)
                        <button type="button" role="tab" @click="tab = {{ $t }}" :aria-selected="tab === {{ $t }}"
                            class="shrink-0 border-b-2 px-4 pb-3 text-sm font-semibold whitespace-nowrap transition sm:text-base"
                            :class="tab === {{ $t }} ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-ink'">{{ $section['showcase']->name }}</button>
                    @endforeach
                </div>
                @foreach ($showcases as $t => $section)
                    <a x-show="tab === {{ $t }}" @if ($t) x-cloak @endif href="{{ $section['more_url'] }}" class="mb-3 inline-flex items-center gap-1 text-sm font-semibold text-primary hover:underline">Tümünü gör <x-icon name="arrow-right" class="size-4" /></a>
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

    {{-- ============ Tüm ürünler çağrısı ============ --}}
    <section class="mt-12 overflow-hidden rounded-3xl bg-linear-to-r from-primary-dark to-primary text-white shadow-lg">
        <div class="flex flex-col items-start gap-6 px-6 py-8 sm:px-10 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-xl font-bold tracking-tight sm:text-2xl">Aradığınızı bulamadınız mı?</h2>
                <p class="mt-1 text-sm text-white/75">Kategori, marka ve stok durumuna göre süzerek tüm ürünlerimize göz atın.</p>
            </div>
            <form method="GET" action="{{ route('dealer.catalog') }}" class="flex w-full max-w-md gap-2" role="search">
                <input name="q" placeholder="Ürün adı, kodu veya barkod" aria-label="Ürün ara"
                    class="min-w-0 flex-1 rounded-full border-0 bg-white/15 px-5 py-3 text-sm text-white placeholder:text-white/60 focus:bg-white focus:text-ink focus:ring-2 focus:ring-white/50">
                <button class="shrink-0 rounded-full bg-white px-5 py-3 text-sm font-semibold text-ink shadow hover:bg-amber-300">Ara</button>
            </form>
        </div>
    </section>

    {{-- ============ Alt bilgi ============ --}}
    <footer class="mt-12 border-t border-line pt-8 pb-2 text-sm text-muted">
        <div class="flex flex-col gap-6 sm:flex-row sm:justify-between">
            <div>
                <p class="font-semibold text-ink">{{ $currentBusiness->display_name }}</p>
                <p class="mt-1 max-w-sm">{{ $currentBusiness->legal_name }}@if ($currentBusiness->address)<br>{{ trim(implode(', ', array_filter([$currentBusiness->address, $currentBusiness->district, $currentBusiness->city]))) }}@endif</p>
            </div>
            <div class="flex gap-10">
                <ul class="space-y-1.5">
                    <li class="mb-2 text-xs font-semibold tracking-wider text-ink uppercase">Alışveriş</li>
                    <li><a href="{{ route('dealer.catalog') }}" class="hover:text-primary">Tüm ürünler</a></li>
                    <li><a href="{{ route('dealer.orders.index') }}" class="hover:text-primary">Siparişlerim</a></li>
                    <li><a href="{{ route('dealer.account.statement') }}" class="hover:text-primary">Cari hesap</a></li>
                </ul>
                <ul class="space-y-1.5">
                    <li class="mb-2 text-xs font-semibold tracking-wider text-ink uppercase">İletişim</li>
                    @if ($currentBusiness->phone)<li><a href="tel:{{ preg_replace('/[^0-9+]/', '', $currentBusiness->phone) }}" class="hover:text-primary">{{ $currentBusiness->phone }}</a></li>@endif
                    @if ($currentBusiness->email)<li><a href="mailto:{{ $currentBusiness->email }}" class="hover:text-primary">{{ $currentBusiness->email }}</a></li>@endif
                    <li><a href="{{ route('dealer.announcements.index') }}" class="hover:text-primary">Duyurular</a></li>
                </ul>
            </div>
        </div>
    </footer>
</x-layouts.panel>
