<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" title="Seçkiler">
    <x-page-header title="Seçkiler" subtitle="Temaya göre elle seçtiğiniz ürün listeleri (ör. Okula dönüş, Ofis masası, Hediye). Ana sayfada en fazla 4 seçki kutu olarak görünür.">
        <x-button :href="route('store_admin.collections.create')" icon="plus">Yeni seçki</x-button>
    </x-page-header>

    <x-card :padding="false">
        @if ($collections->isEmpty())
            <x-empty-state icon="star" title="Henüz seçki yok" text="İlk seçkiyi oluşturun; ürünleri stok kodlarıyla (SKU) seçersiniz." />
        @else
            <table class="table-base">
                <thead><tr><th>Seçki</th><th class="text-right">Ürün</th><th>Durum</th><th></th></tr></thead>
                <tbody class="divide-y divide-line">
                    @foreach ($collections as $collection)
                        @php $c = \App\Domain\Store\Storefront\Palette::classes($collection->color ?: 'orange'); @endphp
                        <tr>
                            <td><span class="flex items-center gap-3"><span class="size-3 rounded-full {{ $c['solid'] }}"></span>
                                <span><a href="{{ route('store_admin.collections.edit', $collection) }}" class="font-semibold hover:text-primary">{{ $collection->title }}</a>
                                    @if ($collection->subtitle)<span class="block text-xs text-muted">{{ $collection->subtitle }}</span>@endif</span></span></td>
                            <td class="text-right tabular-nums">{{ $collection->products_count }}</td>
                            <td><x-badge :tone="$collection->is_active ? 'success' : 'warning'">{{ $collection->is_active ? 'Yayında' : 'Kapalı' }}</x-badge></td>
                            <td class="text-right"><a href="{{ route('store.collection', $collection->slug) }}" target="_blank" class="text-sm text-primary hover:underline">Mağazada gör</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-card>
</x-layouts.panel>
