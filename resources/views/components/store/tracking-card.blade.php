{{--
    Kargo takibi: firma logosu, takip numarası (kopyalanır) ve "Kargom nerede" düğmesi. Düğme kargo firmasının
    takip sayfasını numarayla açar; numarayı adresten almayan firmalarda müşteri numarayı sayfadaki kutuya yapıştırır.
--}}
@props(['order'])
@php $logo = $order->carrierLogo(); @endphp
<section {{ $attributes->merge(['class' => 'flex flex-col gap-4 rounded-2xl border border-line p-5 sm:flex-row sm:items-center']) }}>
    <span class="flex h-14 w-36 shrink-0 items-center justify-center rounded-xl bg-[#fff] px-3 ring-1 ring-line">
        @if ($logo)
            <img src="{{ $logo }}" alt="{{ $order->carrier }}" class="max-h-8 max-w-full object-contain">
        @else
            <x-icon name="truck" class="size-6 text-muted" />
        @endif
    </span>
    <div class="min-w-0 flex-1">
        <p class="text-sm text-muted">Kargo firması</p>
        <p class="font-semibold">{{ $order->carrier }}</p>
        <p class="mt-1 flex flex-wrap items-center gap-2 text-sm" x-data="copyCode(@alpine($order->tracking_number))">
            <span class="text-muted">Takip no:</span> <span class="font-mono font-medium">{{ $order->tracking_number }}</span>
            <button type="button" class="rounded-md px-2 py-0.5 text-xs font-semibold text-primary ring-1 ring-line hover:bg-surface" @click="copy()">
                <span x-show="!copied">Kopyala</span><span x-show="copied" x-cloak>Kopyalandı</span>
            </button>
        </p>
    </div>
    @if ($order->tracking_url)
        <div class="sm:text-right">
            <a href="{{ $order->tracking_url }}" target="_blank" rel="noopener noreferrer"
                class="inline-flex h-11 items-center gap-2 rounded-xl bg-navy px-5 text-sm font-semibold text-white hover:bg-puzzle-orange">
                <x-icon name="truck" class="size-4" /> Kargom nerede
            </a>
            @unless ($order->trackingIsDirect())
                <p class="mt-1.5 max-w-56 text-xs text-muted">Açılan sayfada takip numaranızı kutuya yapıştırın.</p>
            @endunless
        </div>
    @endif
</section>
