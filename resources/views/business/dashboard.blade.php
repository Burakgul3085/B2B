@php use App\Domain\Inventory\Quantity; use App\Domain\Support\Money; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Business" title="Dashboard">
    <x-page-header title="Dashboard" :subtitle="'Hoş geldiniz, '.auth('business')->user()->name.'.'" />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-kpi label="Karar bekleyen sipariş" :value="$kpis['pending_orders']" :href="route('business.orders.index')" :hint="'Bugün gelen: '.$kpis['today_orders']" />
        <x-kpi label="Bu ay onaylanan satış" :value="Money::format($kpis['month_sales'])" hint="KDV dahil, iptaller hariç" />
        <x-kpi label="Sevk deposunda stokta olmayan" :value="number_format($kpis['out_of_stock'], 0, ',', '.')" :href="route('business.stock.index', ['level' => 'out'])" hint="Listeyi aç" />
        <x-kpi label="Açık ürün talebi" :value="$kpis['open_requests']" :href="$currentBusiness->moduleEnabled('product_requests') ? route('business.product-requests.index') : null" :hint="$currentBusiness->moduleEnabled('product_requests') ? 'Talepleri gör' : null" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <x-card title="Son siparişler" class="lg:col-span-2" :padding="false">
            @if ($recentOrders->isEmpty())
                <x-empty-state icon="file" title="Henüz sipariş yok" text="Bayileriniz katalogdan sipariş verdiğinde burada görünür." class="py-8" />
            @else
                <table class="table-base">
                    <thead><tr><th>Sipariş</th><th>Bayi</th><th class="text-right">Tutar</th><th>Durum</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($recentOrders as $order)
                            <tr>
                                <td><a href="{{ route('business.orders.show', $order) }}" class="font-medium text-primary hover:underline">{{ $order->order_no }}</a><p class="text-xs text-muted">{{ $order->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</p></td>
                                <td>{{ $order->relationship->dealer->legal_name }}</td>
                                <td class="text-right tabular-nums">{{ Money::format($order->grand_total) }}</td>
                                <td><x-badge :tone="$order->statusTone()">{{ $order->statusLabel() }}</x-badge></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-card>

        <div class="space-y-6">
            @if (collect($setup)->contains('done', false))
                <x-card title="Kurulum adımları" :padding="false">
                    <ul class="divide-y divide-line">
                        @foreach ($setup as $step)
                            <li>
                                <a href="{{ route($step['route']) }}" class="flex items-center gap-3 px-5 py-3.5 text-sm hover:bg-slate-50">
                                    <x-icon :name="$step['done'] ? 'check-circle' : 'circle'" @class(['size-5', 'text-success' => $step['done'], 'text-slate-300' => ! $step['done']]) />
                                    <span @class(['flex-1', 'text-muted line-through' => $step['done']])>{{ $step['label'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif

            <x-card title="Finans">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-muted">Cari alacak</dt><dd class="font-medium tabular-nums">{{ Money::format($finance['receivable']) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Vadesi geçmiş</dt><dd @class(['font-medium tabular-nums', 'text-danger' => bccomp($finance['overdue'], '0', 2) > 0])>{{ Money::format($finance['overdue']) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Bu ay tahsilat</dt><dd class="font-medium tabular-nums">{{ Money::format($finance['month_collections']) }}</dd></div>
                </dl>
                <a href="{{ route('business.accounts.index') }}" class="mt-3 inline-block text-sm font-medium text-primary hover:underline">Cari hesaplar</a>
            </x-card>

            <x-card title="Özet">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-muted">Aktif bayi</dt><dd class="font-medium tabular-nums">{{ $kpis['active_dealers'] }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Yeni başvuru</dt><dd class="font-medium tabular-nums"><a href="{{ route('business.applications.index') }}" class="text-primary hover:underline">{{ $kpis['new_applications'] }}</a></dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Aktif ürün</dt><dd class="font-medium tabular-nums">{{ number_format($kpis['active_products'], 0, ',', '.') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Toplam stok</dt><dd class="font-medium tabular-nums">{{ Quantity::format($kpis['stock_qty']) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Stok değeri <span class="text-xs">(alış × miktar)</span></dt><dd class="font-medium tabular-nums">{{ Money::format($kpis['stock_value']) }}</dd></div>
                </dl>
                @if ($kpis['unpriced'])<p class="mt-2 text-xs text-warning">{{ $kpis['unpriced'] }} stoklu ürünün alış fiyatı yok; stok değerine dahil edilmedi.</p>@endif
            </x-card>
        </div>
    </div>

    <x-card title="Kritik stoktaki ürünler" description="Varsayılan sevk deposunda kritik seviyede veya altında." class="mt-6" :padding="false">
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
        @endif
    </x-card>
</x-layouts.panel>
