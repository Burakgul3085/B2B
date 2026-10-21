@php use App\Models\Store\StoreReturn; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" title="İadeler">
    <x-page-header title="İadeler" subtitle="Müşterilerin cayma hakkıyla açtığı iade talepleri. Onaylanan iadede ürün teslim alındıktan sonra para iadesi yapılır." />

    <div class="mb-4 flex flex-wrap gap-2 text-sm">
        @foreach (['' => 'Tümü'] + StoreReturn::STATUSES as $key => $label)
            @php
                $count = $key === '' ? null : ($counts[$key] ?? 0);
                $active = ($filters['status'] ?? '') === $key;
            @endphp
            <a href="{{ route('store_admin.returns.index', array_filter(['status' => $key])) }}" @class([
                'rounded-full px-3.5 py-1.5 font-medium ring-1',
                'bg-primary text-white ring-primary' => $active,
                'bg-white text-ink ring-line hover:ring-primary' => ! $active,
            ])>{{ $label }}@if ($count) <span class="ml-1 tabular-nums opacity-80">{{ $count }}</span>@endif</a>
        @endforeach
    </div>

    <x-card :padding="false">
        <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-line p-4">
            @if ($filters['status'] ?? null)<input type="hidden" name="status" value="{{ $filters['status'] }}">@endif
            <x-field name="q" label="Ara" :value="$filters['q'] ?? ''" placeholder="İade no, sipariş no, müşteri adı" class="min-w-64 flex-1" />
            <x-button variant="secondary" aria-label="Ara"><x-icon name="search" class="size-4" /></x-button>
        </form>

        @if ($returns->isEmpty())
            <x-empty-state icon="refresh" title="İade talebi yok" text="Müşteriler teslim edilen siparişlerinde, sipariş sayfasından iade talebi açabilir." />
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>İade</th><th>Sipariş</th><th>Neden</th><th class="text-right">Adet</th><th>Durum</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($returns as $return)
                            <tr>
                                <td>
                                    <a href="{{ route('store_admin.returns.show', $return) }}" class="font-semibold text-ink hover:text-primary">{{ $return->return_no }}</a>
                                    <p class="text-xs text-muted">{{ $return->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</p>
                                </td>
                                <td>{{ $return->order->order_no }}<p class="text-xs text-muted">{{ $return->order->shipping_name }}</p></td>
                                <td class="text-sm">{{ StoreReturn::REASONS[$return->reason] ?? $return->reason }}</td>
                                <td class="text-right tabular-nums">{{ (int) $return->items_sum_quantity }}</td>
                                <td><x-badge :tone="$return->statusTone()">{{ $return->statusLabel() }}</x-badge></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($returns->hasPages())<div class="border-t border-line p-4">{{ $returns->links() }}</div>@endif
        @endif
    </x-card>
</x-layouts.panel>
