@php
    use App\Domain\Store\Customers\PhoneNumber;
    use App\Domain\Support\Money;
    use App\Models\Store\StoreOrder;
    use App\Models\Store\StoreReturn;

    $order = $return->order;
    $isCard = $order->payment_method === 'card';
@endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" :title="'İade '.$return->return_no">
    <x-page-header :title="'İade '.$return->return_no" :subtitle="$return->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i').' · '.(StoreReturn::REASONS[$return->reason] ?? $return->reason)">
        <x-slot:breadcrumb><a href="{{ route('store_admin.returns.index') }}" class="hover:underline">İadeler</a></x-slot:breadcrumb>
        <x-badge :tone="$return->statusTone()" class="px-3! py-1! text-sm!">{{ $return->statusLabel() }}</x-badge>
    </x-page-header>

    @error('return')<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <x-card title="İşlem">
                <div class="space-y-4 text-sm">
                    @switch($return->status)
                        @case('requested')
                            <form method="POST" action="{{ route('store_admin.returns.approve', $return) }}" class="space-y-3 rounded-xl bg-surface p-4">
                                @csrf
                                <x-field name="note" label="Müşteriye not (isteğe bağlı)" textarea maxlength="500" hint="Onay e-postasında iade adresi, kargo ücreti kuralı ve kargolama süresiyle birlikte gönderilir." />
                                <x-button icon="check">İadeyi onayla</x-button>
                            </form>
                            <form method="POST" action="{{ route('store_admin.returns.reject', $return) }}" class="flex flex-wrap items-end gap-3" data-confirm="İade talebi reddedilsin mi? Müşteriye gerekçeyle birlikte e-posta gönderilir.">
                                @csrf
                                <x-field name="reason" label="Ret gerekçesi" required maxlength="500" class="min-w-64 flex-1" />
                                <x-button variant="secondary">Reddet</x-button>
                            </form>
                            @break
                        @case('approved')
                        @case('shipped')
                            @if ($return->status === 'approved')
                                <p class="rounded-lg bg-surface p-3 text-muted">Müşterinin ürünü kargolaması bekleniyor (en geç {{ $return->shipDeadline()?->timezone('Europe/Istanbul')->format('d.m.Y') }}; kargo bilgisi girilmezse {{ \App\Models\Store\StoreReturn::REMIND_DAYS }}. günde hatırlatma gider, {{ \App\Models\Store\StoreReturn::EXPIRE_DAYS }}. günde talep kapanır). Ürün kargo bilgisi girilmeden elinize ulaştıysa yine teslim alabilirsiniz.</p>
                            @endif
                            <form method="POST" action="{{ route('store_admin.returns.receive-refund', $return) }}" class="space-y-3 rounded-xl bg-surface p-4"
                                data-confirm="{{ $isCard ? 'Ürünler teslim alınsın ve tutar iyzico üzerinden karta iade edilsin mi? Bu işlem geri alınamaz.' : 'Ürünler teslim alınsın ve havaleyle yaptığınız para iadesi kaydedilsin mi?' }}">
                                @csrf
                                <p class="font-medium text-ink">Teslim alındı, iade et</p>
                                <p class="text-muted">Ürünleri kontrol edin: satılabilir adetler stoğa döner (hasarlı adetleri 0 yapın). Para iadesi aynı anda yapılır.</p>
                                @foreach ($return->items as $item)
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <span>{{ $item->orderItem->product_name }}@if ($item->orderItem->option_label) <span class="text-muted">({{ $item->orderItem->option_label }})</span>@endif · {{ $item->quantity }} adet</span>
                                        <x-field :name="'resellable['.$item->id.']'" label="Stoğa dönecek adet" type="number" min="0" :max="$item->quantity" :value="$item->quantity" class="w-40" />
                                    </div>
                                @endforeach
                                @unless ($isCard)
                                    <p class="rounded-lg bg-white p-3 ring-1 ring-line">Tutarı bu hesaba gönderin: <span class="font-mono">{{ trim(chunk_split((string) $return->refund_iban, 4, ' ')) }}</span><span class="block text-muted">{{ $return->refund_account_name }}</span></p>
                                @endunless
                                <div class="flex flex-wrap items-end gap-3">
                                    <x-field name="amount" label="İade tutarı (₺)" required inputmode="decimal" :value="number_format((float) $maxRefund, 2, ',', '')" class="w-48"
                                        :hint="'Hesaplanan: '.Money::format($maxRefund).($return->sellerFault() && $return->cargo_fee !== null ? ' (müşterinin ödediği '.Money::format($return->cargo_fee).' iade kargosu dahil)' : '').'. Gerekirse düşürebilirsiniz.'" />
                                    <x-field name="note" :label="$isCard ? 'Açıklama (isteğe bağlı)' : 'Havale bilgisi'" :required="! $isCard" maxlength="300" class="min-w-64 flex-1" :placeholder="$isCard ? null : 'ör. 21.10.2026 EFT, dekont no 12345'" />
                                </div>
                                <x-button icon="check-circle">{{ $isCard ? 'Teslim alındı, karta iade et' : 'Teslim alındı, iadeyi kaydet' }}</x-button>
                            </form>
                            <details class="text-sm">
                                <summary class="cursor-pointer text-muted">Yalnızca teslim al (para iadesini sonra yap)</summary>
                                <form method="POST" action="{{ route('store_admin.returns.receive', $return) }}" class="mt-3 space-y-3" data-confirm="Ürünler teslim alındı olarak işaretlensin mi? Sağlam adetler stoğa eklenir.">
                                    @csrf
                                    @foreach ($return->items as $item)
                                        <div class="flex flex-wrap items-center justify-between gap-3">
                                            <span>{{ $item->orderItem->product_name }} · {{ $item->quantity }} adet</span>
                                            <x-field :name="'resellable['.$item->id.']'" label="Stoğa dönecek adet" type="number" min="0" :max="$item->quantity" :value="$item->quantity" class="w-40" />
                                        </div>
                                    @endforeach
                                    <x-button variant="secondary" size="sm">Ürünler teslim alındı</x-button>
                                </form>
                            </details>
                            @break
                        @case('received')
                            <form method="POST" action="{{ route('store_admin.returns.refund', $return) }}" class="space-y-3 rounded-xl bg-surface p-4"
                                data-confirm="{{ $isCard ? 'Tutar iyzico üzerinden müşterinin kartına iade edilsin mi? Bu işlem geri alınamaz.' : 'Havaleyi yaptıysanız para iadesini kaydedin. Devam edilsin mi?' }}">
                                @csrf
                                @if ($isCard)
                                    <p class="text-muted">Tutar iyzico üzerinden müşterinin kartına iade edilir.</p>
                                @else
                                    <p class="text-muted">Kapıda ödemeli sipariş: tutarı aşağıdaki hesaba havale/EFT ile gönderin, ardından burada kaydedin.</p>
                                    <p class="rounded-lg bg-white p-3 ring-1 ring-line"><span class="font-mono">{{ trim(chunk_split((string) $return->refund_iban, 4, ' ')) }}</span><span class="block text-muted">{{ $return->refund_account_name }}</span></p>
                                @endif
                                <div class="flex flex-wrap items-end gap-3">
                                    <x-field name="amount" label="İade tutarı (₺)" required inputmode="decimal" :value="number_format((float) $maxRefund, 2, ',', '')" class="w-48"
                                        :hint="'En fazla '.Money::format($maxRefund).'. Kargo ve kapıda ödeme bedeli, siparişteki tüm ürünler iade edildiğinde dahildir.'" />
                                    <x-field name="note" :label="$isCard ? 'Açıklama (isteğe bağlı)' : 'Havale bilgisi'" :required="! $isCard" maxlength="300" class="min-w-64 flex-1" :placeholder="$isCard ? null : 'ör. 21.10.2026 EFT, dekont no 12345'" />
                                </div>
                                <x-button icon="check">{{ $isCard ? 'Karta iade et' : 'Para iadesini kaydet' }}</x-button>
                            </form>
                            @break
                        @default
                            <p class="text-muted">Bu iade talebi kapandı.@if ($return->refund_amount) İade edilen tutar: <span class="font-medium text-ink">{{ Money::format($return->refund_amount) }}</span>.@endif</p>
                    @endswitch
                </div>
            </x-card>

            @if ($return->photos->isNotEmpty())
                <x-card title="Müşterinin fotoğrafları">
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                        @foreach ($return->photos as $photo)
                            <a href="{{ route('store_admin.returns.photo', [$return, $photo->id]) }}" target="_blank" rel="noopener" class="block aspect-square overflow-hidden rounded-lg ring-1 ring-line">
                                <img src="{{ route('store_admin.returns.photo', [$return, $photo->id]) }}" alt="İade fotoğrafı {{ $loop->iteration }}" class="size-full object-cover">
                            </a>
                        @endforeach
                    </div>
                </x-card>
            @endif

            <x-card title="İade edilen ürünler" :padding="false">
                <table class="table-base">
                    <thead><tr><th>Ürün</th><th class="text-right">Sipariş adedi</th><th class="text-right">İade adedi</th><th class="text-right">Stoğa dönen</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($return->items as $item)
                            <tr>
                                <td>{{ $item->orderItem->product_name }}<p class="text-xs text-muted">@if ($item->orderItem->option_label){{ $item->orderItem->option_label }} · @endif{{ $item->orderItem->sku }} · {{ Money::format($item->orderItem->unit_price) }}</p></td>
                                <td class="text-right tabular-nums">{{ $item->orderItem->quantity }}</td>
                                <td class="text-right font-medium tabular-nums">{{ $item->quantity }}</td>
                                <td class="text-right tabular-nums">{{ $return->received_at ? $item->restocked_quantity : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-card>

            @if ($return->refunds->isNotEmpty())
                <x-card title="Para iadesi kayıtları" :padding="false">
                    <ul class="divide-y divide-line text-sm">
                        @foreach ($return->refunds as $refund)
                            <li class="flex flex-wrap justify-between gap-2 px-5 py-3">
                                <span>{{ $refund->method === 'iyzico' ? 'Karta (iyzico)' : 'Elle' }}@if ($refund->note)<span class="block text-xs text-muted">{{ $refund->note }}</span>@endif
                                    @if ($refund->error_message)<span class="block text-xs text-danger">{{ $refund->error_message }}</span>@endif</span>
                                <span class="text-right"><span @class(['font-medium tabular-nums', 'text-danger line-through' => $refund->status === 'failed'])>{{ Money::format($refund->amount) }}</span>
                                    <span class="block text-xs text-muted">{{ $refund->status === 'failed' ? 'Başarısız' : 'Yapıldı' }} · {{ $refund->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</span></span>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif
        </div>

        <div class="space-y-6">
            <x-card title="Sipariş">
                <a href="{{ route('store_admin.orders.show', $order) }}" class="font-medium text-primary hover:underline">{{ $order->order_no }}</a>
                <p class="mt-1 text-sm">{{ StoreOrder::PAYMENT_METHODS[$order->payment_method] }} · {{ Money::format($order->total) }}</p>
                <p class="text-sm text-muted">Teslim: {{ $order->delivered_at?->timezone('Europe/Istanbul')->format('d.m.Y') ?? '—' }}</p>
                @if (bccomp((string) $order->refunded_total, '0', 2) > 0)<p class="text-sm text-danger">Siparişte iade edilen: {{ Money::format($order->refunded_total) }}</p>@endif
            </x-card>
            <x-card title="Müşteri">
                <p class="text-sm font-medium">{{ $order->shipping_name }}</p>
                <p class="text-sm"><a href="tel:{{ $order->phone }}" class="text-primary hover:underline">{{ PhoneNumber::format($order->phone) }}</a> · <a href="mailto:{{ $order->email }}" class="break-all text-primary hover:underline">{{ $order->email }}</a></p>
                @if ($return->customer_note)<p class="mt-3 rounded-lg bg-warning-soft p-3 text-sm text-amber-900">Müşteri notu: {{ $return->customer_note }}</p>@endif
                <p class="mt-3 text-sm"><span class="text-muted">İade kargosu:</span>
                    @if ($return->cargo_tracking){{ $return->cargo_company }} · <span class="font-mono">{{ $return->cargo_tracking }}</span>
                        <span class="block text-xs text-muted">{{ $return->shipped_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}@if ($return->cargo_fee !== null) · müşterinin ödediği: {{ Money::format($return->cargo_fee) }}@endif</span>
                    @else <span class="text-muted">{{ $return->status === 'approved' ? 'henüz girilmedi' : '—' }}</span>@endif</p>
                <p class="mt-1 text-xs text-muted">{{ $return->sellerFault() ? 'Neden mağazadan kaynaklı: iade kargosu müşteriye ödenir.' : 'Vazgeçme iadesi: iade kargosu müşteriye aittir.' }}</p>
                @if ($return->admin_note)<p class="mt-3 rounded-lg bg-surface p-3 text-sm">Yönetici notu: {{ $return->admin_note }}</p>@endif
            </x-card>
        </div>
    </div>
</x-layouts.panel>
