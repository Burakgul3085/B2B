@php
    use App\Domain\Support\Money;
    use App\Models\Store\StoreOrder;
    use App\Models\Store\StoreReturn;
    use Illuminate\Support\Carbon;

    $s = $summary;
    $p = $previous;
    $max = max(1, ...array_map(fn ($d) => (float) $d['gross'], $daily));
    $tz = 'Europe/Istanbul';
    $presets = [
        'Bu ay' => [now($tz)->startOfMonth()->toDateString(), now($tz)->toDateString()],
        'Son 7 gün' => [now($tz)->subDays(6)->toDateString(), now($tz)->toDateString()],
        'Son 30 gün' => [now($tz)->subDays(29)->toDateString(), now($tz)->toDateString()],
        'Geçen ay' => [now($tz)->subMonthNoOverflow()->startOfMonth()->toDateString(), now($tz)->subMonthNoOverflow()->endOfMonth()->toDateString()],
        'Bu yıl' => [now($tz)->startOfYear()->toDateString(), now($tz)->toDateString()],
    ];
    // Önceki döneme göre değişim (%); önceki dönem sıfırsa gösterilmez.
    $delta = fn ($now, $before) => (float) $before > 0 ? round(((float) $now - (float) $before) / (float) $before * 100) : null;
    $kpis = [
        ['Net satış', Money::format($s['net']), $delta($s['net'], $p['net']), 'Sipariş tutarı '.Money::format($s['gross']).' − iade '.Money::format($s['refunded']), 'bg-puzzle-orange-soft text-puzzle-orange', 'layers'],
        ['Sipariş', number_format($s['orders'], 0, ',', '.'), $delta($s['orders'], $p['orders']), number_format($s['items'], 0, ',', '.').' ürün satıldı', 'bg-puzzle-blue-soft text-puzzle-blue', 'cart'],
        ['Ortalama sepet', Money::format($s['average']), $delta($s['average'], $p['average']), 'Sipariş başına tutar', 'bg-puzzle-green-soft text-puzzle-green', 'package'],
        ['İndirim', Money::format($s['discounts']), $delta($s['discounts'], $p['discounts']), 'Kupon ve kampanyalarla düşülen', 'bg-amber-50 text-amber-700', 'percent'],
    ];
    $period = ['from' => $from, 'to' => $to];
    $groupIcons = ['Satış' => 'cart', 'Ürün' => 'package', 'Stok' => 'warehouse', 'Müşteri' => 'users', 'Pazarlama' => 'percent', 'İade ve kargo' => 'truck'];
@endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" title="Raporlar">
    <x-page-header title="Raporlar" subtitle="Dönem özeti ve ayrıntılı raporlar. Raporları filtreleyin, sıralayın, sütun seçin; Excel veya PDF olarak indirin. Tutarlar KDV dahildir.">
        <x-button :href="route('store_admin.reports.export', ['siparisler', 'xlsx', 'status' => 'all'] + $period)" variant="secondary" icon="download">Siparişleri Excel'e aktar</x-button>
    </x-page-header>

    {{-- Dönem seçimi --}}
    <form method="GET" class="mb-6 flex flex-wrap items-end gap-3 rounded-xl border border-line bg-white p-4">
        <x-field name="from" label="Başlangıç" type="date" :value="$from" />
        <x-field name="to" label="Bitiş" type="date" :value="$to" />
        <x-button variant="secondary">Göster</x-button>
        <div class="flex flex-wrap gap-2 text-sm">
            @foreach ($presets as $label => [$pf, $pt])
                <a href="{{ route('store_admin.reports.index', ['from' => $pf, 'to' => $pt]) }}" @class(['rounded-full px-3 py-1.5 ring-1 transition', 'bg-primary text-white ring-primary' => $pf === $from && $pt === $to, 'bg-white ring-line hover:ring-primary' => ! ($pf === $from && $pt === $to)])>{{ $label }}</a>
            @endforeach
        </div>
        <p class="w-full text-xs text-muted">Karşılaştırma: önceki dönem {{ Carbon::parse($prevFrom)->format('d.m.Y') }} – {{ Carbon::parse($prevTo)->format('d.m.Y') }}. Satış sayılan siparişler: ödenmiş veya kapıda ödemeli oluşturulmuş, iptal edilmemiş siparişler.</p>
    </form>

    {{-- Özet kartları --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($kpis as [$label, $value, $change, $hint, $tone, $icon])
            <div class="rounded-xl border border-line bg-white p-5">
                <div class="flex items-start justify-between gap-3">
                    <p class="text-sm text-muted">{{ $label }}</p>
                    <span class="flex size-9 items-center justify-center rounded-lg {{ $tone }}"><x-icon :name="$icon" class="size-4" /></span>
                </div>
                <p class="mt-1 text-2xl font-bold tabular-nums">{{ $value }}</p>
                <p class="mt-1 flex flex-wrap items-center gap-x-2 text-xs text-muted">
                    @if ($change !== null)
                        <span @class(['rounded px-1.5 py-0.5 font-semibold tabular-nums', 'bg-success-soft text-green-700' => $change >= 0, 'bg-danger-soft text-red-700' => $change < 0])>{{ $change >= 0 ? '▲' : '▼' }} %{{ abs($change) }}</span>
                    @endif
                    <span>{{ $hint }}</span>
                </p>
            </div>
        @endforeach
    </div>

    <div class="mt-4 grid gap-4 sm:grid-cols-3">
        @foreach ([
            ['Teslimat ve hizmet bedeli', Money::format($s['fees'])],
            ['Dönemde yapılan para iadesi', Money::format($s['refunds_in_period'])],
            ['İptal / teslim alınmadı', number_format($s['cancelled'], 0, ',', '.').' sipariş'],
        ] as [$label, $value])
            <div class="flex items-center justify-between rounded-xl border border-line bg-white px-5 py-3 text-sm">
                <span class="text-muted">{{ $label }}</span><span class="font-semibold tabular-nums">{{ $value }}</span>
            </div>
        @endforeach
    </div>

    {{-- Günlük satış grafiği --}}
    <x-card title="Günlük satış" class="mt-6" :padding="false">
        <x-slot:actions><a href="{{ route('store_admin.reports.show', ['donemsel-satis'] + $period) }}" class="text-sm font-medium text-primary hover:underline">Dönemsel satış raporu →</a></x-slot:actions>
        <div class="overflow-x-auto p-5">
            <div class="flex h-44 min-w-[600px] items-end gap-1" role="img" aria-label="Günlük satış grafiği">
                @foreach ($daily as $d)
                    <div class="group relative flex h-full flex-1 flex-col items-center justify-end" title="{{ Carbon::parse($d['day'])->format('d.m.Y') }}: {{ $d['orders'] }} sipariş, {{ Money::format($d['gross']) }}">
                        <div class="w-full rounded-t bg-primary/75 transition group-hover:bg-puzzle-orange" style="height: {{ max((float) $d['gross'] > 0 ? 2 : 0, round((float) $d['gross'] / $max * 100)) }}%"></div>
                    </div>
                @endforeach
            </div>
            <div class="mt-1 flex min-w-[600px] justify-between text-xs text-muted">
                <span>{{ Carbon::parse($from)->format('d.m.Y') }}</span><span>En yüksek gün: {{ Money::format($max) }}</span><span>{{ Carbon::parse($to)->format('d.m.Y') }}</span>
            </div>
        </div>
    </x-card>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <x-card title="En çok satan ürünler" :padding="false">
            <x-slot:actions><a href="{{ route('store_admin.reports.show', ['urun-satislari'] + $period) }}" class="text-sm font-medium text-primary hover:underline">Tümü →</a></x-slot:actions>
            @if ($products === [])
                <p class="p-5 text-sm text-muted">Bu dönemde satış yok.</p>
            @else
                <table class="table-base">
                    <thead><tr><th>Ürün</th><th class="text-right">Adet</th><th class="text-right">Net ciro</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($products as $i => $product)
                            <tr><td><span class="mr-2 inline-flex size-6 items-center justify-center rounded-md bg-surface text-xs font-semibold text-muted">{{ $i + 1 }}</span>{{ $product->name }}<p class="ml-8 text-xs text-muted">@if ($product->option_label){{ $product->option_label }} · @endif{{ $product->sku }}</p></td>
                                <td class="text-right tabular-nums">{{ (int) $product->quantity }}</td><td class="text-right tabular-nums">{{ Money::format($product->revenue) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-card>

        <div class="space-y-6">
            <x-card title="Ödeme ve teslimat" :padding="false">
                <x-slot:actions><a href="{{ route('store_admin.reports.show', ['odeme-teslimat'] + $period) }}" class="text-sm font-medium text-primary hover:underline">Ayrıntı →</a></x-slot:actions>
                @if ($methods === [])
                    <p class="p-5 text-sm text-muted">Bu dönemde satış yok.</p>
                @else
                    <table class="table-base">
                        <thead><tr><th>Ödeme</th><th>Teslimat</th><th class="text-right">Sipariş</th><th class="text-right">Tutar</th></tr></thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($methods as $m)
                                <tr><td>{{ StoreOrder::PAYMENT_METHODS[$m->payment_method] ?? $m->payment_method }}</td><td>{{ StoreOrder::DELIVERY_METHODS[$m->delivery_method] ?? $m->delivery_method }}</td>
                                    <td class="text-right tabular-nums">{{ $m->orders }}</td><td class="text-right tabular-nums">{{ Money::format($m->gross) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-card>

            <x-card title="Kampanya ve kuponlar" :padding="false">
                <x-slot:actions><a href="{{ route('store_admin.reports.show', ['kampanya-kuponlar'] + $period) }}" class="text-sm font-medium text-primary hover:underline">Ayrıntı →</a></x-slot:actions>
                @if ($coupons === [])
                    <p class="p-5 text-sm text-muted">Bu dönemde indirimli sipariş yok.</p>
                @else
                    <table class="table-base">
                        <thead><tr><th>İndirim</th><th class="text-right">Sipariş</th><th class="text-right">İndirim</th><th class="text-right">Sipariş tutarı</th></tr></thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($coupons as $c)
                                <tr><td>{{ $c->discount_name }}</td><td class="text-right tabular-nums">{{ $c->orders }}</td>
                                    <td class="text-right tabular-nums">{{ Money::format($c->amount) }}</td><td class="text-right tabular-nums">{{ Money::format($c->gross) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-card>

            <x-card title="İade nedenleri">
                <x-slot:actions><a href="{{ route('store_admin.reports.show', ['iadeler'] + $period) }}" class="text-sm font-medium text-primary hover:underline">Ayrıntı →</a></x-slot:actions>
                @forelse ($reasons as $reason => $count)
                    <div class="flex justify-between py-1 text-sm"><span>{{ StoreReturn::REASONS[$reason] ?? $reason }}</span><span class="tabular-nums">{{ $count }}</span></div>
                @empty
                    <p class="text-sm text-muted">Bu dönemde iade talebi yok.</p>
                @endforelse
            </x-card>
        </div>
    </div>

    {{-- Rapor kataloğu --}}
    <div class="mt-10">
        <h2 class="text-lg font-semibold">Ayrıntılı raporlar</h2>
        <p class="mt-1 text-sm text-muted">Her raporda filtre, sıralama ve sütun seçimi yapabilir; Excel veya PDF olarak indirebilir, sık kullandığınız filtreleri adıyla kaydedebilirsiniz.</p>

        @if ($saved->isNotEmpty())
            <x-card title="Kayıtlı raporlarım" class="mt-4" :padding="false">
                <ul class="divide-y divide-line">
                    @foreach ($saved as $item)
                        <li class="flex items-center gap-3 px-5 py-3">
                            <x-icon name="filter" class="size-4 shrink-0 text-primary" />
                            <a href="{{ route('store_admin.reports.show', [$item->report_key] + $item->filters) }}" class="flex-1 text-sm">
                                <span class="font-medium text-ink hover:text-primary">{{ $item->name }}</span>
                                <span class="block text-xs text-muted">{{ $titles[$item->report_key] ?? $item->report_key }}</span>
                            </a>
                            <form method="POST" action="{{ route('store_admin.reports.saved.destroy', $item->id) }}" data-confirm="&quot;{{ $item->name }}&quot; silinsin mi?">@csrf @method('DELETE')
                                <button class="text-sm text-muted hover:text-danger">Sil</button></form>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif

        <div class="mt-4 grid gap-6 lg:grid-cols-2 2xl:grid-cols-3">
            @foreach ($groups as $group => $reports)
                <x-card :padding="false">
                    <div class="flex items-center gap-2 border-b border-line px-5 py-3.5">
                        <x-icon :name="$groupIcons[$group] ?? 'file'" class="size-4 text-muted" />
                        <h3 class="font-semibold">{{ $group }}</h3>
                    </div>
                    <ul class="divide-y divide-line">
                        @foreach ($reports as $report)
                            <li>
                                <a href="{{ route('store_admin.reports.show', $report->key()) }}" class="group flex items-center gap-3 px-5 py-3.5 hover:bg-slate-50">
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"><x-icon :name="$report->icon()" class="size-4" /></span>
                                    <span class="flex-1">
                                        <span class="block text-sm font-medium group-hover:text-primary">{{ $report->title() }}</span>
                                        <span class="block text-xs text-muted">{{ $report->description() }}</span>
                                    </span>
                                    <x-icon name="arrow-right" class="size-4 text-muted transition group-hover:translate-x-0.5" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endforeach
        </div>
    </div>
</x-layouts.panel>
