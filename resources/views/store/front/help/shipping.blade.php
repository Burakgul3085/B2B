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
                    <div class="flex justify-between gap-3"><dt class="text-muted">Kargo ücreti</dt><dd class="font-semibold tabular-nums">{{ $cargoFee && bccomp($cargoFee, '0', 2) > 0 ? Money::format($cargoFee) : 'Ücretsiz' }}</dd></div>
                    @if ($info['free_threshold'])<div class="flex justify-between gap-3"><dt class="text-muted">Ücretsiz kargo</dt><dd class="font-semibold text-puzzle-green tabular-nums">{{ Money::format($info['free_threshold']) }} ve üzeri</dd></div>@endif
                    @if ($info['cutoff'])<div class="flex justify-between gap-3"><dt class="text-muted">Aynı gün kargo</dt><dd class="font-semibold">Hafta içi {{ $info['cutoff'] }} öncesi</dd></div>@endif
                </dl>
            </div>
            @if ($info['local'])
                <div class="rounded-3xl border border-line p-6">
                    <span class="flex size-12 items-center justify-center rounded-2xl bg-puzzle-green-soft text-puzzle-green"><x-icon name="map-pin" class="size-6" /></span>
                    <p class="mt-4 font-display text-lg font-extrabold">Eskişehir mağaza teslimatı</p>
                    <p class="mt-1 text-sm text-muted">Eskişehir'deki adreslere kendi aracımızla.</p>
                    <dl class="mt-4 space-y-2 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-muted">Teslimat ücreti</dt><dd class="font-semibold tabular-nums">{{ $localFee && bccomp($localFee, '0', 2) > 0 ? Money::format($localFee) : 'Ücretsiz' }}</dd></div>
                        @if (\App\Models\Store\StoreSetting::flag('cod_enabled'))<div class="flex justify-between gap-3"><dt class="text-muted">Kapıda ödeme</dt><dd class="font-semibold">Var</dd></div>@endif
                    </dl>
                </div>
            @endif
            <div class="rounded-3xl bg-surface p-6 sm:col-span-2">
                <p class="flex items-center gap-2 font-semibold"><x-icon name="refresh" class="size-5 text-puzzle-blue" /> İade hakkı</p>
                <p class="mt-1 text-sm text-slate-700">Teslim aldığınız ürünü {{ $info['return_days'] }} gün içinde iade edebilirsiniz. Ayrıntılar <a href="{{ route('store.legal', 'iptal-iade') }}" class="font-semibold text-navy-ink underline">İptal ve İade Koşulları</a> sayfasında.</p>
            </div>
        </div>
    @endif
</x-store.help-layout>
