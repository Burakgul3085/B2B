<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" title="Stok Durumu">
    <x-page-header title="Stok Durumu" subtitle="Mağaza stoğu B2B deposundan ayrı tutulur. Satılabilir = stok − ödeme aşamasında ayrılan.">
        <x-button variant="secondary" icon="refresh" :href="route('store_admin.stock.movements')">Stok hareketleri</x-button>
        <x-button variant="secondary" icon="upload" :href="route('store_admin.products.import')">Excel ile güncelle</x-button>
    </x-page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-kpi label="Toplam stok (adet)" :value="number_format($summary['units'], 0, ',', '.')" />
        <x-kpi label="Kritik seviyede" :value="$summary['critical']" :href="route('store_admin.stock.index', ['status' => 'critical'])" hint="Listele" />
        <x-kpi label="Stokta yok (satışta)" :value="$summary['out']" :href="route('store_admin.stock.index', ['status' => 'out'])" hint="Listele" />
    </div>

    <x-card :padding="false">
        <form method="GET" class="grid gap-3 border-b border-line p-4 sm:grid-cols-2 lg:grid-cols-5 lg:items-end">
            <x-field name="q" label="Ara" :value="$filters['q'] ?? ''" placeholder="Ürün adı, stok kodu veya barkod" class="lg:col-span-2" />
            <x-field name="category" label="Kategori" :options="['' => 'Tümü'] + $categories" :value="$filters['category'] ?? ''" />
            <x-field name="status" label="Durum" :options="['' => 'Tümü', 'critical' => 'Kritik seviyede', 'out' => 'Stokta yok', 'in' => 'Stokta var']" :value="$filters['status'] ?? ''" />
            <x-button variant="secondary" icon="search">Filtrele</x-button>
        </form>

        @if ($variants->isEmpty())
            <x-empty-state icon="layers" title="Gösterilecek ürün yok" text="Ürün ekledikten sonra stok girişini buradan veya Excel ile yapabilirsiniz." />
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead>
                        <tr>
                            <th>Ürün</th>
                            <th class="text-right">Stok</th>
                            <th class="hidden text-right sm:table-cell">Ayrılan</th>
                            <th class="text-right">Satılabilir</th>
                            <th class="hidden text-right md:table-cell">Kritik seviye</th>
                            <th></th>
                        </tr>
                    </thead>
                    @foreach ($variants as $variant)
                        @php $reserved = $held[$variant->id] ?? 0; $available = max(0, $variant->stock_quantity - $reserved); @endphp
                        <tbody x-data="{ open: false }" class="border-b border-line">
                            <tr>
                                <td>
                                    <a href="{{ route('store_admin.products.edit', $variant->product_id) }}" class="font-medium text-ink hover:text-primary">{{ $variant->product->name }}</a>
                                    <p class="text-xs text-muted">@if ($variant->option_value){{ $variant->product->variant_name }}: {{ $variant->option_value }} · @endif{{ $variant->sku }}@unless ($variant->is_active) · <span class="text-warning">satışta değil</span>@endunless</p>
                                </td>
                                <td class="text-right font-semibold tabular-nums">
                                    <span @class(['text-danger' => $variant->stock_quantity === 0, 'text-warning' => $variant->stock_quantity > 0 && $variant->isCritical()])>{{ number_format($variant->stock_quantity, 0, ',', '.') }}</span>
                                </td>
                                <td class="hidden text-right text-muted tabular-nums sm:table-cell">{{ $reserved ?: '—' }}</td>
                                <td class="text-right tabular-nums">{{ number_format($available, 0, ',', '.') }}</td>
                                <td class="hidden text-right text-muted tabular-nums md:table-cell">{{ $variant->critical_stock ?? '—' }}</td>
                                <td class="text-right whitespace-nowrap">
                                    <button type="button" @click="open = ! open" class="text-sm font-medium text-primary hover:underline" :aria-expanded="open">Stok işlemi</button>
                                    <a href="{{ route('store_admin.stock.movements', ['variant' => $variant->id]) }}" class="ml-3 text-sm text-muted hover:underline">Geçmiş</a>
                                </td>
                            </tr>
                            <tr x-show="open" x-cloak>
                                <td colspan="6" class="bg-slate-50/70">@include('store.admin.stock._actions', ['variant' => $variant])</td>
                            </tr>
                        </tbody>
                    @endforeach
                </table>
            </div>
            @if ($variants->hasPages())<div class="border-t border-line p-4">{{ $variants->links() }}</div>@endif
        @endif
    </x-card>
</x-layouts.panel>
