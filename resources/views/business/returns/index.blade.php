@php use App\Models\OrderReturnRequest; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Business" title="İade Talepleri">
    <x-page-header title="İade Talepleri" subtitle="Bayilerin teslim edilmiş siparişlerden açtığı iade talepleri. Ürünleri teslim aldığınızda bayinin carisine alacak yazılır." />

    <nav class="mb-4 flex flex-wrap gap-2" aria-label="Durum">
        @foreach (['open' => 'Açık'] + OrderReturnRequest::STATUSES as $key => $label)
            <a href="{{ route('business.returns.index', ['status' => $key]) }}" @class([
                'rounded-full px-3 py-1.5 text-sm font-medium',
                'bg-primary text-white' => $status === $key,
                'bg-white text-muted ring-1 ring-line hover:text-ink' => $status !== $key,
            ])>{{ $label }} <span class="tabular-nums opacity-75">{{ $key === 'open' ? collect(OrderReturnRequest::OPEN)->sum(fn ($s) => $counts[$s] ?? 0) : ($counts[$key] ?? 0) }}</span></a>
        @endforeach
    </nav>

    <x-card :padding="false">
        @if ($requests->isEmpty())
            <x-empty-state icon="refresh" title="{{ $status === 'open' ? 'Açık iade talebi yok' : 'İade talebi bulunamadı' }}" />
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>Talep</th><th>Bayi</th><th>Sipariş</th><th class="hidden sm:table-cell">Tarih</th><th>Neden</th><th>Durum</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($requests as $r)
                            <tr>
                                <td><a href="{{ route('business.returns.show', $r) }}" class="font-medium text-primary hover:underline">{{ $r->request_no }}</a><p class="text-xs text-muted">{{ $r->items_count }} kalem</p></td>
                                <td>{{ $r->relationship->dealer->legal_name }}</td>
                                <td><a href="{{ route('business.orders.show', $r->order_id) }}" class="hover:underline">{{ $r->order->order_no }}</a></td>
                                <td class="hidden text-muted tabular-nums sm:table-cell">{{ $r->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</td>
                                <td>{{ $r->reasonLabel() }}</td>
                                <td><x-badge :tone="$r->statusColor()">{{ $r->statusLabel() }}</x-badge></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($requests->hasPages())<div class="border-t border-line p-4">{{ $requests->links() }}</div>@endif
        @endif
    </x-card>
</x-layouts.panel>
