@php use App\Domain\Inventory\Quantity; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Business" title="Stok Durumu">
    <x-page-header title="Stok Durumu" subtitle="Depo bazında güncel stok. Sepet ve siparişler stok ayırmaz; stok yalnızca sipariş onayında düşer.">
        @if ($warehouses->count() > 1)<x-button variant="secondary" icon="arrows" :href="route('business.stock.transfer')">Transfer</x-button>@endif
        <x-button icon="plus" :href="route('business.stock.create')">Stok hareketi</x-button>
    </x-page-header>

    <x-card :padding="false">
        <form method="GET" class="grid gap-3 border-b border-line p-4 sm:grid-cols-2 lg:grid-cols-5 lg:items-end">
            <x-field name="q" label="Ara" :value="$filters['q'] ?? ''" placeholder="Ad, kod veya barkod" class="lg:col-span-2" />
            <x-field name="warehouse" label="Depo" :options="$warehouses->pluck('name', 'id')->all()" :value="$warehouseId" />
            <x-field name="category" label="Kategori" :options="['' => 'Tümü'] + $categories" :value="$filters['category'] ?? ''" />
            <div class="flex gap-2">
                <x-field name="level" label="Seviye" :options="['' => 'Tümü', 'in' => 'Stokta', 'critical' => 'Kritik', 'out' => 'Stokta yok']" :value="$filters['level'] ?? ''" class="flex-1" />
                <x-button variant="secondary" class="self-end" aria-label="Filtrele"><x-icon name="search" class="size-4" /></x-button>
            </div>
        </form>

        @if ($products->isEmpty())
            <x-empty-state icon="layers" title="Gösterilecek ürün yok" text="Filtreleri değiştirin veya önce ürün ekleyin." />
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>Ürün</th><th class="hidden sm:table-cell">Marka</th><th class="text-right">Stok</th><th class="hidden text-right sm:table-cell">Kritik seviye</th><th>Durum</th><th></th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($products as $product)
                            @php
                                $qty = (string) $product->warehouse_qty;
                                $state = bccomp($qty, '0', 3) <= 0 ? 'out' : (bccomp($qty, (string) $product->critical_stock_level, 3) <= 0 ? 'critical' : 'ok');
                            @endphp
                            <tr>
                                <td><a href="{{ route('business.products.show', $product) }}" class="font-medium hover:text-primary">{{ $product->name }}</a><p class="text-xs text-muted">{{ $product->sku }}</p></td>
                                <td class="hidden text-muted sm:table-cell">{{ $product->brand?->name ?? '—' }}</td>
                                <td class="text-right font-medium tabular-nums">{{ Quantity::format($qty) }} <span class="text-xs font-normal text-muted">{{ $product->unit->name }}</span></td>
                                <td class="hidden text-right text-muted tabular-nums sm:table-cell">{{ Quantity::format($product->critical_stock_level) }}</td>
                                <td>
                                    @if ($state === 'out')<x-badge tone="danger">Stokta yok</x-badge>
                                    @elseif ($state === 'critical')<x-badge tone="warning">Kritik</x-badge>
                                    @else<x-badge tone="success">Yeterli</x-badge>@endif
                                </td>
                                <td class="text-right"><a href="{{ route('business.stock.create', ['product' => $product->id]) }}" class="text-sm font-medium text-primary hover:underline">Hareket</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($products->hasPages())<div class="border-t border-line p-4">{{ $products->links() }}</div>@endif
        @endif
    </x-card>
</x-layouts.panel>
