@php use App\Domain\Support\Money; use App\Models\Order; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Dealer" title="Siparişlerim">
    <x-page-header title="Siparişlerim" />

    <x-card :padding="false">
        <form method="GET" class="flex flex-wrap gap-2 border-b border-line p-4">
            <select name="status" class="field-input w-auto" aria-label="Durum" onchange="this.form.submit()">
                <option value="">Tüm siparişler</option>
                <option value="open" @selected(($filters['status'] ?? '') === 'open')>Devam edenler</option>
                @foreach (Order::STATUSES as $k => $l)<option value="{{ $k }}" @selected(($filters['status'] ?? '') === $k)>{{ $l }}</option>@endforeach
            </select>
            <input name="q" value="{{ $filters['q'] ?? '' }}" class="field-input max-w-48" placeholder="Sipariş no" aria-label="Sipariş no">
            <x-button variant="secondary" icon="search">Ara</x-button>
        </form>
        @if ($orders->isEmpty())
            <x-empty-state icon="file" title="Sipariş bulunamadı">
                <x-button :href="route('dealer.catalog')">Kataloğa git</x-button>
            </x-empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>Sipariş</th><th>Tarih</th><th class="text-right">Kalem</th><th class="text-right">Tutar</th><th>Durum</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($orders as $order)
                            <tr>
                                <td><a href="{{ route('dealer.orders.show', $order) }}" class="font-medium text-primary hover:underline">{{ $order->order_no }}</a></td>
                                <td class="text-muted tabular-nums">{{ $order->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</td>
                                <td class="text-right tabular-nums">{{ $order->items_count }}</td>
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
