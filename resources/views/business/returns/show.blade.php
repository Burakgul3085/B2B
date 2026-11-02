@php
    use App\Domain\Inventory\Quantity;
    use App\Domain\Support\Money;
@endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Business" :title="$r->request_no">
    <x-page-header :title="'İade talebi '.$r->request_no" :subtitle="$r->relationship->dealer->legal_name.' · '.$r->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i')">
        <x-slot:breadcrumb><a href="{{ route('business.returns.index') }}" class="hover:underline">İade Talepleri</a></x-slot:breadcrumb>
        <x-badge :tone="$r->statusColor()">{{ $r->statusLabel() }}</x-badge>
        <x-button variant="secondary" size="sm" icon="file" :href="route('business.orders.show', $r->order)">Sipariş {{ $r->order->order_no }}</x-button>
    </x-page-header>

    @error('return')<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            @if (in_array($r->status, ['requested', 'approved'], true))
                <x-card title="Ürünleri teslim al" description="Gelen miktarın tutarı sipariş fiyatıyla hesaplanır: sipariş kartla ödendiyse iyzico'dan bayinin kartına iade edilir, diğer ödemelerde bayinin carisine alacak yazılır. Siparişin tamamı gelirse sipariş iptal edilmiş olur. Yalnızca tekrar satılabilir miktar depoya girer; hasarlı ürün stoğa eklenmez." :padding="false">
                    <form method="POST" action="{{ route('business.returns.receive', $r) }}" data-confirm="Ürünler teslim alınsın mı? İade tutarı (kartla ödendiyse karta) ödenecek ve stok girişi yazılacak; bu işlem geri alınamaz.">
                        @csrf
                        <div class="overflow-x-auto">
                            <table class="table-base">
                                <thead><tr><th>Ürün</th><th class="text-right">Talep</th><th class="text-right">Teslim alınan</th><th class="text-right">Tekrar satılabilir</th></tr></thead>
                                <tbody class="divide-y divide-line">
                                    @foreach ($r->items as $item)
                                        @php $q = Quantity::format($item->quantity); @endphp
                                        <tr>
                                            <td>{{ $item->orderItem->name }}<p class="text-xs text-muted">{{ $item->orderItem->sku }}</p></td>
                                            <td class="text-right tabular-nums">{{ $q }} {{ $item->orderItem->unit_name }}</td>
                                            <td class="text-right"><input type="text" inputmode="decimal" name="received[{{ $item->id }}]" value="{{ old('received.'.$item->id, $q) }}" class="field-input w-24 text-right" aria-label="{{ $item->orderItem->name }} teslim alınan"></td>
                                            <td class="text-right"><input type="text" inputmode="decimal" name="resellable[{{ $item->id }}]" value="{{ old('resellable.'.$item->id, $q) }}" class="field-input w-24 text-right" aria-label="{{ $item->orderItem->name }} tekrar satılabilir"></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="flex flex-wrap items-end gap-3 border-t border-line p-4">
                            <x-field name="note" label="Not (isteğe bağlı)" maxlength="500" class="min-w-64 flex-1" />
                            <x-button icon="check">Teslim al ve iadeyi öde</x-button>
                        </div>
                    </form>
                </x-card>
            @else
                <x-card title="Ürünler" :padding="false">
                    <div class="overflow-x-auto">
                        <table class="table-base">
                            <thead><tr><th>Ürün</th><th class="text-right">Talep</th>@if ($r->orderReturn)<th class="text-right">Teslim alınan</th><th class="text-right">Depoya giren</th>@endif</tr></thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($r->items as $item)
                                    @php $got = $r->orderReturn?->items->firstWhere('order_item_id', $item->order_item_id); @endphp
                                    <tr>
                                        <td>{{ $item->orderItem->name }}</td>
                                        <td class="text-right tabular-nums">{{ Quantity::format($item->quantity) }} {{ $item->orderItem->unit_name }}</td>
                                        @if ($r->orderReturn)
                                            <td class="text-right tabular-nums">{{ $got ? Quantity::format($got->quantity) : '0' }}</td>
                                            <td class="text-right tabular-nums">{{ $got ? Quantity::format($got->restocked_quantity ?? $got->quantity) : '0' }}</td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($r->orderReturn)
                        @php $toCard = (string) $r->orderReturn->refunded_to_card; $toAccount = bcsub((string) $r->orderReturn->grand_total, $toCard, 2); @endphp
                        <p class="border-t border-line p-4 text-sm">İade tutarı <b>{{ Money::format($r->orderReturn->grand_total) }}</b> ({{ $r->received_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}):
                            @if (bccomp($toCard, '0', 2) > 0) {{ Money::format($toCard) }} iyzico'dan karta iade edildi.@endif
                            @if (bccomp($toAccount, '0', 2) > 0) {{ Money::format($toAccount) }} bayinin carisine alacak yazıldı.@endif
                        </p>
                    @endif
                </x-card>
            @endif

            @if ($r->photos->isNotEmpty())
                <x-card title="Fotoğraflar">
                    <div class="flex flex-wrap gap-3">
                        @foreach ($r->photos as $photo)
                            <a href="{{ route('business.returns.photo', [$r, $photo]) }}" target="_blank" rel="noopener"><img src="{{ route('business.returns.photo', [$r, $photo]) }}" alt="İade fotoğrafı" class="size-32 rounded-lg object-cover ring-1 ring-line" loading="lazy"></a>
                        @endforeach
                    </div>
                </x-card>
            @endif
        </div>

        <div class="space-y-6">
            <x-card title="Talep">
                <dl class="space-y-2 text-sm">
                    <div><dt class="text-muted">Neden</dt><dd class="font-medium">{{ $r->reasonLabel() }}</dd></div>
                    @if ($r->dealer_note)<div><dt class="text-muted">Bayi açıklaması</dt><dd>{{ $r->dealer_note }}</dd></div>@endif
                    @if ($r->business_note)<div><dt class="text-muted">Bayiye notunuz</dt><dd class="whitespace-pre-line">{{ $r->business_note }}</dd></div>@endif
                    @if ($r->reject_reason)<div><dt class="text-muted">Ret gerekçesi</dt><dd>{{ $r->reject_reason }}</dd></div>@endif
                    @if ($r->approved_at)<div><dt class="text-muted">Onay</dt><dd class="tabular-nums">{{ $r->approved_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</dd></div>@endif
                    @if ($r->cancelled_at)<div><dt class="text-muted">Bayi geri çekti</dt><dd class="tabular-nums">{{ $r->cancelled_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</dd></div>@endif
                </dl>
            </x-card>

            @if ($r->status === 'requested')
                <x-card title="Onayla" description="Bayiye ürünleri nasıl göndereceğini yazın (ör. hangi gün, hangi araçla).">
                    <form method="POST" action="{{ route('business.returns.approve', $r) }}" class="space-y-3">
                        @csrf
                        <x-field name="business_note" label="Bayiye not (isteğe bağlı)" textarea maxlength="1000" />
                        <x-button size="sm" icon="check">Talebi onayla</x-button>
                    </form>
                </x-card>
            @endif
            @if (in_array($r->status, ['requested', 'approved'], true))
                <details class="rounded-2xl border border-line bg-white p-5 shadow-xs" @if ($errors->has('reject_reason')) open @endif>
                    <summary class="cursor-pointer text-sm font-medium text-danger">Talebi reddet</summary>
                    <form method="POST" action="{{ route('business.returns.reject', $r) }}" class="mt-3 space-y-3" data-confirm="İade talebi reddedilsin mi?">
                        @csrf
                        <x-field name="reject_reason" label="Ret gerekçesi" textarea required maxlength="1000" hint="Bayiye gösterilir." />
                        <x-button variant="danger" size="sm">Reddet</x-button>
                    </form>
                </details>
            @endif
        </div>
    </div>
</x-layouts.panel>
