{{-- Sipariş ve Teslimat: tutarlar ve seçenekler ayarlardan canlı gelir --}}
@php use App\Domain\Support\Money; @endphp
<x-store.help-layout :key="$key" :c="$c" icon="truck">
    @if ($c['show_rates'])
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-3xl border border-line p-6">
                <span class="flex size-12 items-center justify-center rounded-2xl bg-puzzle-orange-soft text-puzzle-orange"><x-icon name="truck" class="size-6" /></span>
                <p class="mt-4 font-display text-lg font-extrabold">Kargo ile teslimat</p>
                <p class="mt-1 text-sm text-muted">Türkiye'nin her yerine.</p>
                <dl class="mt-4 space-y-2 text-sm">
                    @forelse ($carriers as $carrier)
                        <div class="flex items-center justify-between gap-3"><dt class="flex items-center gap-2 text-muted">
                            @if ($logo = $carrier->logoUrl())<span class="flex h-7 w-20 items-center justify-center rounded-md bg-[#fff] px-1.5 ring-1 ring-line"><img src="{{ $logo }}" alt="" class="max-h-4 max-w-full object-contain" loading="lazy"></span>@endif
                            {{ $carrier->name }}</dt><dd class="font-semibold tabular-nums">{{ bccomp((string) $carrier->fee, '0', 2) > 0 ? Money::format($carrier->fee) : 'Ücretsiz' }}</dd></div>
                    @empty
                        <div class="flex justify-between gap-3"><dt class="text-muted">Kargo ücreti</dt><dd class="font-semibold tabular-nums">{{ $cargoFee && bccomp($cargoFee, '0', 2) > 0 ? Money::format($cargoFee) : 'Ücretsiz' }}</dd></div>
                    @endforelse
                    @if ($info['free_threshold'])<div class="flex justify-between gap-3"><dt class="text-muted">Ücretsiz kargo</dt><dd class="font-semibold text-puzzle-green tabular-nums">{{ Money::format($info['free_threshold']) }} ve üzeri</dd></div>@endif
                    @if ($info['cutoff'])<div class="flex justify-between gap-3"><dt class="text-muted">Aynı gün kargo</dt><dd class="font-semibold">Hafta içi {{ $info['cutoff'] }} öncesi</dd></div>@endif
                </dl>
            </div>
            @php $codOn = \App\Models\Store\StoreSetting::flag('cod_enabled'); @endphp
            @if ($info['local'])
                <div class="rounded-3xl border border-line p-6">
                    <span class="flex size-12 items-center justify-center rounded-2xl bg-puzzle-green-soft text-puzzle-green"><x-icon name="map-pin" class="size-6" /></span>
                    <p class="mt-4 font-display text-lg font-extrabold">Eskişehir eve teslim</p>
                    <p class="mt-1 text-sm text-muted">Eskişehir'de teslimat yaptığımız ilçelerdeki adreslere kendi aracımızla.</p>
                    <dl class="mt-4 space-y-2 text-sm">
                        @if ($localMin)<div class="flex justify-between gap-3"><dt class="text-muted">En düşük sipariş</dt><dd class="font-semibold tabular-nums">{{ Money::format($localMin) }}</dd></div>@endif
                        <div class="flex justify-between gap-3"><dt class="text-muted">Teslimat ücreti</dt><dd class="font-semibold tabular-nums">{{ $localFee && bccomp($localFee, '0', 2) > 0 ? Money::format($localFee) : 'Ücretsiz' }}</dd></div>
                        @if ($localFree && $localFee && bccomp($localFee, '0', 2) > 0)<div class="flex justify-between gap-3"><dt class="text-muted">Ücretsiz eve teslim</dt><dd class="font-semibold text-puzzle-green tabular-nums">{{ Money::format($localFree) }} ve üzeri</dd></div>@endif
                        @if ($codOn)<div class="flex justify-between gap-3"><dt class="text-muted">Kapıda ödeme (nakit / POS)</dt><dd class="font-semibold">Var</dd></div>@endif
                    </dl>
                </div>
            @endif
            @if ($info['pickup'])
                <div class="rounded-3xl border border-line p-6">
                    <span class="flex size-12 items-center justify-center rounded-2xl bg-puzzle-blue-soft text-puzzle-blue"><x-icon name="building" class="size-6" /></span>
                    <p class="mt-4 font-display text-lg font-extrabold">Mağazadan teslim al</p>
                    <p class="mt-1 text-sm text-muted">Eskişehir'deki siparişlerde ücretsiz. Siparişiniz hazır olunca e-postayla haber veririz.@if ($storeAddress) Mağaza adresi: {{ $storeAddress }}.@endif</p>
                    <dl class="mt-4 space-y-2 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-muted">Ücret</dt><dd class="font-semibold text-puzzle-green">Ücretsiz</dd></div>
                        @if ($codOn)<div class="flex justify-between gap-3"><dt class="text-muted">Mağazada ödeme (nakit / POS)</dt><dd class="font-semibold">Var</dd></div>@endif
                    </dl>
                </div>
            @endif
            @if ($codOn && ($info['local'] || $info['pickup']))
                <p class="rounded-3xl bg-surface p-6 text-sm text-slate-700 sm:col-span-2">Kapıda ve mağazada ödeme yalnızca Eskişehir'deki siparişlerde geçerlidir. Başka şehirlere kargoyla gönderilen siparişler kartla veya havale / EFT ile ödenir.</p>
            @endif
            <div class="rounded-3xl bg-surface p-6 sm:col-span-2">
                <p class="flex items-center gap-2 font-semibold"><x-icon name="refresh" class="size-5 text-puzzle-blue" /> İade hakkı</p>
                <p class="mt-1 text-sm text-slate-700">Teslim aldığınız ürünü {{ $info['return_days'] }} gün içinde iade edebilirsiniz. Ayrıntılar <a href="{{ route('store.legal', 'iptal-iade') }}" class="font-semibold text-navy-ink underline">İptal ve İade Koşulları</a> sayfasında.</p>
            </div>
        </div>
    @endif
</x-store.help-layout>
