<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" title="Kargo Firmaları">
    <x-page-header title="Kargo Firmaları" subtitle="Kargoyla gönderilen siparişlerde firma seçilir ve takip numarası girilir. Takip bağlantısı müşterinin sipariş sayfasında ve e-postasında gösterilir." />

    @if ($errors->any())
        <ul class="mb-5 list-inside list-disc rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">
            @foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach
        </ul>
    @endif

    <div class="grid gap-6 xl:grid-cols-3">
        <x-card title="Firmalar" :padding="false" class="xl:col-span-2">
            @if ($carriers->isEmpty())
                <x-empty-state icon="package" title="Kargo firması yok" text="Sağdaki formdan firma ekleyin." />
            @else
                <ul class="divide-y divide-line">
                    @foreach ($carriers as $carrier)
                        <li class="p-5">
                            {{-- Satırlar ayrı formlar; alan kimlikleri firmaya göre benzersiz --}}
                            <form method="POST" action="{{ route('store_admin.carriers.update', $carrier) }}" class="grid gap-3 sm:grid-cols-6 sm:items-end">
                                @csrf @method('PUT')
                                <label class="text-sm font-medium sm:col-span-2">Firma
                                    <input name="name" required maxlength="60" value="{{ $carrier->name }}" class="field-input mt-1.5"></label>
                                <label class="text-sm font-medium sm:col-span-3">Takip bağlantısı
                                    <input name="tracking_url" maxlength="300" value="{{ $carrier->tracking_url }}" placeholder="https://…{takip}" class="field-input mt-1.5"></label>
                                <label class="text-sm font-medium">Sıra
                                    <input name="sort_order" type="number" min="0" max="999" value="{{ $carrier->sort_order }}" class="field-input mt-1.5"></label>
                                <div class="flex flex-wrap items-center justify-between gap-3 sm:col-span-6">
                                    <label class="flex items-center gap-2 text-sm"><input type="hidden" name="is_active" value="0">
                                        <input type="checkbox" name="is_active" value="1" @checked($carrier->is_active) class="size-4 rounded border-line text-primary"> Gönderide seçilebilir</label>
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

        <x-card title="Firma ekle">
            <form method="POST" action="{{ route('store_admin.carriers.store') }}" class="space-y-4">
                @csrf
                <x-field name="name" label="Firma" required maxlength="60" />
                <x-field name="tracking_url" label="Takip bağlantısı" maxlength="300" placeholder="https://…{takip}"
                    hint="Firmanın gönderi sorgulama adresi; takip numarasının geleceği yere {takip} yazın. Boş bırakılırsa yalnızca takip numarası gösterilir." />
                <x-field name="sort_order" label="Sıra" type="number" min="0" max="999" value="0" class="max-w-32" />
                <x-button icon="plus">Ekle</x-button>
            </form>
        </x-card>
    </div>
</x-layouts.panel>
