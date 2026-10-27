@php use App\Domain\Support\Money; @endphp
<x-store.account-shell title="Siparişlerim" active="orders">
    <p class="text-xs font-bold tracking-[0.18em] text-puzzle-orange uppercase">Hesabım</p>
    <h1 class="mt-1 font-display text-3xl font-extrabold tracking-tight sm:text-4xl">Siparişlerim</h1>
    <p class="mt-1 text-muted">Siparişlerinizin durumu, faturaları ve iade talepleri.</p>

    @if ($orders->isEmpty())
        <div class="mt-8 flex flex-col items-center rounded-3xl border border-line bg-white px-6 py-14 text-center">
            <span class="flex size-16 items-center justify-center rounded-2xl bg-puzzle-orange-soft text-puzzle-orange"><x-icon name="package" class="size-8" /></span>
            <p class="mt-4 font-display text-lg font-extrabold">Henüz siparişiniz yok.</p>
            <p class="mt-1 text-sm text-muted">Verdiğiniz siparişler burada listelenir.</p>
            <a href="{{ route('store.products') }}" class="mt-6 inline-flex h-11 items-center rounded-xl bg-navy px-5 text-sm font-semibold text-white hover:bg-puzzle-orange">Alışverişe başla</a>
        </div>
    @else
        <ul class="mt-8 space-y-3">
            @foreach ($orders as $order)
                <li>
                    <a href="{{ route('store.orders.show', $order->order_no) }}" class="group flex flex-wrap items-center justify-between gap-4 rounded-3xl border border-line bg-white px-5 py-4 transition hover:-translate-y-0.5 hover:border-transparent hover:shadow-xl hover:shadow-navy/10 sm:px-6">
                        <span class="flex items-center gap-4">
                            <span class="flex size-12 items-center justify-center rounded-2xl bg-surface text-navy-ink"><x-icon name="file" class="size-5" /></span>
                            <span>
                                <span class="block font-display font-extrabold">{{ $order->order_no }}</span>
                                <span class="block text-xs text-muted">{{ $order->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }} · {{ $order->items_count }} kalem</span>
                            </span>
                        </span>
                        <span class="flex items-center gap-4">
                            <x-badge :tone="$order->statusTone()">{{ $order->statusLabel() }}</x-badge>
                            <span class="font-display text-lg font-extrabold tabular-nums">{{ Money::format($order->total) }}</span>
                            <x-icon name="arrow-right" class="size-4 text-muted transition group-hover:translate-x-1" />
                        </span>
                    </a>
                    @if ($order->tracking_number && $order->tracking_url && $order->status === 'shipped')
                        <div class="mx-5 -mt-1 flex flex-wrap items-center justify-between gap-3 rounded-b-2xl border border-t-0 border-line bg-surface/60 px-5 py-3 text-sm sm:mx-6">
                            <span class="flex items-center gap-3">
                                @if ($logo = $order->carrierLogo())<span class="flex h-8 w-20 items-center justify-center rounded-md bg-[#fff] px-1.5 ring-1 ring-line"><img src="{{ $logo }}" alt="" class="max-h-5 max-w-full object-contain" loading="lazy"></span>@endif
                                <span>{{ $order->carrier }} · <span class="font-mono">{{ $order->tracking_number }}</span></span>
                            </span>
                            <a href="{{ $order->tracking_url }}" target="_blank" rel="noopener noreferrer" class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-navy px-3.5 text-xs font-semibold text-white hover:bg-puzzle-orange">
                                <x-icon name="truck" class="size-4" /> Kargom nerede</a>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
        <div class="mt-6">{{ $orders->links() }}</div>
    @endif
</x-store.account-shell>
