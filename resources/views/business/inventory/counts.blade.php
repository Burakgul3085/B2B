@php use App\Models\StockCount; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Business" title="Stok sayımları">
    <x-page-header title="Stok sayımları" subtitle="Sayılan miktarları girin; uygulandığında fark kadar stok hareketi oluşur.">
        <x-slot:breadcrumb><a href="{{ route('business.warehouses.index') }}" class="hover:underline">Depolar ve Sayım</a></x-slot:breadcrumb>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card title="Sayımlar" class="lg:col-span-2" :padding="false">
            @if ($counts->isEmpty())
                <x-empty-state icon="list" title="Henüz sayım yok" text="Yeni bir sayım başlatın, ürünleri kod veya barkodla ekleyin." />
            @else
                <table class="table-base">
                    <thead><tr><th>No</th><th>Depo</th><th>Ürün</th><th>Durum</th><th>Tarih</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($counts as $count)
                            <tr>
                                <td><a href="{{ route('business.stock-counts.show', $count) }}" class="font-medium text-primary hover:underline">#{{ $count->id }}</a></td>
                                <td>{{ $count->warehouse->name }}</td>
                                <td class="tabular-nums">{{ $count->lines_count }}</td>
                                <td><x-badge :tone="['draft' => 'warning', 'applied' => 'success', 'cancelled' => 'neutral'][$count->status]">{{ StockCount::STATUSES[$count->status] }}</x-badge></td>
                                <td class="text-muted tabular-nums">{{ ($count->applied_at ?? $count->created_at)->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if ($counts->hasPages())<div class="border-t border-line p-4">{{ $counts->links() }}</div>@endif
            @endif
        </x-card>

        <x-card title="Yeni sayım">
            <form method="POST" action="{{ route('business.stock-counts.store') }}" class="space-y-4">
                @csrf
                <x-field name="warehouse_id" label="Depo" :options="$warehouses->all()" required />
                <x-field name="note" label="Açıklama" placeholder="Ör. Ekim ayı sayımı" />
                <x-button icon="plus">Sayımı başlat</x-button>
            </form>
        </x-card>
    </div>
</x-layouts.panel>
