@php
    use App\Domain\Store\Customers\PhoneNumber;
    use App\Domain\Support\Money;
    use App\Models\Store\StoreLegalDocument;
    use App\Models\Store\StoreOrder;

    $isCod = $order->payment_method === 'cod';
    $canCancel = in_array($order->status, ['pending_payment', 'awaiting_approval', 'paid', 'preparing'], true);
    $refundsOnCancel = $order->payment_method === 'card' && $order->isSettled();
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
                                <x-field name="carrier_id" label="Kargo firması" :options="['' => 'Seçin'] + $carriers->all()" required class="w-48"
                                    :hint="$carriers->isEmpty() ? 'Önce Ayarlar → Kargo Firmaları ekranından firma ekleyin.' : null" />
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
                        <form method="POST" action="{{ route('store_admin.orders.cancel', $order) }}" class="flex flex-wrap items-end gap-3 border-t border-line pt-4"
                            data-confirm="{{ $refundsOnCancel ? 'Sipariş iptal edilsin ve '.Money::format($order->refundableAmount()).' karta iade edilsin mi? Bu işlem geri alınamaz.' : 'Sipariş iptal edilsin mi? Müşteriye e-posta gönderilir.' }}">
                            @csrf
                            <x-field name="reason" label="İptal gerekçesi" required maxlength="200" class="min-w-64 flex-1" />
                            <x-button variant="danger">{{ $refundsOnCancel ? 'İptal et ve ödemeyi iade et' : 'Siparişi iptal et' }}</x-button>
                        </form>
                        @if ($refundsOnCancel)<p class="text-xs text-muted">Ödemenin tamamı ({{ Money::format($order->refundableAmount()) }}) iyzico üzerinden karta iade edilir; iade başarısız olursa sipariş iptal edilmez.</p>@endif
                    @endif
                    @if ($order->status === 'delivered')
                        <p class="text-muted">Teslim edildi. İade talepleri müşterinin sipariş sayfasından açılır ve <a href="{{ route('store_admin.returns.index') }}" class="text-primary hover:underline">İadeler</a> bölümüne düşer.</p>
                    @endif
                    @if (in_array($order->status, ['cancelled', 'not_delivered', 'payment_failed'], true))
                        <p class="text-muted">Bu sipariş kapandı.</p>
                    @endif
                </div>
            </x-card>

            @php
                $canReturn = $order->status === 'delivered' && array_sum($returnable) > 0;
                $canRefund = $order->isSettled() && bccomp($order->refundableAmount(), '0', 2) > 0;
            @endphp
            @if ($canReturn || $canRefund)
                <x-card title="Diğer işlemler">
                    <div class="space-y-4 text-sm">
                        @if ($canReturn)
                            <details @if ($errors->hasAny(['items', 'reason', 'iban', 'account_name'])) open @endif>
                                <summary class="cursor-pointer font-medium text-primary">Müşteri adına iade talebi aç</summary>
                                <p class="mt-2 text-muted">Telefonla veya e-postayla gelen iade istekleri için. Talep "Talep alındı" olarak açılır ve müşteriye e-posta gider; iade süresi sınırı uygulanmaz.</p>
                                <form method="POST" action="{{ route('store_admin.orders.return', $order) }}" class="mt-3 space-y-3 rounded-xl bg-surface p-4">
                                    @csrf
                                    @foreach ($order->items as $item)
                                        @if (($returnable[$item->id] ?? 0) > 0)
                                            <div class="flex items-center justify-between gap-3">
                                                <label for="ai_{{ $item->id }}">{{ $item->product_name }}@if ($item->option_label) <span class="text-muted">({{ $item->option_label }})</span>@endif</label>
                                                <select id="ai_{{ $item->id }}" name="items[{{ $item->id }}]" class="field-input w-24">
                                                    @for ($q = 0; $q <= $returnable[$item->id]; $q++)<option value="{{ $q }}">{{ $q }}</option>@endfor
                                                </select>
                                            </div>
                                        @endif
                                    @endforeach
                                    @error('items')<p class="text-danger">{{ $message }}</p>@enderror
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <x-field name="reason" label="İade nedeni" :options="['' => 'Seçin'] + \App\Models\Store\StoreReturn::REASONS" required />
                                        <x-field name="note" label="Not (isteğe bağlı)" maxlength="500" />
                                    </div>
                                    @if ($isCod)
                                        <div class="grid gap-3 sm:grid-cols-2">
                                            <x-field name="iban" label="Müşterinin IBAN'ı" required maxlength="40" />
                                            <x-field name="account_name" label="Hesap sahibi" required maxlength="120" />
                                        </div>
                                    @endif
                                    <x-button variant="secondary">İade talebini aç</x-button>
                                </form>
                            </details>
                        @endif
                        @if ($canRefund)
                            <details @if ($errors->hasAny(['amount', 'method', 'refund_reason'])) open @endif>
                                <summary class="cursor-pointer font-medium text-primary">Para iadesi (ürün iadesi olmadan)</summary>
                                <p class="mt-2 text-muted">Eksik gönderim, hasarlı ürün için indirim veya gönül alma. Ürün stoğa dönmez. En fazla {{ Money::format($order->refundableAmount()) }}.</p>
                                <form method="POST" action="{{ route('store_admin.orders.refund', $order) }}" class="mt-3 space-y-3 rounded-xl bg-surface p-4"
                                    data-confirm="Para iadesi yapılsın mı? Bu işlem geri alınamaz.">
                                    @csrf
                                    <div class="grid gap-3 sm:grid-cols-3">
                                        <x-field name="amount" label="Tutar (₺)" required inputmode="decimal" />
                                        @if ($isCod)
                                            <input type="hidden" name="method" value="manual">
                                            <p class="self-end text-xs text-muted sm:col-span-2">Kapıda ödeme: tutarı müşteriye havale edip burada kaydedin.</p>
                                        @else
                                            <x-field name="method" label="Yöntem" :options="['iyzico' => 'Karta iade (iyzico)', 'manual' => 'iyzico panelinden yaptım (elle)']" class="sm:col-span-2" />
                                        @endif
                                    </div>
                                    <x-field name="refund_reason" label="Neden" required maxlength="300" placeholder="ör. 2 adet kalem eksik gönderildi" hint="Müşteriye e-postada gösterilir." />
                                    <x-button variant="secondary">Para iadesi yap</x-button>
                                </form>
                            </details>
                        @endif
                    </div>
                </x-card>
            @endif

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
                    @if (bccomp((string) $order->discount_total, '0', 2) > 0)<div class="flex justify-between text-green-800"><dt>{{ $order->discount_name }}</dt><dd class="tabular-nums">−{{ Money::format($order->discount_total) }}</dd></div>@endif
                    @if ($order->discount_name && bccomp((string) $order->discount_total, '0', 2) === 0)<div class="flex justify-between text-green-800"><dt>{{ $order->discount_name }}</dt><dd>Ücretsiz teslimat</dd></div>@endif
                    <div class="flex justify-between"><dt class="text-muted">Teslimat</dt><dd class="tabular-nums">{{ Money::format($order->shipping_fee) }}</dd></div>
                    @if (bccomp((string) $order->cod_fee, '0', 2) > 0)<div class="flex justify-between"><dt class="text-muted">Kapıda ödeme bedeli</dt><dd class="tabular-nums">{{ Money::format($order->cod_fee) }}</dd></div>@endif
                    <div class="flex justify-between border-t border-line pt-2 text-base font-bold"><dt>Toplam</dt><dd class="tabular-nums">{{ Money::format($order->total) }}</dd></div>
                    <div class="flex justify-between text-xs text-muted"><dt>Dahil KDV</dt><dd class="tabular-nums">{{ Money::format($order->vat_total) }}</dd></div>
                    @if (bccomp((string) $order->refunded_total, '0', 2) > 0)
                        <div class="flex justify-between border-t border-line pt-2 text-danger"><dt>İade edilen</dt><dd class="tabular-nums">−{{ Money::format($order->refunded_total) }}</dd></div>
                    @endif
                </dl>
            </x-card>

            @if ($order->returns->isNotEmpty() || $order->refunds->isNotEmpty())
                <x-card title="İadeler" :padding="false">
                    <ul class="divide-y divide-line text-sm">
                        @foreach ($order->returns as $return)
                            <li class="flex flex-wrap items-center justify-between gap-2 px-5 py-3">
                                <a href="{{ route('store_admin.returns.show', $return) }}" class="font-medium text-primary hover:underline">{{ $return->return_no }}</a>
                                <span class="text-muted">{{ $return->created_at->timezone('Europe/Istanbul')->format('d.m.Y') }}</span>
                                <x-badge :tone="$return->statusTone()">{{ $return->statusLabel() }}</x-badge>
                            </li>
                        @endforeach
                        @foreach ($order->refunds as $refund)
                            <li class="flex flex-wrap justify-between gap-2 px-5 py-3">
                                <span>Para iadesi · {{ $refund->method === 'iyzico' ? 'karta (iyzico)' : 'elle' }}@if ($refund->note)<span class="block text-xs text-muted">{{ $refund->note }}</span>@endif
                                    @if ($refund->error_message)<span class="block text-xs text-danger">{{ $refund->error_message }}</span>@endif</span>
                                <span class="text-right"><span @class(['font-medium tabular-nums', 'text-danger line-through' => $refund->status === 'failed'])>{{ Money::format($refund->amount) }}</span>
                                    <span class="block text-xs text-muted">{{ $refund->status === 'failed' ? 'Başarısız' : 'Yapıldı' }} · {{ $refund->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</span></span>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif

            <x-card title="Sipariş geçmişi" :padding="false">
                <ol class="divide-y divide-line text-sm">
                    @foreach ($order->events as $event)
                        <li class="flex flex-wrap justify-between gap-2 px-5 py-3">
                            <span><span class="font-medium">{{ StoreOrder::eventLabel($event->status) }}</span>@if ($event->note)<span class="block text-muted">{{ $event->note }}</span>@endif</span>
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
                @if ($order->tracking_number)<p class="mt-2 text-sm">Kargo: {{ $order->carrier }} ·
                    @if ($order->tracking_url)<a href="{{ $order->tracking_url }}" target="_blank" rel="noopener noreferrer" class="font-mono text-primary hover:underline">{{ $order->tracking_number }}</a>@else<span class="font-mono">{{ $order->tracking_number }}</span>@endif</p>@endif
                @if ($order->customer_note)<p class="mt-3 rounded-lg bg-warning-soft p-3 text-sm text-amber-900">Not: {{ $order->customer_note }}</p>@endif
            </x-card>
            <x-card title="Fatura">
                <p class="text-sm">{{ $order->billing_type === 'corporate' ? 'Kurumsal' : 'Bireysel' }} · {{ $order->billing_name }}</p>
                @if ($order->billing_tax_number)<p class="text-sm text-muted">{{ $order->billing_tax_office ? $order->billing_tax_office.' / ' : 'TCKN: ' }}{{ $order->billing_tax_number }}</p>@endif
                <p class="mt-1 text-sm text-slate-600">{{ $order->billing_address }}</p>
                <div class="mt-4 border-t border-line pt-4">
                    @if ($order->invoice)
                        <p class="text-sm">Fatura no: <span class="font-medium">{{ $order->invoice->invoice_no }}</span> · {{ $order->invoice->issued_on->format('d.m.Y') }}</p>
                        @if ($order->invoice->file_path)<a href="{{ route('store_admin.orders.invoice', $order) }}" class="mt-1 inline-block text-sm text-primary hover:underline">PDF dosyasını indir</a>@endif
                    @endif
                    @foreach ($order->creditNotes as $note)
                        <p class="mt-2 text-sm">İade faturası: <a href="{{ route('store_admin.orders.credit-note', [$order, $note->id]) }}" class="font-medium text-primary hover:underline">{{ $note->number }}</a>
                            · {{ $note->issued_on->format('d.m.Y') }}@if ($note->amount) · {{ Money::format($note->amount) }}@endif</p>
                    @endforeach
                    @if ($order->invoice && (bccomp((string) $order->refunded_total, '0', 2) > 0 || in_array($order->status, ['cancelled', 'not_delivered'], true)))
                        <details class="mt-3" @if ($errors->hasAny(['number'])) open @endif>
                            <summary class="cursor-pointer text-sm font-medium text-primary">İade faturası ekle</summary>
                            <form method="POST" action="{{ route('store_admin.orders.credit-note.store', $order) }}" enctype="multipart/form-data" class="mt-3 space-y-3">
                                @csrf
                                <x-field name="number" label="İade faturası no" required maxlength="30" />
                                <x-field name="issued_on" label="Tarih" type="date" required :value="now('Europe/Istanbul')->toDateString()" />
                                <x-field name="amount" label="Tutar (₺, isteğe bağlı)" inputmode="decimal" />
                                @if ($order->returns->isNotEmpty())
                                    <x-field name="return_id" label="İlgili iade" :options="['' => '—'] + $order->returns->pluck('return_no', 'id')->all()" />
                                @endif
                                <x-field name="file" label="PDF" type="file" accept="application/pdf" required hint="e-Arşiv bağlanana kadar elle eklenir." />
                                <x-button>Kaydet</x-button>
                            </form>
                        </details>
                    @endif
                    @if (! in_array($order->status, ['pending_payment', 'payment_failed'], true))
                        <details @class(['mt-3' => $order->invoice]) @if (! $order->invoice || $errors->hasAny(['invoice_no', 'issued_on', 'file'])) open @endif>
                            <summary class="cursor-pointer text-sm font-medium text-primary">{{ $order->invoice ? 'Faturayı değiştir' : 'Fatura ekle' }}</summary>
                            <form method="POST" action="{{ route('store_admin.orders.invoice.store', $order) }}" enctype="multipart/form-data" class="mt-3 space-y-3">
                                @csrf
                                <x-field name="invoice_no" label="Fatura numarası" required maxlength="30" :value="$order->invoice?->invoice_no" />
                                <x-field name="issued_on" label="Fatura tarihi" type="date" required :value="$order->invoice?->issued_on?->toDateString() ?? now('Europe/Istanbul')->toDateString()" />
                                <x-field name="file" label="PDF" type="file" accept="application/pdf" :required="! $order->invoice" :hint="$order->invoice ? 'Boş bırakılırsa mevcut PDF korunur.' : 'En fazla 5 MB. Müşteri sipariş sayfasından indirir.'" />
                                <x-button>Kaydet</x-button>
                            </form>
                        </details>
                    @endif
                </div>
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
