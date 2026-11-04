{{-- Ödemesi bekleyen kalem satırı (toptancı ve bayi cari ekranı). $item: AccountAnalysis kalemi, $orderRoute: sipariş linki rotası. --}}
@php
    use App\Domain\Support\Money;
    use Illuminate\Support\Carbon;

    $order = $item['order'];
    $m = $item['movement'];
@endphp
<tr>
    <td>
        @if ($order)
            <a href="{{ route($orderRoute, $order->id) }}" class="font-medium hover:text-primary">{{ $order->order_no }}</a>
            <p class="text-xs text-muted">{{ $order->paymentMethodLabel() }}</p>
        @else
            <span class="font-medium">{{ \App\Models\CurrentAccountMovement::TYPES[$m->type] ?? $m->type }}</span>
            <p class="text-xs text-muted">{{ $m->description }}</p>
        @endif
    </td>
    <td class="whitespace-nowrap tabular-nums">{{ $item['due'] ? Carbon::parse($item['due'])->format('d.m.Y') : '—' }}</td>
    <td class="text-right font-medium tabular-nums">{{ Money::format($item['open']) }}</td>
    <td class="text-right">
        @if ($item['pending'])
            <x-badge>Teslim bekleniyor</x-badge>
        @elseif ($item['bucket'] === 'overdue')
            <x-badge tone="danger">{{ $item['days_overdue'] }} gün gecikti</x-badge>
        @elseif ($item['days_left'] === 0)
            <x-badge tone="warning">Bugün</x-badge>
        @elseif ($item['bucket'] === 'due_soon')
            <x-badge tone="warning">{{ $item['days_left'] }} gün kaldı</x-badge>
        @else
            <x-badge tone="info">{{ $item['days_left'] }} gün kaldı</x-badge>
        @endif
    </td>
</tr>
