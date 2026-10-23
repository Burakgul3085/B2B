{{-- Mini sepet çekmecesinin içeriği (fetch ile yüklenir): satırlar, ücretsiz kargoya kalan tutar, düğmeler. --}}
@php
    use App\Domain\Support\Decimal;
    use App\Domain\Support\Money;
    use App\Models\Store\StoreSetting;

    $subtotal = $summary->subtotal();
    $threshold = Decimal::parse(StoreSetting::get('shipping_free_threshold'), 2);
    $remaining = $threshold ? bcsub($threshold, $subtotal, 2) : null;
    $progress = $threshold && bccomp($threshold, '0', 2) > 0 ? min(100, (int) round((float) $subtotal / (float) $threshold * 100)) : 0;
@endphp
@if ($summary->isEmpty())
    <div class="flex flex-1 flex-col items-center justify-center gap-4 px-6 py-16 text-center">
        <span class="flex size-16 items-center justify-center rounded-full bg-navy-soft text-navy-ink"><x-icon name="cart" class="size-7" /></span>
        <p class="font-display text-lg font-bold">Sepetiniz boş</p>
        <p class="text-sm text-muted">Beğendiğiniz ürünleri sepete ekleyerek alışverişe başlayın.</p>
        <a href="{{ route('store.products') }}" class="rounded-xl bg-navy px-5 py-2.5 text-sm font-semibold text-white hover:bg-navy-2">Ürünlere göz at</a>
    </div>
@else
    @if ($threshold)
        <div class="border-b border-line px-5 py-4 text-sm">
            @if (bccomp($remaining, '0', 2) > 0)
                <p>Ücretsiz kargo için <span class="font-semibold text-puzzle-orange">{{ Money::format($remaining) }}</span> daha ekleyin.</p>
            @else
                <p class="flex items-center gap-2 font-semibold text-puzzle-green"><x-icon name="truck" class="size-4" /> Kargonuz ücretsiz!</p>
            @endif
            <div class="mt-2 h-2 overflow-hidden rounded-full bg-surface"><div class="h-full rounded-full bg-gradient-to-r from-puzzle-orange to-puzzle-yellow" style="width: {{ $progress }}%"></div></div>
        </div>
    @endif
    <ul class="flex-1 divide-y divide-line overflow-y-auto">
        @foreach ($summary->lines as $line)
            <li class="flex gap-3 px-5 py-4">
                <a href="{{ route('store.product', $line->product->slug) }}" class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-line bg-[#fff]">
                    @if ($line->product->primaryImage)
                        <img src="{{ route('store.media.product', ['storeImage' => $line->product->primaryImage, 'w' => 160]) }}" alt="" class="max-h-full max-w-full object-contain">
                    @endif
                </a>
                <div class="min-w-0 flex-1 text-sm">
                    <a href="{{ route('store.product', $line->product->slug) }}" class="line-clamp-2 font-medium hover:text-primary">{{ $line->product->name }}</a>
                    @if ($line->variant->option_value)<p class="text-xs text-muted">{{ $line->variant->option_value }}</p>@endif
                    <p class="mt-1 text-muted tabular-nums">{{ $line->quantity() }} × {{ Money::format($line->unitPrice()) }}</p>
                    @if ($line->issue)<p class="mt-1 text-xs font-medium text-danger">Stok değişti; sepette kontrol edin.</p>@endif
                </div>
                <p class="text-sm font-semibold tabular-nums">{{ Money::format($line->total()) }}</p>
            </li>
        @endforeach
    </ul>
    <div class="space-y-3 border-t border-line px-5 py-5">
        <div class="flex justify-between font-display text-base font-bold"><span>Ara toplam</span><span class="tabular-nums">{{ Money::format($subtotal) }}</span></div>
        <p class="text-xs text-muted">KDV dahildir. Teslimat ücreti ödeme adımında hesaplanır.</p>
        <a href="{{ route('store.checkout') }}" class="block rounded-xl bg-navy py-3 text-center font-semibold text-white hover:bg-navy-2">Siparişi tamamla</a>
        <a href="{{ route('store.cart') }}" class="block rounded-xl border border-line py-3 text-center font-semibold hover:bg-surface">Sepete git</a>
    </div>
@endif
