@php use App\Models\Order; @endphp
<ol class="relative space-y-5 before:absolute before:top-2 before:bottom-2 before:left-[5px] before:w-px before:bg-line">
    @foreach ($order->history as $h)
        @php
            $dot = ['completed' => 'bg-emerald-500', 'approved' => 'bg-emerald-500', 'rejected' => 'bg-red-500', 'cancelled' => 'bg-slate-400', 'cancel_requested' => 'bg-amber-400'][$h->to_status] ?? 'bg-primary';
        @endphp
        <li class="relative flex gap-3 text-sm">
            <span class="relative mt-1.5 size-[11px] shrink-0 rounded-full ring-4 ring-white {{ $dot }}"></span>
            <div class="min-w-0">
                <p class="font-semibold text-ink">{{ Order::STATUSES[$h->to_status] ?? $h->to_status }}</p>
                <p class="text-xs text-muted">{{ $h->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }} · {{ ['business' => 'Toptancı', 'dealer' => 'Bayi'][$h->actor_type] ?? 'Sistem' }}</p>
                @if ($h->note)<p class="mt-1 rounded-lg bg-slate-50 px-3 py-2 text-slate-700">{{ $h->note }}</p>@endif
            </div>
        </li>
    @endforeach
</ol>
