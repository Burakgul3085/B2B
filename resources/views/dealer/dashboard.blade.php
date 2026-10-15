@php use App\Domain\Inventory\Quantity; use App\Domain\Support\Money; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Dealer" title="Ana Sayfa">
    <x-page-header title="Hoş geldiniz" :subtitle="$currentBusiness->display_name.' bayi paneli'">
        <x-button icon="package" :href="route('dealer.catalog')">Sipariş ver</x-button>
    </x-page-header>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-kpi label="Devam eden sipariş" :value="$kpis['open_orders']" :href="route('dealer.orders.index', ['status' => 'open'])" hint="Siparişlerim" />
        <x-kpi label="Bu ay onaylanan sipariş" :value="Money::format($kpis['month_amount'])" hint="KDV dahil" />
        <x-kpi label="Cari bakiye" :value="Money::format($kpis['balance'])" :href="route('dealer.account.statement')" :hint="bccomp($kpis['balance'], '0', 2) > 0 ? 'Ekstreyi gör' : 'Borcunuz yok'" />
        <x-kpi label="Açık ürün talebi" :value="$kpis['open_requests']" :href="$currentBusiness->moduleEnabled('product_requests') ? route('dealer.requests.index') : null" :hint="$currentBusiness->moduleEnabled('product_requests') ? 'Taleplerim' : null" />
    </div>

    @if ($arrived->isNotEmpty())
        <div class="mt-6 rounded-xl border border-green-200 bg-success-soft p-4 text-sm text-green-900">
            <p class="font-semibold">Talep ettiğiniz ürünler stoğa girdi</p>
            <ul class="mt-2 space-y-1">
                @foreach ($arrived as $r)<li><a href="{{ route('dealer.catalog.show', $r->product_id) }}" class="font-medium underline">{{ $r->product->name }}</a></li>@endforeach
            </ul>
        </div>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <x-card title="Son siparişler" class="lg:col-span-2" :padding="false">
            @if ($recentOrders->isEmpty())
                <x-empty-state icon="file" title="Henüz siparişiniz yok" text="Katalogdan ürünleri sepete ekleyerek ilk siparişinizi verin." class="py-8">
                    <x-button :href="route('dealer.catalog')">Kataloğa git</x-button>
                </x-empty-state>
            @else
                <table class="table-base">
                    <thead><tr><th>Sipariş</th><th>Tarih</th><th class="text-right">Tutar</th><th>Durum</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($recentOrders as $order)
                            <tr>
                                <td><a href="{{ route('dealer.orders.show', $order) }}" class="font-medium text-primary hover:underline">{{ $order->order_no }}</a></td>
                                <td class="text-muted tabular-nums">{{ $order->created_at->timezone('Europe/Istanbul')->format('d.m.Y') }}</td>
                                <td class="text-right tabular-nums">{{ Money::format($order->grand_total) }}</td>
                                <td><x-badge :tone="$order->statusTone()">{{ $order->statusLabel() }}</x-badge></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-card>

        <div class="space-y-6">
            <x-card title="En çok sipariş verdikleriniz" :padding="false">
                @if ($topProducts->isEmpty())
                    <p class="p-5 text-sm text-muted">Onaylanmış siparişiniz olduğunda burada görünür.</p>
                @else
                    <ul class="divide-y divide-line text-sm">
                        @foreach ($topProducts as $p)
                            <li class="flex justify-between gap-3 px-5 py-3">
                                <a href="{{ route('dealer.catalog.show', $p->product_id) }}" class="truncate hover:text-primary">{{ $p->name }}</a>
                                <span class="shrink-0 tabular-nums text-muted">{{ Quantity::format($p->qty) }} {{ $p->unit_name }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            @if ($currentBusiness->phone || $currentBusiness->email)
                <x-card title="Toptancı iletişim">
                    <dl class="space-y-2 text-sm">
                        @if ($currentBusiness->phone)<div><dt class="text-muted">Telefon</dt><dd class="font-medium">{{ $currentBusiness->phone }}</dd></div>@endif
                        @if ($currentBusiness->email)<div><dt class="text-muted">E-posta</dt><dd class="font-medium">{{ $currentBusiness->email }}</dd></div>@endif
                    </dl>
                </x-card>
            @endif
        </div>
    </div>
</x-layouts.panel>
