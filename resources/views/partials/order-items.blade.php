{{-- Sipariş kalemleri ve toplamlar (toptancı ve bayi ortak). $order, $imageRoute (isteğe bağlı: ürün görseli rotası; items.product.primaryImage yüklü olmalı) --}}
@php use App\Domain\Inventory\Quantity; use App\Domain\Support\Money; $approved = $order->stock_applied_at !== null; $imageRoute ??= null; @endphp
<div class="overflow-x-auto">
    <table class="table-base">
        <thead>
            <tr>
                <th>Ürün</th>
                <th class="text-right">{{ $approved ? 'İstenen / Onaylanan' : 'Miktar' }}</th>
                <th class="text-right">Birim fiyat</th>
                <th class="hidden text-right sm:table-cell">İndirim</th>
                <th class="hidden text-right md:table-cell">KDV</th>
                <th class="text-right">Tutar</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-line">
            @foreach ($order->items as $item)
                @php $short = $approved && bccomp((string) $item->quantity_approved, (string) $item->quantity_requested, 3) < 0; @endphp
                <tr>
                    <td>
                        @if ($imageRoute)
                            <div class="flex items-center gap-3">
                                <span class="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-line bg-white p-1">
                                    @if ($item->product?->primaryImage)
                                        <img src="{{ route($imageRoute, $item->product->primaryImage) }}" alt="" class="max-h-full max-w-full object-contain" loading="lazy">
                                    @else
                                        <x-icon name="image" class="size-5 text-slate-300" />
                                    @endif
                                </span>
                                <div class="min-w-0"><span class="font-medium">{{ $item->name }}</span><p class="text-xs text-muted">{{ $item->sku }}</p></div>
                            </div>
                        @else
                            <span class="font-medium">{{ $item->name }}</span><p class="text-xs text-muted">{{ $item->sku }}</p>
                        @endif
                    </td>
                    <td class="text-right whitespace-nowrap tabular-nums">
                        @if ($approved)
                            {{ Quantity::format($item->quantity_requested) }} / <span @class(['font-semibold', 'text-warning' => $short])>{{ Quantity::format($item->quantity_approved) }}</span>
                        @else
                            {{ Quantity::format($item->quantity_requested) }}
                        @endif
                        <span class="text-xs text-muted">{{ $item->unit_name }}</span>
                    </td>
                    <td class="text-right tabular-nums">{{ Money::formatUnit($item->unit_price) }}</td>
                    <td class="hidden text-right tabular-nums sm:table-cell">@if ((float) $item->discount_percent > 0)%{{ rtrim(rtrim(number_format((float) $item->discount_percent, 2, ',', ''), '0'), ',') }}@else — @endif</td>
                    <td class="hidden text-right tabular-nums md:table-cell">%{{ rtrim(rtrim(number_format((float) $item->vat_rate, 2, ',', ''), '0'), ',') }}</td>
                    <td class="text-right font-medium tabular-nums">{{ Money::format($item->line_total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
<dl class="ml-auto max-w-xs space-y-1.5 border-t border-line p-5 text-sm">
    <div class="flex justify-between"><dt class="text-muted">Ara toplam</dt><dd class="tabular-nums">{{ Money::format($order->subtotal) }}</dd></div>
    @if ((float) $order->discount_total > 0)<div class="flex justify-between text-success"><dt>İndirim</dt><dd class="tabular-nums">−{{ Money::format($order->discount_total) }}</dd></div>@endif
    <div class="flex justify-between"><dt class="text-muted">KDV</dt><dd class="tabular-nums">{{ Money::format($order->vat_total) }}</dd></div>
    <div class="flex justify-between border-t border-line pt-1.5 text-base font-semibold"><dt>Genel toplam</dt><dd class="tabular-nums">{{ Money::format($order->grand_total) }}</dd></div>
</dl>
