@php
    use App\Domain\Store\Customers\PhoneNumber;
    use App\Domain\Store\Payments\BankTransfer;
    use App\Domain\Support\Money;
    use App\Models\Store\StoreOrder;
    use App\Models\Store\StoreReturn;

    $steps = match ($order->payment_method) {
        'cod' => ['awaiting_approval' => 'Alındı', 'preparing' => 'Hazırlanıyor', 'shipped' => 'Yolda', 'delivered' => 'Teslim edildi'],
        'transfer' => ['awaiting_transfer' => 'Ödeme bekleniyor', 'preparing' => 'Hazırlanıyor', 'shipped' => 'Yolda', 'delivered' => 'Teslim edildi'],
        default => ['paid' => 'Ödendi', 'preparing' => 'Hazırlanıyor', 'shipped' => 'Yolda', 'delivered' => 'Teslim edildi'],
    };
    $current = array_search($order->status === 'pending_payment' ? 'paid' : $order->status, array_keys($steps), true);
    $closed = in_array($order->status, ['cancelled', 'payment_failed', 'not_delivered'], true);
@endphp
<x-layouts.store :title="'Sipariş '.$order->order_no" :noindex="true">
    <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
        @if (session('order_placed') || (request('odeme') === 'sonuc' && ! in_array($order->status, ['pending_payment', 'payment_failed', 'cancelled'], true)))
            <div class="mb-6 rounded-2xl border border-green-200 bg-success-soft p-6 text-green-900">
                <p class="text-lg font-semibold">Siparişiniz alındı, teşekkür ederiz!</p>
                <p class="mt-1 text-sm">
                    @if ($order->status === 'awaiting_approval') Kısa süre içinde sizi arayarak siparişinizi teyit edeceğiz. Ödemeyi teslimatta yapacaksınız.
                    @elseif ($order->payment_method === 'cod') Siparişinizi hazırlamaya başladık. Ödemeyi teslimatta yapacaksınız.
                    @elseif ($order->status === 'awaiting_transfer') Ödemenizi aşağıdaki hesaplardan birine gönderdiğinizde siparişinizi hazırlamaya başlayacağız.
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

        @if ($order->status === 'awaiting_transfer')
            <section class="mt-6 rounded-2xl border border-amber-200 bg-warning-soft p-5 text-sm text-amber-900">
                <h2 class="font-semibold">Havale / EFT ile ödeme</h2>
                <p class="mt-1">Lütfen <span class="font-semibold">{{ Money::format($order->total) }}</span> tutarını aşağıdaki hesaplardan birine, açıklamaya
                    <span class="font-semibold">{{ $order->order_no }}</span> yazarak <span class="font-semibold">{{ BankTransfer::deadline($order)->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</span> tarihine kadar gönderin.
                    Ödemeniz hesabımıza geçince siparişinizi hazırlamaya başlarız; bu süre içinde ödeme gelmezse sipariş iptal edilir.</p>
                <ul class="mt-4 space-y-2.5">
                    @foreach (BankTransfer::accounts() as $account)
                        <x-store.bank-account :account="$account" />
                    @endforeach
                </ul>
            </section>
        @endif

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
            <x-store.tracking-card :order="$order" class="mt-6" id="kargo-takip" />
        @elseif ($order->carrier && $order->delivery_method === 'cargo')
            <p class="mt-4 text-sm text-muted">Kargo firması: <span class="font-medium text-ink">{{ $order->carrier }}</span> · Takip numarası kargoya verilince burada görünür.</p>
        @endif

        @error('return')<p role="alert" class="mt-6 rounded-xl border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</p>@enderror
        @error('cancel')<p role="alert" class="mt-6 rounded-xl border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</p>@enderror

        @if ($order->cancel_requested_at && $order->status !== 'cancelled')
            <section class="mt-6 rounded-2xl border border-amber-200 bg-warning-soft p-5 text-sm text-amber-900">
                <h2 class="font-semibold">İptal talebiniz alındı</h2>
                <p class="mt-1">{{ $order->cancel_requested_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }} tarihli talebiniz işleniyor. Ödemeniz
                    <span class="font-mono">{{ trim(chunk_split((string) $order->cancel_refund_iban, 4, ' ')) }}</span> hesabına gönderilip siparişiniz iptal edilecek.</p>
            </section>
        @elseif ($order->customerCancellable())
            @php $transferPaid = $order->payment_method === 'transfer' && bccomp($order->refundableAmount(), '0', 2) > 0; @endphp
            <details class="mt-6 rounded-2xl border border-line p-5 text-sm" @if ($errors->has('reason') && old('cancel_form')) open @endif>
                <summary class="cursor-pointer font-semibold text-ink">Siparişi iptal et</summary>
                <p class="mt-2 text-muted">Siparişiniz kargoya verilmeden iptal edebilirsiniz.
                    @if ($order->payment_method === 'card' && $order->isSettled()) Ödemeniz ({{ Money::format($order->refundableAmount()) }}) kartınıza otomatik iade edilir.
                    @elseif ($transferPaid) Ödemeniz havaleyle alındığı için tutar yazdığınız hesaba gönderilir; iptal bu işlemden sonra tamamlanır.
                    @else Ödemeniz alınmadığı için siparişiniz hemen iptal edilir. @endif</p>
                <form method="POST" action="{{ $cancelUrl }}" class="mt-4 space-y-4" data-confirm="{{ $order->order_no }} numaralı sipariş iptal edilsin mi?">
                    @csrf
                    <x-store.bot-trap />
                    <input type="hidden" name="cancel_form" value="1">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-field name="reason" label="İptal nedeni" :options="['' => 'Seçin'] + StoreOrder::CANCEL_REASONS" required />
                        <x-field name="note" label="Açıklama (isteğe bağlı)" maxlength="200" />
                    </div>
                    @if ($transferPaid)
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-field name="iban" label="İade için IBAN" required maxlength="40" placeholder="TR00 0000 0000 0000 0000 0000 00" />
                            <x-field name="account_name" label="Hesap sahibi" required maxlength="120" />
                        </div>
                    @endif
                    <x-button variant="danger">{{ $transferPaid ? 'İptal talebi gönder' : 'Siparişi iptal et' }}</x-button>
                </form>
            </details>
        @endif

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
                    <div class="space-y-4 border-t border-line px-5 py-4">
                        <h3 class="text-sm font-semibold">İade talepleriniz</h3>
                        @foreach ($order->returns as $return)
                            @php
                                $stepKeys = array_keys(StoreReturn::STEPS);
                                $at = array_search($return->status, $stepKeys, true);
                            @endphp
                            <div class="rounded-xl border border-line p-4 text-sm">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <span class="font-medium">{{ $return->return_no }} <span class="font-normal text-muted">· {{ $return->created_at->timezone('Europe/Istanbul')->format('d.m.Y') }} · {{ $return->items->sum('quantity') }} ürün · {{ StoreReturn::REASONS[$return->reason] ?? '' }}</span></span>
                                    <x-badge :tone="$return->statusTone()">{{ $return->statusLabel() }}</x-badge>
                                </div>
                                @if ($at !== false)
                                    <ol class="mt-3 grid grid-cols-5 gap-1 text-center text-[11px]">
                                        @foreach (StoreReturn::STEPS as $key => $label)
                                            <li><span @class(['block h-1.5 rounded-full', 'bg-primary' => $loop->index <= $at, 'bg-slate-200' => $loop->index > $at])></span>
                                                <span @class(['mt-1 block', 'font-semibold text-ink' => $loop->index === $at, 'text-muted' => $loop->index !== $at])>{{ $label }}</span></li>
                                        @endforeach
                                    </ol>
                                @endif
                                @if (in_array($return->status, ['rejected', 'expired'], true) && $return->admin_note)<p class="mt-2 text-xs text-muted">Gerekçe: {{ $return->admin_note }}</p>@endif
                                @if ($return->refund_amount)<p class="mt-2 text-xs text-muted">İade edilen: {{ Money::format($return->refund_amount) }}</p>@endif
                                @if ($return->photos->isNotEmpty())
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @foreach (\App\Domain\Store\Returns\ReturnService::photoUrls($return) as $url)
                                            <a href="{{ $url }}" target="_blank" rel="noopener" class="block size-14 overflow-hidden rounded-lg ring-1 ring-line"><img src="{{ $url }}" alt="İade fotoğrafı" class="size-full object-cover"></a>
                                        @endforeach
                                    </div>
                                @endif
                                @if ($return->status === 'approved')
                                    <div class="mt-3 rounded-lg bg-warning-soft p-3 text-amber-900">
                                        <p>{{ \App\Domain\Store\Returns\ReturnService::shippingInstructions($return) }}</p>
                                        @if ($return->admin_note)<p class="mt-1">Not: {{ $return->admin_note }}</p>@endif
                                    </div>
                                    <form method="POST" action="{{ $shipUrl($return) }}" class="mt-3 grid gap-3 sm:grid-cols-3 sm:items-end">
                                        @csrf
                                        <x-field name="cargo_company" label="Kargo firması" required maxlength="40" placeholder="ör. Aras Kargo" />
                                        <x-field name="cargo_tracking" label="Takip numarası" required maxlength="60" />
                                        @if ($return->sellerFault())
                                            <x-field name="cargo_fee" label="Ödediğiniz kargo ücreti (₺)" inputmode="decimal" maxlength="12" hint="İade tutarına eklenir." />
                                        @endif
                                        <div><x-button>Kargoya verdim</x-button></div>
                                    </form>
                                @elseif ($return->cargo_tracking)
                                    <p class="mt-2 text-xs text-muted">İade kargosu: {{ $return->cargo_company }} · {{ $return->cargo_tracking }}@if ($return->cargo_fee !== null) · ödenen kargo {{ Money::format($return->cargo_fee) }}@endif</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($returnRefusal)
                    <p class="border-t border-line px-5 py-4 text-sm text-muted">{{ $returnRefusal }}</p>
                @endif
                @if ($canReturn)
                    <details class="border-t border-line px-5 py-4" @if ($errors->any()) open @endif>
                        <summary class="cursor-pointer text-sm font-semibold text-primary">İade talebi oluştur</summary>
                        <p class="mt-2 text-xs text-muted">Teslimden sonraki {{ \App\Domain\Store\Returns\ReturnService::returnDays() }} gün içinde (son gün {{ $returnDeadline->format('d.m.Y') }}) cayma hakkınızı kullanabilirsiniz. Talebiniz onaylandığında ürünleri nereye göndereceğinizi e-postayla bildireceğiz.</p>
                        <form method="POST" action="{{ $returnUrl }}" class="mt-4 space-y-4" enctype="multipart/form-data">
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
                            <div>
                                <label for="iade_foto" class="mb-1.5 block text-sm font-medium">Ürün fotoğrafları</label>
                                <input id="iade_foto" type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp" class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium">
                                <p class="mt-1 text-xs text-muted">En fazla {{ StoreReturn::MAX_PHOTOS }} fotoğraf (JPG, PNG; her biri en fazla 10 MB). Ürün hasarlı, yanlış veya açıklamadaki gibi değilse en az 1 fotoğraf zorunludur.</p>
                                @error('photos')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@enderror
                                @if ($errors->has('photos.*'))<p class="mt-1.5 text-sm text-danger">{{ collect($errors->get('photos.*'))->flatten()->first() }}</p>@endif
                            </div>
                            <p class="rounded-lg bg-surface p-3 text-xs text-muted">Talebiniz onaylanınca ürünü kargo şubesinden <b>gönderici ödemeli</b> olarak gönderirsiniz. "Vazgeçtim" iadelerinde kargo ücreti size aittir; ürün hasarlı, yanlış veya açıklamadaki gibi değilse ödediğiniz kargo ücretini iade tutarına ekleriz.</p>
                            @if ($order->refundsToBank())
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <x-field name="iban" label="İade için IBAN" required maxlength="40" placeholder="TR00 0000 0000 0000 0000 0000 00" hint="İade tutarı bu hesaba havale edilir." />
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
                    @foreach ($order->creditNotes as $note)
                        <a href="{{ $creditNoteUrl($note->id) }}" class="mt-1 flex items-center gap-1.5 font-medium text-primary hover:underline"><x-icon name="download" class="size-4" /> İade faturası ({{ $note->number }})</a>
                    @endforeach
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
