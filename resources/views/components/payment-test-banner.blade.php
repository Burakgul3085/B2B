{{--
    Kartla ödeme iyzico TEST (sandbox) ortamındayken yönetim panellerinde sürekli uyarı: test kartıyla alınan "ödemeler"
    gerçek değildir. Mağaza yönetiminde mağaza ayarı, toptancı panelinde işletmenin ödeme ayarı esas alınır.
--}}
@props(['area'])
@php
    use App\Domain\Auth\Area;

    $testMode = match ($area) {
        Area::StoreAdmin => \App\Models\Store\StoreSetting::get('card_enabled') === '1' && \App\Models\Store\StoreSetting::get('iyzico_mode') !== 'live',
        Area::Business => (function () {
            $s = \App\Models\BusinessSetting::where('business_id', auth('business')->user()?->business_id)->first();

            return $s && $s->payments_enabled && $s->card_enabled && $s->iyzico_mode !== 'live';
        })(),
        default => false,
    };
    $settingsUrl = $area === Area::StoreAdmin ? route('store_admin.payments.edit') : ($area === Area::Business ? route('business.payments.edit') : null);
@endphp
@if ($testMode)
    <div role="alert" class="mb-5 flex flex-wrap items-center gap-3 rounded-lg border border-red-300 bg-danger-soft px-4 py-3 text-sm text-red-900">
        <x-icon name="alert" class="size-5 shrink-0" />
        <p class="min-w-0 flex-1"><b>Kartla ödeme TEST modunda.</b> Test kartıyla alınan ödemeler gerçek değildir, hesabınıza para geçmez. Bu siparişleri göndermeyin. Gerçek satışa geçmek için ödeme ayarlarında ortamı <b>Canlı</b> yapın.</p>
        @if ($settingsUrl)<a href="{{ $settingsUrl }}" class="font-semibold underline">Ödeme ayarları</a>@endif
    </div>
@endif
