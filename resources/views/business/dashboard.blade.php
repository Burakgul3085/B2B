@php use App\Domain\Inventory\Quantity; use App\Domain\Support\Money; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Business" title="Dashboard">
    <x-page-header title="Dashboard" :subtitle="'Hoş geldiniz, '.auth('business')->user()->name.'.'" />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-kpi label="Karar bekleyen sipariş" :value="$kpis['pending_orders']" :href="route('business.orders.index')" :hint="'Bugün gelen: '.$kpis['today_orders']" />
        <x-kpi label="Bu ay onaylanan satış" :value="Money::format($kpis['month_sales'])" hint="KDV dahil, iptaller hariç" />
        <x-kpi label="Sevk deposunda stokta olmayan" :value="number_format($kpis['out_of_stock'], 0, ',', '.')" :href="route('business.stock.index', ['level' => 'out'])" hint="Listeyi aç" />
        <x-kpi label="Açık ürün talebi" :value="$kpis['open_requests']" :href="$currentBusiness->moduleEnabled('product_requests') ? route('business.product-requests.index') : null" :hint="$currentBusiness->moduleEnabled('product_requests') ? 'Talepleri gör' : null" />
    </div>

    @php
        $a = $analytics; $af = $a['filters']; $ak = $a['kpis'];
        // Önceki döneme göre değişim; önceki dönem sıfırsa yüzde gösterilmez.
        $delta = function ($cur, $prev) {
            if (bccomp((string) $prev, '0', 2) === 0) {
                return bccomp((string) $cur, '0', 2) > 0 ? 'Önceki dönemde yok' : 'Önceki dönemle aynı';
            }
            $pct = round(((float) $cur - (float) $prev) / (float) $prev * 100, 1);

            return ($pct > 0 ? '+' : '').number_format($pct, 1, ',', '.').'% önceki döneme göre';
        };
        $series = $a['series'];
        $rangeText = $a['range']['from']->format('d.m.Y').' – '.$a['range']['to']->format('d.m.Y');
    @endphp

    <section class="mt-8" aria-labelledby="analiz">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 id="analiz" class="text-lg font-semibold text-ink">Satış analizi</h2>
                <p class="text-sm text-muted">{{ $rangeText }} · önceki dönem: {{ $a['range']['prevFrom']->format('d.m.Y') }} – {{ $a['range']['prevTo']->format('d.m.Y') }}</p>
            </div>
            <form method="GET" action="{{ route('business.dashboard') }}" class="flex flex-wrap items-end gap-2" x-data="{ period: @alpine($af['period']) }">
                <div>
                    <label for="d_period" class="mb-1 block text-xs font-medium text-muted">Dönem</label>
                    <select id="d_period" name="period" x-model="period" class="field-input py-1.5 text-sm">
                        @foreach (\App\Domain\Reporting\DashboardMetrics::PERIODS as $k => $label)<option value="{{ $k }}" @selected($af['period'] === $k)>{{ $label }}</option>@endforeach
                    </select>
                </div>
                <template x-if="period === 'custom'">
                    <div class="flex gap-2">
                        <div><label for="d_from" class="mb-1 block text-xs font-medium text-muted">Başlangıç</label><input id="d_from" type="date" name="from" value="{{ $af['from'] ?? $a['range']['from']->toDateString() }}" class="field-input py-1.5 text-sm"></div>
                        <div><label for="d_to" class="mb-1 block text-xs font-medium text-muted">Bitiş</label><input id="d_to" type="date" name="to" value="{{ $af['to'] ?? $a['range']['to']->toDateString() }}" class="field-input py-1.5 text-sm"></div>
                    </div>
                </template>
                <div>
                    <label for="d_dealer" class="mb-1 block text-xs font-medium text-muted">Bayi</label>
                    <select id="d_dealer" name="dealer" class="field-input max-w-48 py-1.5 text-sm">
                        @foreach ($a['dealerOptions'] as $k => $label)<option value="{{ $k }}" @selected((string) ($af['dealer'] ?? '') === (string) $k)>{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label for="d_category" class="mb-1 block text-xs font-medium text-muted">Kategori</label>
                    <select id="d_category" name="category" class="field-input max-w-48 py-1.5 text-sm">
                        @foreach ($a['categoryOptions'] as $k => $label)<option value="{{ $k }}" @selected((string) ($af['category'] ?? '') === (string) $k)>{{ $label }}</option>@endforeach
                    </select>
                </div>
                <x-button type="submit" variant="secondary" size="sm">Uygula</x-button>
            </form>
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-kpi label="Dönem satışı" :value="Money::format($ak['sales'])" :hint="$delta($ak['sales'], $ak['sales_prev'])" />
            <x-kpi label="Onaylanan sipariş" :value="number_format($ak['orders'], 0, ',', '.')" :hint="$delta($ak['orders'], $ak['orders_prev'])" />
            <x-kpi label="Ortalama sipariş tutarı" :value="Money::format($ak['average'])" hint="Satış ÷ onaylanan sipariş" />
            <x-kpi label="Dönem tahsilatı" :value="Money::format($ak['collections'])" hint="İptal edilenler hariç" />
        </div>
        @if (! empty($af['category']))<p class="mt-2 text-xs text-muted">Kategori filtresinde sipariş tutarları, o kategoriden kalem içeren siparişlerin tamamıdır; kategori dağılımı yalnızca o kategorinin kalemlerini sayar.</p>@endif

        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <x-chart class="lg:col-span-2" title="Satış eğrisi" :description="$series['monthly'] ? 'Aylık, KDV dahil' : 'Günlük, KDV dahil'"
                :empty="array_sum($series['current']) + array_sum($series['previous']) == 0"
                :config="['type' => 'line', 'unit' => 'money', 'labels' => $series['labels'], 'names' => ['Bu dönem', 'Önceki dönem'], 'series' => [$series['current'], $series['previous']]]"
                :headers="['Tarih', 'Bu dönem', 'Önceki dönem']"
                :rows="collect($series['labels'])->map(fn ($l, $i) => [$l, Money::format($series['current'][$i]), Money::format($series['previous'][$i])])->all()" />

            <x-chart title="Sipariş durumları" description="Dönemde oluşturulan siparişler"
                :empty="array_sum($a['status']) === 0"
                :config="['type' => 'doughnut', 'labels' => array_keys($a['status']), 'values' => array_values($a['status'])]"
                :headers="['Durum', 'Sipariş']"
                :rows="collect($a['status'])->map(fn ($v, $k) => [$k, $v])->values()->all()" />
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <x-chart title="Kategoriye göre satış" description="Ana kategori, KDV hariç net kalem tutarı (ilk 8)"
                :empty="empty($a['categories'])"
                :config="['type' => 'hbar', 'unit' => 'money', 'labels' => array_keys($a['categories']), 'values' => array_values($a['categories'])]"
                :headers="['Kategori', 'Net satış']"
                :rows="collect($a['categories'])->map(fn ($v, $k) => [$k, Money::format($v)])->values()->all()" />

            <x-chart title="En çok satış yapılan bayiler" description="KDV dahil sipariş tutarı (ilk 8)"
                :empty="empty($a['dealers'])"
                :config="['type' => 'hbar', 'unit' => 'money', 'labels' => array_keys($a['dealers']), 'values' => array_values($a['dealers'])]"
                :headers="['Bayi', 'Satış']"
                :rows="collect($a['dealers'])->map(fn ($v, $k) => [$k, Money::format($v)])->values()->all()" />
        </div>
    </section>

    <div class="mt-8 grid gap-6 lg:grid-cols-3">
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
