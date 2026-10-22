@php
    use App\Domain\Store\Customers\PhoneNumber;
    use App\Domain\Support\Money;
    use App\Models\Store\StoreOrder;
    use App\Models\Store\StoreReturn;

    $steps = $order->payment_method === 'cod'
        ? ['awaiting_approval' => 'Alındı', 'preparing' => 'Hazırlanıyor', 'shipped' => 'Yolda', 'delivered' => 'Teslim edildi']
        : ['paid' => 'Ödendi', 'preparing' => 'Hazırlanıyor', 'shipped' => 'Yolda', 'delivered' => 'Teslim edildi'];
    $current = array_search($order->status === 'pending_payment' ? 'paid' : $order->status, array_keys($steps), true);
    $closed = in_array($order->status, ['cancelled', 'payment_failed', 'not_delivered'], true);
@endphp
<x-layouts.store :title="'Sipariş '.$order->order_no" :noindex="true">
    <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
        @if (session('order_placed'))
            <div class="mb-6 rounded-2xl border border-green-200 bg-success-soft p-6 text-green-900">
                <p class="text-lg font-semibold">Siparişiniz alındı, teşekkür ederiz!</p>
                <p class="mt-1 text-sm">
                    @if ($order->status === 'awaiting_approval') Kısa süre içinde sizi arayarak siparişinizi teyit edeceğiz. Ödemeyi teslimatta yapacaksınız.
                    @elseif ($order->payment_method === 'cod') Siparişinizi hazırlamaya başladık. Ödemeyi teslimatta yapacaksınız.
                    @else Ödemeniz alındı, siparişinizi hazırlamaya başladık. @endif
                    Sipariş bilgileri {{ $order->email }} adresine gönderildi.
                </p>
            </div>
        @endif

        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm text-muted">Sipariş no</p>
                <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ $order->order_no }}</h1>
                <p class="mt-1 text-sm text-muted">{{ $order->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</p>
            </div>
            <x-badge :tone="$order->statusTone()" class="px-3! py-1! text-sm!">{{ $order->statusLabel() }}</x-badge>
        </div>

        @if ($order->status === 'pending_payment')
            <p class="mt-6 rounded-xl border border-amber-200 bg-warning-soft px-4 py-3 text-sm text-amber-900">Ödemeniz henüz onaylanmadı. Ödeme sayfasından ayrıldıysanız birkaç dakika içinde sonuçlanacaktır.</p>
        @elseif ($closed)
            <p class="mt-6 rounded-xl border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">
                {{ $order->status === 'payment_failed' ? 'Ödeme alınamadığı için sipariş tamamlanmadı. Ürünler sepetinizde duruyor; yeniden deneyebilirsiniz.' : 'Bu sipariş iptal edildi.' }}
                @if ($order->cancel_reason) <span class="block mt-1">Gerekçe: {{ $order->cancel_reason }}</span>@endif
            </p>
        @else
            <ol class="mt-8 grid grid-cols-4 gap-2 text-center text-xs sm:text-sm">
                @foreach (array_values($steps) as $i => $label)
                    <li>
                        <span @class(['mx-auto block h-1.5 rounded-full', 'bg-primary' => $current !== false && $i <= $current, 'bg-slate-200' => $current === false || $i > $current])></span>
                        <span @class(['mt-2 block', 'font-semibold text-ink' => $i === $current, 'text-muted' => $i !== $current])>{{ $label }}</span>
                    </li>
                @endforeach
            </ol>
        @endif
        @if ($order->tracking_number)
            <p class="mt-4 text-sm">Kargo: <span class="font-medium">{{ $order->carrier }}</span> · Takip no: <span class="font-mono font-medium">{{ $order->tracking_number }}</span>
                @if ($order->tracking_url)<a href="{{ $order->tracking_url }}" target="_blank" rel="noopener noreferrer" class="ml-2 font-medium text-primary hover:underline">Kargoyu takip et →</a>@endif</p>
        @endif

        @error('return')<p role="alert" class="mt-6 rounded-xl border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</p>@enderror

        <div class="mt-8 grid gap-6 lg:grid-cols-3">
            <section class="rounded-2xl border border-line lg:col-span-2">
                <h2 class="border-b border-line px-5 py-4 font-semibold">Ürünler</h2>
                <ul class="divide-y divide-line">
                    @foreach ($order->items as $item)
                        <li class="flex justify-between gap-4 px-5 py-3 text-sm">
                            <span><span class="font-medium">{{ $item->product_name }}</span>
                                <span class="block text-xs text-muted">@if ($item->option_label){{ $item->option_label }} · @endif{{ $item->quantity }} × {{ Money::format($item->unit_price) }}</span></span>
                            <span class="tabular-nums">{{ Money::format($item->line_total) }}</span>
                        </li>
                    @endforeach
                </ul>
                <dl class="space-y-2 border-t border-line px-5 py-4 text-sm">
                    <div class="flex justify-between"><dt class="text-muted">Ürünler</dt><dd class="tabular-nums">{{ Money::format($order->subtotal) }}</dd></div>
                    @if (bccomp((string) $order->discount_total, '0', 2) > 0)<div class="flex justify-between text-green-800"><dt>{{ $order->discount_name }}</dt><dd class="tabular-nums">−{{ Money::format($order->discount_total) }}</dd></div>@endif
                    <div class="flex justify-between"><dt class="text-muted">Teslimat ({{ StoreOrder::DELIVERY_METHODS[$order->delivery_method] }})</dt><dd class="tabular-nums">{{ bccomp((string) $order->shipping_fee, '0', 2) > 0 ? Money::format($order->shipping_fee) : 'Ücretsiz' }}</dd></div>
                    @if (bccomp((string) $order->cod_fee, '0', 2) > 0)<div class="flex justify-between"><dt class="text-muted">Kapıda ödeme bedeli</dt><dd class="tabular-nums">{{ Money::format($order->cod_fee) }}</dd></div>@endif
                    <div class="flex justify-between border-t border-line pt-3 text-base font-bold"><dt>Toplam</dt><dd class="tabular-nums">{{ Money::format($order->total) }}</dd></div>
                    <div class="flex justify-between text-xs text-muted"><dt>Tutara dahil KDV</dt><dd class="tabular-nums">{{ Money::format($order->vat_total) }}</dd></div>
                    @if (bccomp((string) $order->refunded_total, '0', 2) > 0)
                        <div class="flex justify-between border-t border-line pt-3 font-medium text-green-800"><dt>İade edilen</dt><dd class="tabular-nums">{{ Money::format($order->refunded_total) }}</dd></div>
                    @endif
                </dl>

                @if ($order->returns->isNotEmpty())
                    <div class="border-t border-line px-5 py-4">
                        <h3 class="text-sm font-semibold">İade talepleriniz</h3>
                        <ul class="mt-2 space-y-2 text-sm">
                            @foreach ($order->returns as $return)
                                <li class="flex flex-wrap items-center justify-between gap-2">
                                    <span>{{ $return->return_no }} <span class="text-muted">· {{ $return->created_at->timezone('Europe/Istanbul')->format('d.m.Y') }} · {{ $return->items->sum('quantity') }} ürün</span>
                                        @if ($return->status === 'rejected' && $return->admin_note)<span class="block text-xs text-muted">Gerekçe: {{ $return->admin_note }}</span>@endif
                                        @if ($return->refund_amount)<span class="block text-xs text-muted">İade edilen: {{ Money::format($return->refund_amount) }}</span>@endif</span>
                                    <x-badge :tone="$return->statusTone()">{{ $return->statusLabel() }}</x-badge>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($canReturn)
                    <details class="border-t border-line px-5 py-4" @if ($errors->any()) open @endif>
                        <summary class="cursor-pointer text-sm font-semibold text-primary">İade talebi oluştur</summary>
                        <p class="mt-2 text-xs text-muted">Teslimden sonraki {{ \App\Domain\Store\Returns\ReturnService::returnDays() }} gün içinde (son gün {{ $returnDeadline->format('d.m.Y') }}) cayma hakkınızı kullanabilirsiniz. Talebiniz onaylandığında ürünleri nereye göndereceğinizi e-postayla bildireceğiz.</p>
                        <form method="POST" action="{{ $returnUrl }}" class="mt-4 space-y-4">
                            @csrf
                            <x-store.bot-trap />
                            <fieldset>
                                <legend class="text-sm font-medium">İade edilecek ürünler</legend>
                                <ul class="mt-2 space-y-2">
                                    @foreach ($order->items as $item)
                                        @if (($returnable[$item->id] ?? 0) > 0)
                                            <li class="flex items-center justify-between gap-3 text-sm">
                                                <label for="iade_{{ $item->id }}">{{ $item->product_name }}@if ($item->option_label) <span class="text-muted">({{ $item->option_label }})</span>@endif</label>
                                                <select id="iade_{{ $item->id }}" name="items[{{ $item->id }}]" class="field-input w-24">
                                                    @for ($q = 0; $q <= $returnable[$item->id]; $q++)
                                                        <option value="{{ $q }}" @selected((int) old('items.'.$item->id, 0) === $q)>{{ $q }}</option>
                                                    @endfor
                                                </select>
                                            </li>
                                        @endif
                                    @endforeach
                                </ul>
                                @error('items')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@enderror
                            </fieldset>
                            <x-field name="reason" label="İade nedeni" :options="['' => 'Seçin'] + StoreReturn::REASONS" required />
                            <x-field name="note" label="Açıklama (isteğe bağlı)" textarea maxlength="500" />
                            @if ($order->payment_method === 'cod')
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <x-field name="iban" label="İade için IBAN" required maxlength="40" placeholder="TR00 0000 0000 0000 0000 0000 00" hint="Kapıda ödemenin iadesi bu hesaba havale edilir." />
                                    <x-field name="account_name" label="Hesap sahibi" required maxlength="120" />
                                </div>
                            @else
                                <p class="text-xs text-muted">Onaylanan iadenin tutarı ödemede kullandığınız karta iade edilir.</p>
                            @endif
                            <x-button>İade talebini gönder</x-button>
                        </form>
                    </details>
                @endif
            </section>

            <div class="space-y-6">
                <section class="rounded-2xl border border-line p-5 text-sm">
                    <h2 class="font-semibold">Teslimat adresi</h2>
                    <p class="mt-2">{{ $order->shipping_name }} · {{ PhoneNumber::format($order->shipping_phone) }}</p>
                    <p class="mt-1 text-slate-600">{{ $order->shippingOneLine() }}</p>
                </section>
                <section class="rounded-2xl border border-line p-5 text-sm">
                    <h2 class="font-semibold">Ödeme</h2>
                    <p class="mt-2">{{ StoreOrder::PAYMENT_METHODS[$order->payment_method] }} · <span class="text-muted">{{ StoreOrder::PAYMENT_STATUSES[$order->payment_status] ?? $order->payment_status }}</span></p>
                    <h2 class="mt-4 font-semibold">Fatura</h2>
                    <p class="mt-2">{{ $order->billing_name }}@if ($order->billing_type === 'corporate') · {{ $order->billing_tax_office }} / {{ $order->billing_tax_number }}@endif</p>
                    @if ($order->invoice?->file_path)
                        <a href="{{ $invoiceUrl }}" class="mt-2 inline-flex items-center gap-1.5 font-medium text-primary hover:underline"><x-icon name="download" class="size-4" /> Faturayı indir ({{ $order->invoice->invoice_no }})</a>
                    @endif
                </section>
                @if ($order->consents->isNotEmpty())
                    <section class="rounded-2xl border border-line p-5 text-sm">
                        <h2 class="font-semibold">Onayladığınız metinler</h2>
                        @foreach ($order->consents as $consent)
                            <details class="mt-2">
                                <summary class="cursor-pointer text-primary">{{ \App\Models\Store\StoreLegalDocument::KEYS[$consent->document_key] ?? $consent->document_key }} (sürüm {{ $consent->version }})</summary>
                                <div class="mt-2 max-h-72 space-y-2 overflow-y-auto rounded-lg bg-surface p-3 text-xs leading-relaxed [&_h2]:font-semibold [&_li]:ml-4 [&_ul]:list-disc">{{ \App\Domain\Store\Orders\LegalDocuments::html($consent->body) }}</div>
                            </details>
                        @endforeach
                    </section>
                @endif
            </div>
        </div>

        @auth('customer')
            <a href="{{ route('store.orders') }}" class="mt-8 inline-block text-sm font-medium text-primary hover:underline">← Tüm siparişlerim</a>
        @endauth
    </div>
</x-layouts.store>
