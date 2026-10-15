@php use App\Domain\Inventory\Quantity; use App\Domain\Support\Money; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Business" title="Dashboard">
    <x-page-header title="Dashboard" :subtitle="'Hoş geldiniz, '.auth('business')->user()->name.'.'" />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-kpi label="Aktif ürün" :value="number_format($kpis['active_products'], 0, ',', '.')" :href="route('business.products.index', ['status' => 'active'])" hint="Ürünler" />
        <x-kpi label="Sevk deposunda stokta olmayan" :value="number_format($kpis['out_of_stock'], 0, ',', '.')" :href="route('business.stock.index', ['level' => 'out'])" hint="Listeyi aç" />
        <x-kpi label="Stok değeri" :value="Money::format($kpis['stock_value'])"
            :hint="'Alış fiyatı × miktar'.($kpis['unpriced'] ? ' · '.$kpis['unpriced'].' ürünün alış fiyatı yok' : '')" />
        <x-kpi label="Aktif bayi" :value="$kpis['active_dealers']" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <x-card title="Kritik stoktaki ürünler" description="Varsayılan sevk deposunda kritik seviyede veya altında." class="lg:col-span-2" :padding="false">
            @if ($critical->isEmpty())
                <x-empty-state icon="check-circle" title="Kritik stokta ürün yok" class="py-8" />
            @else
                <table class="table-base">
                    <thead><tr><th>Ürün</th><th class="text-right">Stok</th><th class="text-right">Kritik seviye</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($critical as $product)
                            <tr>
                                <td><a href="{{ route('business.products.show', $product) }}" class="font-medium hover:text-primary">{{ $product->name }}</a><p class="text-xs text-muted">{{ $product->sku }}</p></td>
                                <td class="text-right font-medium text-warning tabular-nums">{{ Quantity::format($product->sales_qty) }} {{ $product->unit->name }}</td>
                                <td class="text-right text-muted tabular-nums">{{ Quantity::format($product->critical_stock_level) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="border-t border-line p-3 text-right"><x-button variant="ghost" size="sm" :href="route('business.stock.index', ['level' => 'critical'])">Tümünü gör</x-button></div>
            @endif
        </x-card>

        @if (collect($setup)->contains('done', false))
            <x-card title="Kurulum adımları" :padding="false">
                <ul class="divide-y divide-line">
                    @foreach ($setup as $step)
                        <li>
                            <a href="{{ route($step['route']) }}" class="flex items-center gap-3 px-5 py-3.5 text-sm hover:bg-slate-50">
                                <x-icon :name="$step['done'] ? 'check-circle' : 'circle'" @class(['size-5', 'text-success' => $step['done'], 'text-slate-300' => ! $step['done']]) />
                                <span @class(['flex-1', 'text-muted line-through' => $step['done']])>{{ $step['label'] }}</span>
                                <x-icon name="arrow-right" class="size-4 text-muted" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @else
            <x-card title="Toplam stok">
                <p class="text-3xl font-semibold tabular-nums">{{ Quantity::format($kpis['stock_qty']) }}</p>
                <p class="mt-1 text-sm text-muted">Tüm depolardaki toplam birim. Sipariş ve cari göstergeleri ilgili modüller açıldığında burada yer alacak.</p>
            </x-card>
        @endif
    </div>
</x-layouts.panel>
