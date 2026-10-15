<x-layouts.panel :area="\App\Domain\Auth\Area::Dealer" :title="$order->order_no">
    <x-page-header :title="'Sipariş '.$order->order_no" :subtitle="$order->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i')">
        <x-slot:breadcrumb><a href="{{ route('dealer.orders.index') }}" class="hover:underline">Siparişlerim</a></x-slot:breadcrumb>
        <x-badge :tone="$order->statusTone()">{{ $order->statusLabel() }}</x-badge>
    </x-page-header>

    @error('order')<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror

    @if ($order->status === 'rejected' && $order->rejected_reason)
        <div class="mb-6 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900"><b>Ret nedeni:</b> {{ $order->rejected_reason }}</div>
    @endif
    @if ($order->is_partial && $order->stock_applied_at)
        <div class="mb-6 rounded-lg border border-amber-200 bg-warning-soft px-4 py-3 text-sm text-amber-900">Siparişiniz kısmi onaylandı: bazı kalemler stok nedeniyle istenenden az karşılandı. Tutar onaylanan miktarlara göre güncellendi.</div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card title="Ürünler" class="lg:col-span-2" :padding="false">@include('partials.order-items', ['order' => $order])</x-card>

        <div class="space-y-6">
            <x-card title="Sevk bilgileri">
                <div class="space-y-1 text-sm">
                    @foreach ($order->shippingLines() as $line)<p>{{ $line }}</p>@endforeach
                </div>
                @if ($order->delivery_planned_on || $order->delivery_note)
                    <div class="mt-4 border-t border-line pt-3 text-sm">
                        @if ($order->delivery_planned_on)<p><span class="text-muted">Planlanan teslim:</span> {{ $order->delivery_planned_on->format('d.m.Y') }}</p>@endif
                        @if ($order->delivery_note)<p><span class="text-muted">Toptancı notu:</span> {{ $order->delivery_note }}</p>@endif
                    </div>
                @endif
                @if ($order->dealer_note)<p class="mt-4 border-t border-line pt-3 text-sm"><span class="text-muted">Notunuz:</span> {{ $order->dealer_note }}</p>@endif
            </x-card>

            <x-card title="Durum geçmişi">@include('partials.order-history', ['order' => $order])</x-card>

            @if ($canCancel || $canRequestCancel)
                <x-card :title="$canCancel ? 'Siparişi iptal et' : 'İptal talebi'" :description="$canCancel ? 'Sipariş henüz onaylanmadı; doğrudan iptal edebilirsiniz.' : 'Sipariş onaylandı; iptal için toptancınızın onayı gerekir.'">
                    <form method="POST" action="{{ route('dealer.orders.cancel', $order) }}" class="space-y-3" onsubmit="return confirm('Emin misiniz?')">
                        @csrf
                        <x-field name="reason" label="İptal nedeni" textarea required />
                        <x-button variant="danger" size="sm">{{ $canCancel ? 'İptal et' : 'İptal talebi gönder' }}</x-button>
                    </form>
                </x-card>
            @endif
        </div>
    </div>
</x-layouts.panel>
