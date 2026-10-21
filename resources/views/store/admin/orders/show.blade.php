@php
    use App\Domain\Store\Customers\PhoneNumber;
    use App\Domain\Support\Money;
    use App\Models\Store\StoreLegalDocument;
    use App\Models\Store\StoreOrder;

    $isCod = $order->payment_method === 'cod';
    $canCancel = in_array($order->status, ['pending_payment', 'awaiting_approval', 'preparing'], true) && $order->payment_status !== 'paid';
@endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" :title="'Sipariş '.$order->order_no">
    <x-page-header :title="'Sipariş '.$order->order_no" :subtitle="$order->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i').' · '.StoreOrder::PAYMENT_METHODS[$order->payment_method].' · '.StoreOrder::DELIVERY_METHODS[$order->delivery_method]">
        <x-slot:breadcrumb><a href="{{ route('store_admin.orders.index') }}" class="hover:underline">Siparişler</a></x-slot:breadcrumb>
        <x-badge :tone="$order->statusTone()" class="px-3! py-1! text-sm!">{{ $order->statusLabel() }}</x-badge>
    </x-page-header>

    @error('order')<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            {{-- İşlemler: yalnızca bu durumda yapılabilecekler gösterilir --}}
            <x-card title="İşlem">
                <div class="space-y-4 text-sm">
                    @switch($order->status)
                        @case('awaiting_approval')
                            <p class="text-muted">Kapıda ödemeli ilk sipariş. Müşteriyi arayıp ({{ PhoneNumber::format($order->phone) }}) teyit edin.</p>
                            <form method="POST" action="{{ route('store_admin.orders.approve', $order) }}">@csrf<x-button icon="check">Teyit edildi, onayla</x-button></form>
                            @break
                        @case('pending_payment')
                            <p class="text-muted">Müşterinin ödeme yapması bekleniyor. Ödeme gelmezse sipariş 30 dakika içinde kendiliğinden kapanır ve stok serbest kalır.</p>
                            @break
                        @case('paid')
                            <form method="POST" action="{{ route('store_admin.orders.prepare', $order) }}">@csrf<x-button>Hazırlanıyor olarak işaretle</x-button></form>
                            @break
                    @endswitch

                    @if (in_array($order->status, ['paid', 'preparing'], true))
                        <form method="POST" action="{{ route('store_admin.orders.ship', $order) }}" class="flex flex-wrap items-end gap-3 rounded-xl bg-surface p-4">
                            @csrf
                            @if ($order->delivery_method === 'cargo')
                                <x-field name="carrier" label="Kargo firması" required maxlength="60" class="w-48" />
                                <x-field name="tracking_number" label="Takip numarası" required maxlength="60" class="w-56" />
                                <x-button icon="arrow-right">Kargoya verildi</x-button>
                            @else
                                <p class="flex-1 text-muted">Mağaza teslimatı: ürünler teslimat için yola çıktığında işaretleyin.</p>
                                <x-button icon="arrow-right">Yola çıktı</x-button>
                            @endif
                        </form>
                    @endif

                    @if ($order->status === 'shipped')
                        <form method="POST" action="{{ route('store_admin.orders.deliver', $order) }}" class="flex flex-wrap items-end gap-3 rounded-xl bg-surface p-4">
                            @csrf
                            @if ($isCod)
                                <x-field name="collected_method" label="Tahsilat" :options="['' => 'Seçin', 'cash' => 'Nakit', 'card' => 'Kapıda kart']" required class="w-48" />
                            @endif
                            <x-button icon="check-circle">Teslim edildi</x-button>
                        </form>
                        @if ($isCod)
                            <form method="POST" action="{{ route('store_admin.orders.not-delivered', $order) }}" class="flex flex-wrap items-end gap-3" data-confirm="Teslim alınmadı olarak işaretlensin mi? Ürünler stoğa döner ve bu telefon kapıda ödeme engel listesine eklenir.">
                                @csrf
                                <x-field name="reason" label="Teslim alınmadı — gerekçe" required maxlength="200" class="min-w-64 flex-1" placeholder="ör. Kapıda teslim almadı, ulaşılamadı" />
                                <x-button variant="secondary">Teslim alınmadı</x-button>
                            </form>
                        @endif
                    @endif

                    @if ($canCancel)
                        <form method="POST" action="{{ route('store_admin.orders.cancel', $order) }}" class="flex flex-wrap items-end gap-3 border-t border-line pt-4" data-confirm="Sipariş iptal edilsin mi? Müşteriye e-posta gönderilir.">
                            @csrf
                            <x-field name="reason" label="İptal gerekçesi" required maxlength="200" class="min-w-64 flex-1" />
                            <x-button variant="danger">Siparişi iptal et</x-button>
                        </form>
                    @elseif ($order->payment_status === 'paid' && ! in_array($order->status, ['shipped', 'delivered'], true))
                        <p class="border-t border-line pt-4 text-xs text-muted">Kartla ödenmiş siparişin iptali ödeme iadesiyle birlikte yapılır; iade işlemleri bir sonraki aşamada eklenecek.</p>
                    @endif
                    @if (in_array($order->status, ['delivered', 'cancelled', 'not_delivered', 'payment_failed'], true))
                        <p class="text-muted">Bu sipariş kapandı.</p>
                    @endif
                </div>
            </x-card>

            <x-card title="Ürünler" :padding="false">
                <table class="table-base">
                    <thead><tr><th>Ürün</th><th class="text-right">Adet</th><th class="text-right">Birim</th><th class="text-right">Tutar</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($order->items as $item)
                            <tr>
                                <td>{{ $item->product_name }}<p class="text-xs text-muted">@if ($item->option_label){{ $item->option_label }} · @endif{{ $item->sku }} · KDV %{{ (int) $item->vat_rate }}</p></td>
                                <td class="text-right tabular-nums">{{ $item->quantity }}</td>
                                <td class="text-right tabular-nums">{{ Money::format($item->unit_price) }}</td>
                                <td class="text-right font-medium tabular-nums">{{ Money::format($item->line_total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <dl class="space-y-1.5 border-t border-line p-5 text-sm">
                    <div class="flex justify-between"><dt class="text-muted">Ürünler</dt><dd class="tabular-nums">{{ Money::format($order->subtotal) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Teslimat</dt><dd class="tabular-nums">{{ Money::format($order->shipping_fee) }}</dd></div>
                    @if (bccomp((string) $order->cod_fee, '0', 2) > 0)<div class="flex justify-between"><dt class="text-muted">Kapıda ödeme bedeli</dt><dd class="tabular-nums">{{ Money::format($order->cod_fee) }}</dd></div>@endif
                    <div class="flex justify-between border-t border-line pt-2 text-base font-bold"><dt>Toplam</dt><dd class="tabular-nums">{{ Money::format($order->total) }}</dd></div>
                    <div class="flex justify-between text-xs text-muted"><dt>Dahil KDV</dt><dd class="tabular-nums">{{ Money::format($order->vat_total) }}</dd></div>
                </dl>
            </x-card>

            <x-card title="Sipariş geçmişi" :padding="false">
                <ol class="divide-y divide-line text-sm">
                    @foreach ($order->events as $event)
                        <li class="flex flex-wrap justify-between gap-2 px-5 py-3">
                            <span><span class="font-medium">{{ StoreOrder::STATUSES[$event->status] ?? $event->status }}</span>@if ($event->note)<span class="block text-muted">{{ $event->note }}</span>@endif</span>
                            <span class="text-xs text-muted">{{ \Illuminate\Support\Carbon::parse($event->created_at)->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}
                                · {{ match ($event->actor_type) { 'store_admin' => $actors[$event->actor_id] ?? 'Yönetici', 'customer' => 'Müşteri', null => 'Sistem', default => $event->actor_type } }}</span>
                        </li>
                    @endforeach
                </ol>
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Müşteri">
                <p class="text-sm font-medium">{{ $order->shipping_name }}</p>
                <p class="text-sm"><a href="tel:{{ $order->phone }}" class="text-primary hover:underline">{{ PhoneNumber::format($order->phone) }}</a> · <a href="mailto:{{ $order->email }}" class="break-all text-primary hover:underline">{{ $order->email }}</a></p>
                <p class="mt-1 text-xs text-muted">{{ $order->customer ? 'Üye' : 'Misafir' }}</p>
                @if ($blocked)
                    <div class="mt-3 rounded-lg bg-danger-soft p-3 text-xs text-red-900">
                        Bu telefon kapıda ödeme engel listesinde. {{ $blocked->reason }}
                        <form method="POST" action="{{ route('store_admin.cod-blocks.destroy', $blocked) }}" class="mt-2" data-confirm="Engel kaldırılsın mı?">@csrf @method('DELETE')<button class="font-medium underline">Engeli kaldır</button></form>
                    </div>
                @endif
            </x-card>
            <x-card title="Teslimat adresi">
                <p class="text-sm">{{ $order->shipping_name }} · {{ PhoneNumber::format($order->shipping_phone) }}</p>
                <p class="mt-1 text-sm text-slate-600">{{ $order->shippingOneLine() }}</p>
                @if ($order->tracking_number)<p class="mt-2 text-sm">Kargo: {{ $order->carrier }} · <span class="font-mono">{{ $order->tracking_number }}</span></p>@endif
                @if ($order->customer_note)<p class="mt-3 rounded-lg bg-warning-soft p-3 text-sm text-amber-900">Not: {{ $order->customer_note }}</p>@endif
            </x-card>
            <x-card title="Fatura">
                <p class="text-sm">{{ $order->billing_type === 'corporate' ? 'Kurumsal' : 'Bireysel' }} · {{ $order->billing_name }}</p>
                @if ($order->billing_tax_number)<p class="text-sm text-muted">{{ $order->billing_tax_office ? $order->billing_tax_office.' / ' : 'TCKN: ' }}{{ $order->billing_tax_number }}</p>@endif
                <p class="mt-1 text-sm text-slate-600">{{ $order->billing_address }}</p>
            </x-card>
            <x-card title="Ödeme">
                <p class="text-sm">{{ StoreOrder::PAYMENT_METHODS[$order->payment_method] }} · <span class="font-medium">{{ StoreOrder::PAYMENT_STATUSES[$order->payment_status] ?? $order->payment_status }}</span>
                    @if ($order->collected_method) ({{ $order->collected_method === 'cash' ? 'nakit' : 'kapıda kart' }})@endif</p>
                @foreach ($order->payments->where('driver', 'iyzico') as $payment)
                    <p class="mt-2 text-xs text-muted">iyzico #{{ $payment->provider_payment_id ?? '—' }} · {{ $payment->status }}
                        @if ($payment->card_last_four) · {{ $payment->card_family }} **** {{ $payment->card_last_four }}@endif
                        @if ($payment->installment && $payment->installment > 1) · {{ $payment->installment }} taksit @endif
                        @if ($payment->error_message)<span class="block text-danger">{{ $payment->error_message }}</span>@endif</p>
                @endforeach
            </x-card>
            <x-card title="Onaylanan yasal metinler">
                @forelse ($order->consents as $consent)
                    <p class="text-sm">{{ StoreLegalDocument::KEYS[$consent->document_key] ?? $consent->document_key }} — sürüm {{ $consent->version }}</p>
                    <p class="text-xs text-muted">{{ $consent->accepted_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }} · IP {{ $consent->ip }}</p>
                @empty
                    <p class="text-sm text-muted">—</p>
                @endforelse
            </x-card>
        </div>
    </div>
</x-layouts.panel>
