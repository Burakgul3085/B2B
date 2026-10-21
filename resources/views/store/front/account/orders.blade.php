@php use App\Domain\Support\Money; @endphp
<x-layouts.store title="Siparişlerim" :noindex="true">
    <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
        <a href="{{ route('store.account') }}" class="text-sm text-muted hover:text-primary">← Hesabım</a>
        <h1 class="mt-3 text-2xl font-bold tracking-tight sm:text-3xl">Siparişlerim</h1>

        @if ($orders->isEmpty())
            <p class="mt-8 rounded-2xl border border-dashed border-line px-6 py-12 text-center text-sm text-muted">Henüz siparişiniz yok.</p>
        @else
            <ul class="mt-6 divide-y divide-line rounded-2xl border border-line">
                @foreach ($orders as $order)
                    <li>
                        <a href="{{ route('store.orders.show', $order->order_no) }}" class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 hover:bg-slate-50">
                            <span>
                                <span class="font-semibold">{{ $order->order_no }}</span>
                                <span class="block text-xs text-muted">{{ $order->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }} · {{ $order->items_count }} kalem</span>
                            </span>
                            <span class="flex items-center gap-4">
                                <x-badge :tone="$order->statusTone()">{{ $order->statusLabel() }}</x-badge>
                                <span class="font-semibold tabular-nums">{{ Money::format($order->total) }}</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="mt-6">{{ $orders->links() }}</div>
        @endif
    </div>
</x-layouts.store>
