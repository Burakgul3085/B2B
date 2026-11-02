{{-- Sipariş ilerlemesi: $order, $compact (listede ince çubuk). Ret ve iptal ayrı gösterilir. --}}
@php
    $steps = ['submitted' => 'Gönderildi', 'in_review' => 'İnceleniyor', 'approved' => 'Onaylandı', 'preparing' => 'Hazırlanıyor', 'shipped' => 'Yola çıktı', 'completed' => 'Tamamlandı'];
    $last = count($steps) - 1;
    $index = ['submitted' => 0, 'in_review' => 1, 'approved' => 2, 'preparing' => 3, 'shipped' => 4, 'cancel_requested' => 2, 'completed' => $last][$order->status] ?? null;
    $compact ??= false;
    // "Yola çıktı" isteğe bağlı: kullanılmadan tamamlanan siparişte bu adım işaretlenmez.
    $skipped = fn (string $key) => $key === 'shipped' && $order->shipped_at === null && $index !== null && $index >= 4;
@endphp
@if ($compact)
    @if ($index === null)
        <div class="flex h-1.5 w-28 overflow-hidden rounded-full bg-slate-100"><span class="w-full {{ $order->status === 'rejected' ? 'bg-red-300' : 'bg-slate-300' }}"></span></div>
    @else
        <div class="flex w-28 gap-0.5" aria-label="İlerleme: {{ $index + 1 }}/{{ $last + 1 }}">
            @foreach ($steps as $i => $label)
                <span @class(['h-1.5 flex-1 rounded-full', $loop->index <= $index && ! $skipped($i) ? ($index === $last ? 'bg-emerald-500' : 'bg-primary') : 'bg-slate-200'])></span>
            @endforeach
        </div>
    @endif
@else
    @php
        $dates = $order->history->groupBy('to_status')->map(fn ($h) => $h->first()->created_at->timezone('Europe/Istanbul'));
        $dates['submitted'] = $order->created_at->timezone('Europe/Istanbul');
    @endphp
    @if ($index === null)
        @php $rejected = $order->status === 'rejected'; @endphp
        <div @class(['flex items-start gap-4 rounded-2xl border px-5 py-4', 'border-red-200 bg-red-50' => $rejected, 'border-slate-200 bg-slate-50' => ! $rejected])>
            <span @class(['flex size-10 shrink-0 items-center justify-center rounded-full', 'bg-red-100 text-red-700' => $rejected, 'bg-slate-200 text-slate-600' => ! $rejected])><x-icon name="x" class="size-5" /></span>
            <div class="text-sm">
                <p @class(['text-base font-semibold', 'text-red-800' => $rejected, 'text-ink' => ! $rejected])>{{ $rejected ? 'Sipariş toptancınız tarafından reddedildi' : 'Sipariş iptal edildi' }}</p>
                @if (isset($dates[$order->status]))<p class="text-muted">{{ $dates[$order->status]->format('d.m.Y H:i') }}</p>@endif
                @if ($rejected ? $order->rejected_reason : $order->cancel_reason)
                    <p class="mt-1 text-slate-700"><span class="font-medium">Neden:</span> {{ $rejected ? $order->rejected_reason : $order->cancel_reason }}</p>
                @endif
                <p class="mt-1 text-muted">Bu siparişten borç kaydı oluşmaz. İsterseniz aynı ürünleri tekrar sepete ekleyebilirsiniz.</p>
            </div>
        </div>
    @else
        <ol class="grid grid-cols-6">
            @foreach ($steps as $key => $label)
                @php $done = $loop->index <= $index && ! $skipped($key); $current = $loop->index === $index; $final = $index === $last; @endphp
                <li class="relative flex flex-col items-center text-center">
                    @unless ($loop->first)
                        <span @class(['absolute top-4 right-1/2 -z-0 h-0.5 w-full', $loop->index <= $index ? 'bg-primary' : 'bg-slate-200'])></span>
                    @endunless
                    <span @class([
                        'relative z-10 flex size-8 items-center justify-center rounded-full text-xs font-bold ring-4 ring-white',
                        'bg-emerald-500 text-white' => $done && $final,
                        'bg-primary text-white' => $done && ! $final,
                        'shadow-lg shadow-primary/30 !ring-primary/15' => $current && ! $final,
                        'bg-slate-100 text-slate-400' => ! $done,
                    ])>
                        @if ($done)<x-icon name="check" class="size-4" />@else{{ $loop->iteration }}@endif
                    </span>
                    <span @class(['mt-2 text-xs font-semibold sm:text-sm', 'text-primary' => $current && ! $final, 'text-ink' => $done && ! $current, 'text-emerald-700' => $current && $final, 'text-slate-400' => ! $done])>{{ $label }}</span>
                    <span class="text-[11px] text-muted tabular-nums">{{ $skipped($key) ? 'Kullanılmadı' : (isset($dates[$key]) && $loop->index <= $index ? $dates[$key]->format('d.m.Y') : ' ') }}</span>
                </li>
            @endforeach
        </ol>
        @if ($order->status === 'cancel_requested')
            <p class="mt-4 rounded-lg bg-amber-50 px-4 py-2.5 text-sm text-amber-900 ring-1 ring-amber-200">İptal talebiniz toptancınıza iletildi; yanıt bekleniyor.</p>
        @endif
    @endif
@endif
