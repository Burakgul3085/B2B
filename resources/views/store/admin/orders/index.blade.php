@php use App\Domain\Support\Money; use App\Models\Store\StoreOrder; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" title="Siparişler">
    <x-page-header title="Siparişler" subtitle="Mağaza siparişleri. Kapıda ödemeli ilk siparişler teyit için onay bekler." />

    <div class="mb-4 flex flex-wrap gap-2 text-sm">
        @foreach (['' => 'Tümü', 'new' => 'Yeni (işlem bekleyen)', 'awaiting_transfer' => 'Havale bekleyen', 'preparing' => 'Hazırlanıyor', 'shipped' => 'Yolda', 'delivered' => 'Teslim edildi', 'cancelled' => 'İptal'] as $key => $label)
            @php
                $count = $key === 'new' ? ($counts['awaiting_approval'] ?? 0) + ($counts['paid'] ?? 0) : ($key === '' ? null : ($counts[$key] ?? 0));
                $active = ($filters['status'] ?? '') === $key;
            @endphp
            <a href="{{ route('store_admin.orders.index', array_filter(['status' => $key])) }}" @class([
                'rounded-full px-3.5 py-1.5 font-medium ring-1',
                'bg-primary text-white ring-primary' => $active,
                'bg-white text-ink ring-line hover:ring-primary' => ! $active,
            ])>{{ $label }}@if ($count) <span class="ml-1 tabular-nums opacity-80">{{ $count }}</span>@endif</a>
        @endforeach
    </div>

    <x-card :padding="false">
        <form method="GET" class="grid gap-3 border-b border-line p-4 sm:grid-cols-2 lg:grid-cols-5 lg:items-end">
            @if ($filters['status'] ?? null)<input type="hidden" name="status" value="{{ $filters['status'] }}">@endif
            <x-field name="q" label="Ara" :value="$filters['q'] ?? ''" placeholder="Sipariş no, ad, e-posta, telefon" class="lg:col-span-2" />
            <x-field name="payment" label="Ödeme" :options="['' => 'Tümü'] + StoreOrder::PAYMENT_METHODS" :value="$filters['payment'] ?? ''" />
            <x-field name="from" label="Başlangıç" type="date" :value="$filters['from'] ?? ''" />
            <div class="flex gap-2"><x-field name="to" label="Bitiş" type="date" :value="$filters['to'] ?? ''" class="flex-1" /><x-button variant="secondary" class="self-end" aria-label="Filtrele"><x-icon name="search" class="size-4" /></x-button></div>
        </form>

        @if ($orders->isEmpty())
            <x-empty-state icon="file" title="Sipariş yok" text="Mağazadan gelen siparişler burada listelenir." />
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>Sipariş</th><th>Müşteri</th><th>Ödeme / teslimat</th><th class="text-right">Tutar</th><th>Durum</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($orders as $order)
                            <tr>
                                <td>
                                    <a href="{{ route('store_admin.orders.show', $order) }}" class="font-semibold text-ink hover:text-primary">{{ $order->order_no }}</a>
                                    <p class="text-xs text-muted">{{ $order->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }} · {{ $order->items_count }} kalem</p>
                                </td>
                                <td>{{ $order->shipping_name }}<p class="text-xs text-muted">{{ $order->shipping_district }} / {{ $order->shipping_province }}@unless ($order->customer_id) · misafir @endunless</p></td>
                                <td class="text-sm">{{ StoreOrder::PAYMENT_METHODS[$order->payment_method] }}<p class="text-xs text-muted">{{ StoreOrder::DELIVERY_METHODS[$order->delivery_method] }}</p></td>
                                <td class="text-right font-semibold tabular-nums">{{ Money::format($order->total) }}</td>
                                <td><x-badge :tone="$order->statusTone()">{{ $order->statusLabel() }}</x-badge></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($orders->hasPages())<div class="border-t border-line p-4">{{ $orders->links() }}</div>@endif
        @endif
    </x-card>
</x-layouts.panel>
