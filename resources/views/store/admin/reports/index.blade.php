@php
    use App\Domain\Support\Money;
    use App\Models\Store\StoreOrder;
    use App\Models\Store\StoreReturn;

    $s = $summary;
    $max = max(1, ...array_map(fn ($d) => (float) $d['gross'], $daily));
    $presets = [
        'Bu ay' => [now('Europe/Istanbul')->startOfMonth()->toDateString(), now('Europe/Istanbul')->toDateString()],
        'Son 7 gün' => [now('Europe/Istanbul')->subDays(6)->toDateString(), now('Europe/Istanbul')->toDateString()],
        'Son 30 gün' => [now('Europe/Istanbul')->subDays(29)->toDateString(), now('Europe/Istanbul')->toDateString()],
        'Geçen ay' => [now('Europe/Istanbul')->subMonthNoOverflow()->startOfMonth()->toDateString(), now('Europe/Istanbul')->subMonthNoOverflow()->endOfMonth()->toDateString()],
    ];
@endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" title="Raporlar">
    <x-page-header title="Raporlar" subtitle="Satış sayılan siparişler: ödenmiş veya kapıda ödemeli oluşturulmuş, iptal edilmemiş siparişler. Tutarlar KDV dahildir.">
        <x-button :href="route('store_admin.reports.export', ['from' => $from, 'to' => $to])" variant="secondary" icon="download">Siparişleri Excel'e aktar</x-button>
    </x-page-header>

    <form method="GET" class="mb-6 flex flex-wrap items-end gap-3">
        <x-field name="from" label="Başlangıç" type="date" :value="$from" />
        <x-field name="to" label="Bitiş" type="date" :value="$to" />
        <x-button variant="secondary">Göster</x-button>
        <div class="flex flex-wrap gap-2 text-sm">
            @foreach ($presets as $label => [$pf, $pt])
                <a href="{{ route('store_admin.reports.index', ['from' => $pf, 'to' => $pt]) }}" @class(['rounded-full px-3 py-1.5 ring-1', 'bg-primary text-white ring-primary' => $pf === $from && $pt === $to, 'bg-white ring-line hover:ring-primary' => ! ($pf === $from && $pt === $to)])>{{ $label }}</a>
            @endforeach
        </div>
    </form>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['Net satış', Money::format($s['net']), 'Sipariş tutarı '.Money::format($s['gross']).' − iade '.Money::format($s['refunded'])],
            ['Sipariş', number_format($s['orders'], 0, ',', '.'), number_format($s['items'], 0, ',', '.').' ürün · ortalama sepet '.Money::format($s['average'])],
            ['İndirim', Money::format($s['discounts']), 'Kupon ve kampanyalarla düşülen'],
            ['İptal / teslim alınmadı', number_format($s['cancelled'], 0, ',', '.'), 'Dönemde yapılan para iadesi '.Money::format($s['refunds_in_period'])],
        ] as [$label, $value, $hint])
            <x-card>
                <p class="text-sm text-muted">{{ $label }}</p>
                <p class="mt-1 text-2xl font-bold tabular-nums">{{ $value }}</p>
                <p class="mt-1 text-xs text-muted">{{ $hint }}</p>
            </x-card>
        @endforeach
    </div>

    <x-card title="Günlük satış" class="mt-6" :padding="false">
        <div class="overflow-x-auto p-5">
            <div class="flex h-40 min-w-[600px] items-end gap-1" role="img" aria-label="Günlük satış grafiği">
                @foreach ($daily as $d)
                    <div class="group relative flex flex-1 flex-col items-center justify-end" title="{{ \Illuminate\Support\Carbon::parse($d['day'])->format('d.m.Y') }}: {{ $d['orders'] }} sipariş, {{ Money::format($d['gross']) }}">
                        <div class="w-full rounded-t bg-primary/80 group-hover:bg-primary" style="height: {{ max((float) $d['gross'] > 0 ? 2 : 0, round((float) $d['gross'] / $max * 100)) }}%"></div>
                    </div>
                @endforeach
            </div>
            <div class="mt-1 flex min-w-[600px] justify-between text-xs text-muted">
                <span>{{ \Illuminate\Support\Carbon::parse($from)->format('d.m.Y') }}</span><span>{{ \Illuminate\Support\Carbon::parse($to)->format('d.m.Y') }}</span>
            </div>
        </div>
    </x-card>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <x-card title="En çok satan ürünler" :padding="false">
            @if ($products === [])
                <p class="p-5 text-sm text-muted">Bu dönemde satış yok.</p>
            @else
                <table class="table-base">
                    <thead><tr><th>Ürün</th><th class="text-right">Adet</th><th class="text-right">Net ciro</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($products as $p)
                            <tr><td>{{ $p->name }}<p class="text-xs text-muted">@if ($p->option_label){{ $p->option_label }} · @endif{{ $p->sku }}</p></td>
                                <td class="text-right tabular-nums">{{ (int) $p->quantity }}</td><td class="text-right tabular-nums">{{ Money::format($p->revenue) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-card>

        <div class="space-y-6">
            <x-card title="Ödeme ve teslimat" :padding="false">
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
                @forelse ($reasons as $reason => $count)
                    <div class="flex justify-between py-1 text-sm"><span>{{ StoreReturn::REASONS[$reason] ?? $reason }}</span><span class="tabular-nums">{{ $count }}</span></div>
                @empty
                    <p class="text-sm text-muted">Bu dönemde iade talebi yok.</p>
                @endforelse
            </x-card>
        </div>
    </div>
</x-layouts.panel>
