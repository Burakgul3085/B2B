@php use App\Models\Order; @endphp
<ol class="space-y-4">
    @foreach ($order->history as $h)
        <li class="flex gap-3 text-sm">
            <span class="mt-1.5 size-2 shrink-0 rounded-full bg-primary"></span>
            <div>
                <p class="font-medium">{{ Order::STATUSES[$h->to_status] ?? $h->to_status }}</p>
                <p class="text-xs text-muted">{{ $h->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }} · {{ ['business' => 'Toptancı', 'dealer' => 'Bayi'][$h->actor_type] ?? 'Sistem' }}</p>
                @if ($h->note)<p class="mt-0.5 text-slate-700">{{ $h->note }}</p>@endif
            </div>
        </li>
    @endforeach
</ol>
