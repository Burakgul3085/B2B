@php
    use App\Domain\Store\Shipping\CarrierBrands;
    use App\Domain\Support\Money;

    $money = fn ($v) => $v === null ? '' : number_format((float) $v, 2, ',', '');
@endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" title="Kargo Firmaları">
    <x-page-header title="Kargo Firmaları" subtitle="Anlaştığınız firmaların kargo ücretini girin; müşteri ödeme sayfasında firmayı fiyatıyla seçer. Logo ve takip bağlantısı firma adından kendiliğinden gelir." />

    @if ($errors->any())
        <ul class="mb-5 list-inside list-disc rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">
            @foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach
        </ul>
    @endif

    <p class="mb-5 rounded-lg bg-surface px-4 py-3 text-sm text-muted">
        @if ($freeThreshold)
            Sepeti <span class="font-semibold text-ink">{{ Money::format($freeThreshold) }}</span> ve üzeri olan müşteri firma seçmez, kargo ücretsizdir; firmayı siparişi kargoya verirken siz seçersiniz.
        @else
            Ücretsiz kargo sınırı girilmemiş; her siparişte müşteri firmayı seçer.
        @endif
        Sınırı <a href="{{ route('store_admin.payments.edit') }}" class="font-medium text-primary hover:underline">Ödeme ve Teslimat</a> ekranından değiştirebilirsiniz.
    </p>

    <div class="grid gap-6 xl:grid-cols-3">
        <x-card title="Firmalar" :padding="false" class="xl:col-span-2">
            @if ($carriers->isEmpty())
                <x-empty-state icon="package" title="Kargo firması yok" text="Sağdaki formdan firma ekleyin." />
            @else
                <ul class="divide-y divide-line">
                    @foreach ($carriers as $carrier)
                        @php $brand = $carrier->brand(); @endphp
                        <li class="p-5">
                            <div class="mb-4 flex items-center gap-3">
                                <span class="flex h-12 w-36 shrink-0 items-center justify-center rounded-lg bg-[#fff] px-3 ring-1 ring-line">
                                    @if ($brand)
                                        <img src="{{ $brand['logo'] }}" alt="{{ $carrier->name }}" class="max-h-8 max-w-full object-contain">
                                    @else
                                        <span class="flex size-9 items-center justify-center rounded-full bg-surface text-sm font-bold text-muted">{{ mb_strtoupper(mb_substr($carrier->name, 0, 2)) }}</span>
                                    @endif
                                </span>
                                <div class="min-w-0">
                                    <p class="truncate font-semibold">{{ $carrier->name }}</p>
                                    <p class="text-xs text-muted">
                                        @if (! $carrier->is_active) Kapalı
                                        @elseif ($carrier->fee === null) Ücret girilmedi · müşteriye gösterilmez
                                        @else Müşteriye {{ Money::format($carrier->fee) }} olarak sunulur
                                        @endif
                                    </p>
                                </div>
                            </div>
                            {{-- Satırlar ayrı formlar; alan kimlikleri firmaya göre benzersiz --}}
                            <form method="POST" action="{{ route('store_admin.carriers.update', $carrier) }}" class="grid gap-3 sm:grid-cols-6 sm:items-end">
                                @csrf @method('PUT')
                                <label class="text-sm font-medium sm:col-span-2">Firma
                                    <input name="name" required maxlength="60" value="{{ $carrier->name }}" class="field-input mt-1.5"></label>
                                <label class="text-sm font-medium sm:col-span-2">Kargo ücreti (₺)
                                    <input name="fee" maxlength="20" inputmode="decimal" value="{{ $money($carrier->fee) }}" placeholder="ör. 89,90" class="field-input mt-1.5"></label>
                                <label class="text-sm font-medium sm:col-span-2">Sıra
                                    <input name="sort_order" type="number" min="0" max="999" value="{{ $carrier->sort_order }}" class="field-input mt-1.5"></label>
                                <label class="text-sm font-medium sm:col-span-6">Takip bağlantısı
                                    <input name="tracking_url" maxlength="300" value="{{ $carrier->tracking_url }}" placeholder="{{ $brand ? 'Otomatik: '.$brand['tracking_url'] : 'https://…{takip}' }}" class="field-input mt-1.5"></label>
                                <div class="flex flex-wrap items-center justify-between gap-3 sm:col-span-6">
                                    <label class="flex items-center gap-2 text-sm"><input type="hidden" name="is_active" value="0">
                                        <input type="checkbox" name="is_active" value="1" @checked($carrier->is_active) class="size-4 rounded border-line text-primary"> Kullanımda (ödeme sayfasında ve gönderide)</label>
                                    <div class="flex gap-2">
                                        <x-button variant="secondary">Kaydet</x-button>
                                        <x-button variant="ghost" type="submit" form="sil-{{ $carrier->id }}" class="text-danger">Sil</x-button>
                                    </div>
                                </div>
                            </form>
                            <form id="sil-{{ $carrier->id }}" method="POST" action="{{ route('store_admin.carriers.destroy', $carrier) }}" data-confirm="{{ $carrier->name }} silinsin mi? Eski siparişlerdeki firma adı ve takip bağlantısı korunur.">@csrf @method('DELETE')</form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        <div class="space-y-6">
            <x-card title="Firma ekle">
                <form method="POST" action="{{ route('store_admin.carriers.store') }}" class="space-y-4">
                    @csrf
                    <x-field name="name" label="Firma" required maxlength="60" placeholder="ör. Aras Kargo" />
                    <x-field name="fee" label="Kargo ücreti (₺)" maxlength="20" inputmode="decimal" placeholder="ör. 89,90" class="max-w-40"
                        hint="Anlaştığınız fiyat (KDV dahil). Boş bırakılırsa firma müşteriye sunulmaz, yalnızca gönderide seçilir." />
                    <x-field name="tracking_url" label="Takip bağlantısı" maxlength="300" placeholder="https://…{takip}"
                        hint="Aşağıdaki firmalarda boş bırakın, kendiliğinden gelir. Diğerlerinde takip numarasının geleceği yere {takip} yazın." />
                    <x-field name="sort_order" label="Sıra" type="number" min="0" max="999" value="0" class="max-w-32" />
                    <x-button icon="plus">Ekle</x-button>
                </form>
            </x-card>

            <x-card title="Logosu ve takibi hazır firmalar">
                <ul class="grid grid-cols-2 gap-2">
                    @foreach (CarrierBrands::BRANDS as $key => [$label])
                        <li class="flex h-14 items-center justify-center rounded-lg bg-[#fff] px-3 ring-1 ring-line" title="{{ $label }}">
                            <img src="{{ asset("images/carriers/{$key}.png") }}" alt="{{ $label }}" class="max-h-8 max-w-full object-contain" loading="lazy">
                        </li>
                    @endforeach
                </ul>
                <p class="mt-3 text-xs text-muted">Adı bu firmalardan birini içeren firma (ör. "Yurtiçi", "MNG Kargo") tanınır. Aras, Yurtiçi, DHL ve UPS takip sayfası gönderiyi doğrudan açar; MNG, Sürat, PTT ve Kolay Gelsin sayfasında müşteri numarayı yapıştırır.</p>
            </x-card>
        </div>
    </div>
</x-layouts.panel>
