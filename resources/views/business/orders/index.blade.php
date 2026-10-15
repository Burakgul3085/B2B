@php use App\Domain\Support\Money; use App\Models\Order; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Business" title="Siparişler">
    <x-page-header title="Siparişler" subtitle="Bayilerden gelen siparişler. Stok yalnızca onayda düşer." />

    <nav class="mb-4 flex flex-wrap gap-2" aria-label="Durum">
        @php $pendingCount = ($counts['submitted'] ?? 0) + ($counts['in_review'] ?? 0) + ($counts['cancel_requested'] ?? 0); @endphp
        @foreach (['pending' => 'Karar bekleyen', 'approved' => 'Onaylandı', 'preparing' => 'Hazırlanıyor', 'completed' => 'Tamamlandı', 'rejected' => 'Reddedildi', 'cancelled' => 'İptal'] as $key => $label)
            <a href="{{ route('business.orders.index', ['status' => $key]) }}" @class([
                'rounded-full px-3 py-1.5 text-sm font-medium',
                'bg-primary text-white' => $status === $key,
                'bg-white text-muted ring-1 ring-line hover:text-ink' => $status !== $key,
            ])>{{ $label }} <span class="tabular-nums opacity-75">{{ $key === 'pending' ? $pendingCount : ($counts[$key] ?? 0) }}</span></a>
        @endforeach
    </nav>

    <x-card :padding="false">
        <form method="GET" class="grid gap-3 border-b border-line p-4 sm:grid-cols-5 sm:items-end">
            <input type="hidden" name="status" value="{{ $status }}">
            <x-field name="q" label="Sipariş no" :value="$filters['q'] ?? ''" />
            <x-field name="dealer" label="Bayi" :options="['' => 'Tümü'] + $dealers->all()" :value="$filters['dealer'] ?? ''" class="sm:col-span-2" />
            <x-field name="from" label="Başlangıç" type="date" :value="$filters['from'] ?? ''" />
            <div class="flex gap-2">
                <x-field name="to" label="Bitiş" type="date" :value="$filters['to'] ?? ''" class="flex-1" />
                <x-button variant="secondary" class="self-end" aria-label="Filtrele"><x-icon name="search" class="size-4" /></x-button>
            </div>
        </form>
        @if ($orders->isEmpty())
            <x-empty-state icon="file" title="{{ $status === 'pending' ? 'Karar bekleyen sipariş yok' : 'Sipariş bulunamadı' }}" />
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>Sipariş</th><th>Bayi</th><th class="hidden sm:table-cell">Tarih</th><th class="text-right">Tutar</th><th>Durum</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($orders as $order)
                            <tr>
                                <td><a href="{{ route('business.orders.show', $order) }}" class="font-medium text-primary hover:underline">{{ $order->order_no }}</a><p class="text-xs text-muted">{{ $order->items_count }} kalem</p></td>
                                <td>{{ $order->relationship->dealer->legal_name }}</td>
                                <td class="hidden text-muted tabular-nums sm:table-cell">{{ $order->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</td>
                                <td class="text-right font-medium tabular-nums">{{ Money::format($order->grand_total) }}</td>
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
