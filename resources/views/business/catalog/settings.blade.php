<x-layouts.panel :area="\App\Domain\Auth\Area::Business" title="KDV Oranları">
    <x-page-header title="KDV Oranları" subtitle="Ürünlerde kullanılan KDV oranları. Satış şekli (tek tek, koli, özel kural) ürün formunda seçilir." />

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

    </div>
</x-layouts.panel>
