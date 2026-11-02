{{-- Siparişin iade talepleri ve (tamamlanmış siparişte) yeni talep formu: $order, $returnRequests, $requestable, $canRequestReturn --}}
@php
    use App\Domain\Inventory\Quantity;
    use App\Domain\Support\Money;
    use App\Models\OrderReturnRequest;

@endphp
@if ($returnRequests->isNotEmpty() || $canRequestReturn)
    <section id="iade" class="mt-6 space-y-4 print:hidden">
        @error('return')<div role="alert" class="rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror

        @foreach ($returnRequests as $r)
            <x-card :title="'İade talebi '.$r->request_no" :description="$r->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i').' · '.$r->reasonLabel()">
                <div class="space-y-3 text-sm">
                    <x-badge :tone="$r->statusColor()">{{ $r->statusLabel() }}</x-badge>
                    <ul class="divide-y divide-line rounded-lg border border-line">
                        @foreach ($r->items as $item)
                            <li class="flex justify-between gap-3 px-3 py-2"><span>{{ $item->orderItem->name }}</span><span class="tabular-nums">{{ Quantity::format($item->quantity) }} {{ $item->orderItem->unit_name }}</span></li>
                        @endforeach
                    </ul>
                    @if ($r->dealer_note)<p><span class="text-muted">Açıklamanız:</span> {{ $r->dealer_note }}</p>@endif
                    @if ($r->photos->isNotEmpty())
                        <div class="flex flex-wrap gap-2">
                            @foreach ($r->photos as $photo)
                                <a href="{{ route('dealer.returns.photo', [$r, $photo]) }}" target="_blank" rel="noopener"><img src="{{ route('dealer.returns.photo', [$r, $photo]) }}" alt="İade fotoğrafı" class="size-16 rounded-lg object-cover ring-1 ring-line" loading="lazy"></a>
                            @endforeach
                        </div>
                    @endif
                    @if ($r->status === 'approved')
                        <p class="rounded-lg bg-primary-soft px-3 py-2 text-ink">Talebiniz onaylandı. Ürünleri toptancınıza gönderin; ulaştığında carinize alacak yazılır.@if ($r->business_note)<br><span class="font-medium">Toptancı notu:</span> {{ $r->business_note }}@endif</p>
                    @elseif ($r->status === 'rejected')
                        <p class="rounded-lg bg-danger-soft px-3 py-2 text-red-900"><span class="font-medium">Ret nedeni:</span> {{ $r->reject_reason }}</p>
                    @elseif ($r->status === 'received' && $r->orderReturn)
                        <p class="rounded-lg bg-success-soft px-3 py-2 text-ink">Ürünler teslim alındı; carinize <b>{{ Money::format($r->orderReturn->grand_total) }}</b> alacak yazıldı. <a href="{{ route('dealer.account.statement') }}" class="text-primary hover:underline">Cari hesap</a></p>
                    @endif
                    @if ($r->status === 'requested')
                        <form method="POST" action="{{ route('dealer.returns.cancel', $r) }}" data-confirm="İade talebi geri çekilsin mi?">
                            @csrf
                            <button class="text-sm font-medium text-muted hover:text-red-700">Talebi geri çek</button>
                        </form>
                    @endif
                </div>
            </x-card>
        @endforeach

        @if ($canRequestReturn)
            <details class="rounded-2xl border border-line bg-white shadow-xs" @if (request('iade') || $errors->has('return') || $errors->has('reason') || $errors->has('photos') || $errors->has('photos.*')) open @endif>
                <summary class="cursor-pointer px-5 py-4 text-sm font-semibold text-ink">İade talebi oluştur</summary>
                <form method="POST" action="{{ route('dealer.returns.store', $order) }}" enctype="multipart/form-data" class="space-y-4 border-t border-line px-5 py-4">
                    @csrf
                    <p class="text-sm text-muted">İade etmek istediğiniz ürünlerin miktarını girin. Toptancınız talebi onaylayınca ürünleri gönderirsiniz; ürünler ulaştığında tutar carinize alacak olarak yazılır.</p>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead><tr class="text-left text-muted"><th class="py-2">Ürün</th><th class="py-2 text-right">İade edilebilir</th><th class="py-2 text-right">İade miktarı</th></tr></thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($order->items as $item)
                                    @continue(bccomp($requestable[$item->id] ?? '0', '0', 3) <= 0)
                                    <tr>
                                        <td class="py-2">{{ $item->name }}</td>
                                        <td class="py-2 text-right tabular-nums">{{ Quantity::format($requestable[$item->id]) }} {{ $item->unit_name }}</td>
                                        <td class="py-2 text-right"><input type="text" inputmode="decimal" name="qty[{{ $item->id }}]" value="{{ old('qty.'.$item->id) }}" class="field-input w-24 text-right" aria-label="{{ $item->name }} iade miktarı"></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <x-field name="reason" label="İade nedeni" required :options="['' => 'Seçin'] + OrderReturnRequest::REASONS" />
                    <x-field name="note" label="Açıklama" textarea maxlength="1000" hint="“Diğer” seçtiyseniz zorunlu." />
                    <div>
                        <label for="f_photos" class="mb-1.5 block text-sm font-medium text-ink">Ürün fotoğrafları</label>
                        <input id="f_photos" type="file" name="photos[]" accept="image/jpeg,image/png" multiple class="block w-full text-sm">
                        <p class="mt-1 text-xs text-muted">En fazla {{ OrderReturnRequest::MAX_PHOTOS }} fotoğraf (JPG / PNG, her biri 5 MB). Hasarlı, kusurlu veya yanlış ürün iadesinde en az bir fotoğraf zorunludur.</p>
                        @error('photos')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                        @error('photos.*')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                    </div>
                    <x-button>İade talebini gönder</x-button>
                </form>
            </details>
        @endif
    </section>
@endif
