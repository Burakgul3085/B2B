{{-- Sipariş yazdırma belgesi (toptancı ve bayi ortak). $order, $business; $dueDate (isteğe bağlı: cari borcun son ödeme günü). --}}
@php
    use App\Models\Order;
    use App\Domain\Support\Money;
    use Illuminate\Support\Carbon;

    $dueDate ??= null;
    $closed = in_array($order->status, ['rejected', 'cancelled'], true);
@endphp
<x-print-document title="Sipariş" :number="$order->order_no" :business="$business" :dealer="$order->relationship->dealer"
    :address="array_filter([$order->ship_title, trim($order->ship_recipient.' · '.$order->ship_phone, ' ·'), $order->ship_address, $order->ship_district.' / '.$order->ship_city, $order->ship_note ? 'Adres notu: '.$order->ship_note : null])"
    :meta="[
        'Sipariş tarihi' => $order->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i'),
        'Durum' => $order->statusLabel(),
        'Ödeme yöntemi' => $order->payment_method ? $order->paymentMethodLabel() : null,
        'Ödeme durumu' => $order->payment_status ? Order::PAYMENT_STATUSES[$order->payment_status] : null,
        'Teslimat ücreti' => bccomp((string) $order->delivery_fee, '0', 2) > 0 ? Money::format($order->delivery_fee) : null,
        'Son ödeme günü' => $dueDate && ! $closed ? Carbon::parse($dueDate)->format('d.m.Y') : null,
        'Planlanan teslim' => $order->delivery_planned_on?->format('d.m.Y'),
        'Yola çıktı' => $order->shipped_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i'),
    ]">
    @include('partials.order-items', ['order' => $order])
    @if ($order->dealer_note || $order->delivery_note || $order->shipping_note || ($closed && $order->cancel_reason))
        <x-slot:notes>
            @if ($order->dealer_note)<p><b>Bayi notu:</b> {{ $order->dealer_note }}</p>@endif
            @if ($order->delivery_note)<p><b>Toptancı notu:</b> {{ $order->delivery_note }}</p>@endif
            @if ($order->shipping_note)<p><b>Sevk notu:</b> {{ $order->shipping_note }}</p>@endif
            @if ($closed && $order->cancel_reason)<p><b>{{ $order->status === 'rejected' ? 'Ret' : 'İptal' }} nedeni:</b> {{ $order->cancel_reason }}</p>@endif
        </x-slot:notes>
    @endif
</x-print-document>
