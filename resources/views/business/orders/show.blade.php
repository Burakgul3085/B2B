@php use App\Domain\Inventory\Quantity; use App\Domain\Support\Money; use App\Models\Order; use App\Models\OrderPayment; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Business" :title="$order->order_no">
    <x-page-header :title="'Sipariş '.$order->order_no" :subtitle="$order->relationship->dealer->legal_name.' · '.$order->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i')">
        <x-slot:breadcrumb><a href="{{ route('business.orders.index') }}" class="hover:underline">Siparişler</a></x-slot:breadcrumb>
        <x-badge :tone="$order->statusTone()">{{ $order->statusLabel() }}</x-badge>
        <x-button variant="secondary" size="sm" :href="route('business.dealers.show', $order->relationship)">Bayi</x-button>
        @if ($currentBusiness->moduleEnabled('messaging'))
            <x-button variant="secondary" size="sm" icon="inbox" :href="route('business.messages.create', ['dealer' => $order->dealer_relationship_id, 'context' => 'order', 'id' => $order->id])">Mesaj</x-button>
        @endif
    </x-page-header>

    @error('order')<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror

    @if ($order->status === 'cancel_requested')
        <div class="mb-6 rounded-xl border border-amber-200 bg-warning-soft p-4 text-sm text-amber-900">
            <p class="font-semibold">Bayi iptal talep etti</p>
            <p class="mt-1">Gerekçe: {{ $order->cancel_reason }}</p>
            <form method="POST" action="{{ route('business.orders.resolve-cancel', $order) }}" class="mt-3 flex flex-wrap items-center gap-2">
                @csrf
                <input name="note" class="field-input max-w-xs py-1.5" placeholder="Not (reddederken)" aria-label="Not">
                <x-button name="decision" value="accept" variant="danger" size="sm" data-confirm="İptal kabul edilsin mi? Düşülen stok ve cari borç geri alınacak.">İptali kabul et</x-button>
                <x-button name="decision" value="decline" variant="secondary" size="sm">Reddet</x-button>
            </form>
        </div>
    @endif

    @if ($canApprove)
        {{-- Onay ekranı: kalem bazında miktar, depo ve teslimat bilgisi --}}
        <form method="POST" action="{{ route('business.orders.approve', $order) }}" x-data="{ wh: '{{ old('warehouse_id', $order->warehouse_id) }}', stock: @alpine($stock) }"
            data-confirm="Sipariş onaylansın mı? Onaylanan miktarlar stoktan düşülecek.">
            @csrf
            <x-card title="Onay" description="Stoğu yetmeyen kalemlerde miktarı düşürerek kısmi onay verebilirsiniz. Kesin stok kontrolü onay anında yapılır." :padding="false" class="mb-6">
                <div class="flex flex-wrap items-end gap-4 border-b border-line p-5">
                    <div class="w-64">
                        <label for="wh" class="mb-1.5 block text-sm font-medium">Sevk deposu</label>
                        <select id="wh" name="warehouse_id" x-model="wh" class="field-input">
                            @foreach ($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach
                        </select>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="table-base">
                        <thead><tr><th>Ürün</th><th class="text-right">İstenen</th><th class="text-right">Depodaki stok</th><th class="text-right">Onaylanan</th></tr></thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($order->items as $item)
                                <tr x-data="{ req: {{ (float) $item->quantity_requested }} }">
                                    <td><span class="font-medium">{{ $item->name }}</span><p class="text-xs text-muted">{{ $item->sku }}</p></td>
                                    <td class="text-right tabular-nums">{{ Quantity::format($item->quantity_requested) }} {{ $item->unit_name }}</td>
                                    <td class="text-right tabular-nums">
                                        <span x-text="(+((stock[wh] || {})[{{ $item->product_id }}] || 0)).toLocaleString('tr-TR')"
                                            :class="(+((stock[wh] || {})[{{ $item->product_id }}] || 0)) < req ? 'font-semibold text-danger' : 'text-success'"></span>
                                    </td>
                                    <td class="text-right">
                                        <input name="qty[{{ $item->id }}]" value="{{ old('qty.'.$item->id, Quantity::format($item->quantity_requested)) }}" inputmode="decimal" class="field-input ml-auto w-24 py-1 text-right" aria-label="{{ $item->name }} onaylanan miktar">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="grid gap-4 border-t border-line p-5 sm:grid-cols-2 lg:grid-cols-4">
                    <x-field name="delivery_recipient" label="Teslim alacak kişi" :value="$order->delivery_recipient" />
                    <x-field name="delivery_phone" label="İletişim telefonu" :value="$order->delivery_phone" />
                    <x-field name="delivery_planned_on" label="Planlanan teslim tarihi" type="date" />
                    <x-field name="delivery_note" label="Teslimat notu" />
                </div>
                <div class="flex flex-wrap justify-end gap-2 border-t border-line p-5">
                    @if ($order->status === 'submitted')
                        <x-button type="submit" form="review-form" variant="secondary">İncelemeye al</x-button>
                    @endif
                    <x-button icon="check">Siparişi onayla</x-button>
                </div>
            </x-card>
        </form>
        @if ($order->status === 'submitted')
            <form id="review-form" method="POST" action="{{ route('business.orders.review', $order) }}">@csrf</form>
        @endif
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card :title="$canApprove ? 'Sipariş tutarı (istenen miktarlarla)' : 'Ürünler'" :padding="false">@include('partials.order-items', ['order' => $order])</x-card>

            @if ($returns->isNotEmpty())
                <x-card title="İadeler" :padding="false">
                    <ul class="divide-y divide-line text-sm">
                        @foreach ($returns as $r)
                            <li class="flex justify-between gap-3 px-5 py-3">
                                <span>{{ $r->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }} · {{ $r->items->count() }} kalem @if ($r->note)<span class="text-muted">· {{ $r->note }}</span>@endif</span>
                                <span class="font-medium tabular-nums">{{ Money::format($r->grand_total) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif

            @if ($order->status === 'completed' && collect($returnable)->contains(fn ($q) => bccomp($q, '0', 3) > 0))
                <x-card title="İade kaydı" description="İade edilen miktar sevk deposuna girer ve bayinin carisine sipariş fiyatıyla alacak yazılır." :padding="false">
                    @error('return')<div role="alert" class="mx-5 mt-4 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror
                    <form method="POST" action="{{ route('business.orders.returns.store', $order) }}" data-confirm="İade kaydedilsin mi? Stok girişi ve cari alacak yazılacak.">
                        @csrf
                        <table class="table-base">
                            <thead><tr><th>Ürün</th><th class="text-right">İade edilebilir</th><th class="text-right">İade miktarı</th></tr></thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($order->items as $item)
                                    @continue(bccomp($returnable[$item->id] ?? '0', '0', 3) <= 0)
                                    <tr>
                                        <td>{{ $item->name }}</td>
                                        <td class="text-right tabular-nums">{{ Quantity::format($returnable[$item->id]) }} {{ $item->unit_name }}</td>
                                        <td class="text-right"><input name="qty[{{ $item->id }}]" value="{{ old('qty.'.$item->id) }}" inputmode="decimal" class="field-input ml-auto w-24 py-1 text-right" aria-label="{{ $item->name }} iade miktarı"></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <div class="flex flex-wrap items-end gap-3 border-t border-line p-4">
                            <x-field name="note" label="Açıklama" class="min-w-60 flex-1" />
                            <x-button variant="secondary">İadeyi kaydet</x-button>
                        </div>
                    </form>
                </x-card>
            @endif
        </div>

        <div class="space-y-6">
            @if ($order->payment_method || $order->payments->isNotEmpty())
                <x-card title="Ödeme">
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-muted">Yöntem</dt><dd class="font-medium">{{ $order->paymentMethodLabel() }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-muted">Durum</dt><dd>
                            @if ($order->payment_status)
                                <x-badge :tone="$order->payment_status === 'paid' ? 'success' : ($order->payment_status === 'awaiting' ? 'warning' : 'neutral')">{{ Order::PAYMENT_STATUSES[$order->payment_status] }}</x-badge>
                            @else
                                <span class="text-muted">{{ $order->stock_applied_at ? 'Ödeme beklenmiyor (cari)' : 'Onaydan sonra belirlenir' }}</span>
                            @endif
                        </dd></div>
                        @if (bccomp((string) $order->delivery_fee, '0', 2) > 0)
                            <div class="flex justify-between gap-3"><dt class="text-muted">Teslimat ücreti</dt><dd class="tabular-nums">{{ Money::format($order->delivery_fee) }}</dd></div>
                        @endif
                        @if ($order->paid_at)
                            <div class="flex justify-between gap-3"><dt class="text-muted">Ödeme zamanı</dt><dd class="tabular-nums">{{ $order->paid_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</dd></div>
                        @endif
                    </dl>
                    @if ($order->payments->isNotEmpty())
                        <ul class="mt-4 space-y-1.5 border-t border-line pt-3 text-xs">
                            @foreach ($order->payments as $p)
                                <li class="flex justify-between gap-3">
                                    <span>{{ $p->label() }} · {{ $p->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}
                                        @if ($p->error_message)<span class="block text-danger">{{ $p->error_message }}</span>@endif</span>
                                    <span @class(['shrink-0 tabular-nums', 'font-bold text-danger' => $p->status === 'refund_failed'])>{{ Money::format($p->amount) }} · {{ ['initialized' => 'Başlatıldı', 'succeeded' => 'Başarılı', 'failed' => 'Başarısız', 'refunded' => 'İade edildi', 'expired' => 'Kapatıldı', 'auto_refunded' => 'Fazla ödeme, iade edildi', 'refund_failed' => 'Fazla ödeme, İADE EDİLEMEDİ'][$p->status] ?? $p->status }}@if ($p->mode === 'sandbox') · <b class="text-danger">TEST</b>@endif</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($order->awaitingPayment() && in_array($order->status, Order::ACTIVE_FULFILMENT, true))
                        <p class="mt-4 rounded-lg bg-warning-soft px-3 py-2 text-sm text-amber-900">
                            Bayinin {{ Money::format($order->grand_total) }} ödemesi bekleniyor.
                            {{ in_array($order->payment_method, ['card', 'transfer'], true) ? 'Ödeme alınmadan sipariş hazırlanamaz.' : 'Teslimde ödemeyi alınca kaydedin; ödeme kaydedilmeden sipariş tamamlanamaz.' }}
                        </p>
                        <details class="mt-3" @if ($errors->has('paid_on') || $errors->has('method')) open @endif>
                            <summary class="cursor-pointer text-sm font-medium text-primary">Ödeme alındı (havale / nakit / POS)</summary>
                            <form method="POST" action="{{ route('business.orders.payments.store', $order) }}" class="mt-3 space-y-3" data-confirm="{{ Money::format($order->grand_total) }} ödeme alındı olarak kaydedilsin mi? Bayinin carisine tahsilat yazılacak.">
                                @csrf
                                <x-field name="method" label="Yöntem" :options="OrderPayment::MANUAL_METHODS" :value="old('method', $order->payment_method === 'transfer' ? 'transfer' : 'cash')" />
                                <x-field name="paid_on" label="Ödeme tarihi" type="date" :value="old('paid_on', now('Europe/Istanbul')->toDateString())" required />
                                <x-field name="note" label="Not" placeholder="ör. dekont no" />
                                <x-button size="sm" icon="check">Ödemeyi kaydet</x-button>
                            </form>
                        </details>
                    @endif
                </x-card>
            @endif

            <x-card title="Sevk adresi" description="Sipariş anındaki adres.">
                <div class="space-y-1 text-sm">@foreach ($order->shippingLines() as $line)<p>{{ $line }}</p>@endforeach</div>
                @if ($order->stock_applied_at && ($order->delivery_planned_on || $order->delivery_note || $order->delivery_recipient))
                    <div class="mt-4 space-y-1 border-t border-line pt-3 text-sm">
                        <p><span class="text-muted">Teslim alacak:</span> {{ $order->delivery_recipient }} · {{ $order->delivery_phone }}</p>
                        @if ($order->delivery_planned_on)<p><span class="text-muted">Planlanan teslim:</span> {{ $order->delivery_planned_on->format('d.m.Y') }}</p>@endif
                        @if ($order->delivery_note)<p><span class="text-muted">Not:</span> {{ $order->delivery_note }}</p>@endif
                        <p><span class="text-muted">Depo:</span> {{ $order->warehouse->name }}</p>
                    </div>
                @endif
                @if ($order->dealer_note)<p class="mt-4 border-t border-line pt-3 text-sm"><span class="text-muted">Bayi notu:</span> {{ $order->dealer_note }}</p>@endif
            </x-card>

            @if ($can('preparing') || $can('completed') || $can('cancelled') || $can('rejected'))
                <x-card title="İşlemler">
                    <div class="flex flex-wrap gap-2">
                        @if ($can('preparing') && $order->status === 'approved' && ! ($order->awaitingPayment() && in_array($order->payment_method, ['card', 'transfer'], true)))
                            <form method="POST" action="{{ route('business.orders.prepare', $order) }}">@csrf<x-button variant="secondary" size="sm">Hazırlanıyor</x-button></form>
                        @endif
                        @if ($can('completed') && $order->status !== 'cancel_requested' && ! $order->awaitingPayment())
                            <form method="POST" action="{{ route('business.orders.complete', $order) }}" data-confirm="Sipariş tamamlandı olarak kapatılsın mı?">@csrf<x-button size="sm" icon="check">Tamamlandı</x-button></form>
                        @endif
                    </div>
                    @if ($can('rejected'))
                        <details class="mt-4">
                            <summary class="cursor-pointer text-sm font-medium text-danger">Siparişi reddet</summary>
                            <form method="POST" action="{{ route('business.orders.reject', $order) }}" class="mt-3 space-y-3" data-confirm="Sipariş reddedilsin mi?">
                                @csrf
                                <x-field name="reason" label="Ret gerekçesi" textarea required hint="Bayiye gösterilir." />
                                <x-button variant="danger" size="sm">Reddet</x-button>
                            </form>
                        </details>
                    @endif
                    @if ($can('cancelled') && ! $canApprove && $order->status !== 'cancel_requested')
                        <details class="mt-4">
                            <summary class="cursor-pointer text-sm font-medium text-danger">Siparişi iptal et</summary>
                            <form method="POST" action="{{ route('business.orders.cancel', $order) }}" class="mt-3 space-y-3" data-confirm="Sipariş iptal edilsin mi? Düşülen stok ve cari borç ters kayıtla geri alınacak.{{ $order->payment_status === 'paid' && $order->payments->contains('driver', 'iyzico') ? ' Kart ödemesi iyzico üzerinden bayiye iade edilecek.' : '' }}">
                                @csrf
                                <x-field name="reason" label="İptal nedeni" textarea required />
                                <x-button variant="danger" size="sm">İptal et</x-button>
                            </form>
                        </details>
                    @endif
                </x-card>
            @endif

            <x-card title="Durum geçmişi">@include('partials.order-history', ['order' => $order])</x-card>
        </div>
    </div>
</x-layouts.panel>
