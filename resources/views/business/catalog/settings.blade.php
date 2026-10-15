<x-layouts.panel :area="\App\Domain\Auth\Area::Business" title="KDV ve Birimler">
    <x-page-header title="KDV ve Birimler" subtitle="Ürünlerde kullanılan KDV oranları ve satış birimleri." />

    @error('tax')<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror

    <div class="grid gap-6 lg:grid-cols-2">
        <x-card title="KDV oranları" description="Oranlar güncel mevzuata göre işletmenizce doğrulanmalıdır. Kullanılan bir oran silinmez; pasif yapılır." :padding="false">
            <table class="table-base">
                <thead><tr><th>Oran</th><th>Ürün</th><th>Durum</th><th></th></tr></thead>
                <tbody class="divide-y divide-line">
                    @foreach ($taxRates as $tax)
                        <tr>
                            <td class="font-medium">{{ $tax->name }}</td>
                            <td class="text-muted tabular-nums">{{ $tax->products_count }}</td>
                            <td>@if ($tax->is_active)<x-badge tone="success">Aktif</x-badge>@else<x-badge>Pasif</x-badge>@endif</td>
                            <td class="text-right">
                                <form method="POST" action="{{ route('business.tax-rates.toggle', $tax) }}">@csrf @method('PUT')
                                    <button class="text-sm text-primary hover:underline">{{ $tax->is_active ? 'Pasif yap' : 'Aktif yap' }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <form method="POST" action="{{ route('business.tax-rates.store') }}" class="flex flex-wrap items-end gap-3 border-t border-line p-4">
                @csrf
                <x-field name="rate" label="Yeni oran (%)" type="number" step="0.01" min="0" max="100" required class="w-32" />
                <x-field name="name" label="Görünen ad" placeholder="Ör. KDV %8" class="flex-1" />
                <x-button variant="secondary" icon="plus">Ekle</x-button>
            </form>
        </x-card>

        <x-card title="Birimler" description="Küsuratlı birimlerde (kg, metre) ondalıklı miktar girilebilir." :padding="false">
            <table class="table-base">
                <thead><tr><th>Kod</th><th>Ad</th><th>Küsurat</th><th>Ürün</th></tr></thead>
                <tbody class="divide-y divide-line">
                    @foreach ($units as $unit)
                        <tr>
                            <td class="font-mono text-xs">{{ $unit->code }}</td>
                            <td>
                                <form method="POST" action="{{ route('business.units.update', $unit) }}" class="flex items-center gap-2">@csrf @method('PUT')
                                    <input name="name" value="{{ $unit->name }}" class="field-input max-w-40 py-1" required maxlength="40" aria-label="{{ $unit->code }} adı">
                                    <button class="text-sm text-primary hover:underline">Kaydet</button>
                                </form>
                            </td>
                            <td>{{ $unit->allows_fraction ? 'Evet' : 'Hayır' }}</td>
                            <td class="text-muted tabular-nums">{{ $unit->products_count }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <form method="POST" action="{{ route('business.units.store') }}" class="flex flex-wrap items-end gap-3 border-t border-line p-4">
                @csrf
                <x-field name="code" label="Kod" required class="w-28" maxlength="10" />
                <x-field name="name" label="Ad" required class="flex-1" />
                <label class="flex items-center gap-2 pb-2.5 text-sm"><input type="hidden" name="allows_fraction" value="0"><input type="checkbox" name="allows_fraction" value="1" class="size-4 rounded"> Küsurat</label>
                <x-button variant="secondary" icon="plus">Ekle</x-button>
            </form>
        </x-card>
    </div>
</x-layouts.panel>
