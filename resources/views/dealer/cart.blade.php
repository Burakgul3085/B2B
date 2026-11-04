@php use App\Domain\Inventory\Quantity; use App\Domain\Support\Money; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Dealer" title="Sepet">
    <x-page-header title="Sepet" subtitle="Fiyat ve stok güncel kurallarla hesaplanır. Sepet stok ayırmaz." />

    @error('cart')<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror

    @if ($summary['lines'] === [])
        <x-card>
            <x-empty-state icon="package" title="Sepetiniz boş" text="Katalogdan ürün ekleyerek siparişinizi oluşturun.">
                <x-button :href="route('dealer.catalog')">Kataloğa git</x-button>
            </x-empty-state>
        </x-card>
    @else
        <div class="grid gap-6 lg:grid-cols-3">
            <x-card title="Ürünler" class="lg:col-span-2" :padding="false">
                <ul class="divide-y divide-line">
                    @foreach ($summary['lines'] as $line)
                        @php $p = $line['product']; @endphp
                        <li @class(['flex flex-wrap gap-4 px-5 py-4', 'bg-danger-soft/40' => $line['issue']])>
                            <div class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-line bg-white p-1">
                                @if ($p->primaryImage)<img src="{{ \App\Domain\Catalog\ProductImageUrl::for($p->primaryImage, \App\Domain\Catalog\ProductImageUrl::THUMB) }}" alt="" class="max-h-full max-w-full object-contain mix-blend-multiply">@else<x-icon name="image" class="size-5 text-slate-300" />@endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('dealer.catalog.show', $p) }}" class="font-medium hover:text-primary">{{ $p->name }}</a>
                                <p class="text-xs text-muted">{{ $p->sku }} · {{ $p->saleRuleLabel() }}@if ($cases = $p->casesLabel($line['item']->quantity)) · <b class="text-ink">{{ $cases }}</b>@endif</p>
                                @if ($line['price'])
                                    <p class="mt-1 text-sm tabular-nums">{{ Money::formatUnit($line['price']->basePrice) }} / {{ $p->unit->name }}
                                        @if ($line['price']->hasDiscount())<span class="text-muted line-through"> {{ Money::formatUnit($line['price']->wasPrice) }}</span><span class="text-success"> · %{{ $line['price']->savingPercent() }} indirim</span>@endif
                                    </p>
                                @endif
                                @if ($line['issue'])<p class="mt-1 text-sm font-medium text-danger">{{ $line['issue'] }}</p>@endif
                            </div>
                            <div class="flex items-start gap-3">
                                <form method="POST" action="{{ route('dealer.cart.update', $line['item']) }}" class="flex items-center gap-1">
                                    @csrf @method('PUT')
                                    <input name="quantity" value="{{ Quantity::format($line['item']->quantity) }}" inputmode="decimal" class="field-input w-20 py-1 text-right" aria-label="{{ $p->name }} miktarı">
                                    <button class="text-xs text-primary hover:underline">Güncelle</button>
                                </form>
                                <div class="w-24 text-right">
                                    <p class="font-semibold tabular-nums">{{ $line['amounts'] ? Money::format($line['amounts']['line_net']) : '—' }}</p>
                                    <p class="text-xs text-muted">KDV hariç</p>
                                </div>
                                <form method="POST" action="{{ route('dealer.cart.remove', $line['item']) }}">@csrf @method('DELETE')
                                    <button class="rounded p-1 text-muted hover:text-danger" aria-label="{{ $p->name }} sepetten çıkar"><x-icon name="x" class="size-4" /></button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-card>

            <div class="space-y-6">
                <x-card title="Sipariş özeti">
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-muted">Ara toplam</dt><dd class="tabular-nums">{{ Money::format($summary['totals']['subtotal']) }}</dd></div>
                        @php
                            $promo = $summary['promotion'];
                            $promoAmount = $promo['applied']?->amount ?? '0.00';
                            $lineDiscount = bcsub($summary['totals']['discount_total'], $promoAmount, 2);
                        @endphp
                        @if (bccomp($lineDiscount, '0', 2) > 0)
                            <div class="flex justify-between text-success"><dt>İndirim</dt><dd class="tabular-nums">−{{ Money::format($lineDiscount) }}</dd></div>
                        @endif
                        @if ($promo['applied'])
                            <div class="flex justify-between gap-3 text-success"><dt>{{ $promo['applied']->promotion->isCoupon() ? 'Kupon '.$promo['applied']->promotion->code : 'Kampanya' }} <span class="block text-xs text-muted">{{ $promo['applied']->promotion->name }}</span></dt><dd class="shrink-0 tabular-nums">−{{ Money::format($promoAmount) }}</dd></div>
                        @endif
                        <div class="flex justify-between"><dt class="text-muted">KDV</dt><dd class="tabular-nums">{{ Money::format($summary['totals']['vat_total']) }}</dd></div>
                        @if (bccomp($deliveryFee, '0', 2) > 0)
                            <div class="flex justify-between"><dt class="text-muted">Teslimat ücreti</dt><dd class="tabular-nums">{{ Money::format($deliveryFee) }}</dd></div>
                        @endif
                        <div class="flex justify-between border-t border-line pt-2 text-base font-semibold"><dt>Genel toplam</dt><dd class="tabular-nums">{{ Money::format(bcadd((string) $summary['totals']['grand_total'], $deliveryFee, 2)) }}</dd></div>
                    </dl>
                </x-card>

                <x-card title="Kupon kodu">
                    @if ($promo['code'])
                        <div class="flex items-center justify-between gap-3 rounded-lg border border-line px-3 py-2 text-sm">
                            <span><b class="font-mono">{{ $promo['code'] }}</b>
                                @if ($promo['error'])<span class="block text-danger">{{ $promo['error'] }}</span>
                                @else<span class="block text-success">Uygulandı: {{ $promo['applied']->promotion->summary() }}</span>@endif
                            </span>
                            <form method="POST" action="{{ route('dealer.cart.promotion.remove') }}">@csrf @method('DELETE')
                                <button class="text-xs text-primary hover:underline">Kaldır</button>
                            </form>
                        </div>
                    @else
                        <form method="POST" action="{{ route('dealer.cart.promotion') }}" class="flex gap-2">
                            @csrf
                            <input name="code" value="{{ old('code') }}" maxlength="40" autocomplete="off" class="field-input min-w-0 flex-1 uppercase" placeholder="Kodu girin" aria-label="Kupon kodu">
                            <x-button variant="secondary">Uygula</x-button>
                        </form>
                        @error('code')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@enderror
                        @if ($promo['applied'])<p class="mt-2 text-sm text-success">Kampanya uygulandı: {{ $promo['applied']->promotion->summary() }}</p>@endif
                    @endif
                    @if ($campaigns->isNotEmpty())
                        <div class="mt-4 border-t border-line pt-3">
                            <p class="mb-1.5 text-sm font-medium">Size özel kampanyalar</p>
                            <ul class="space-y-1.5 text-sm">
                                @foreach ($campaigns as $campaign)
                                    <li><b>{{ $campaign->name }}</b>: {{ $campaign->summary() }}@if ($campaign->ends_at)<span class="text-muted"> · {{ $campaign->ends_at->timezone('Europe/Istanbul')->format('d.m.Y') }} tarihine kadar</span>@endif
                                        @if ($campaign->description)<span class="block text-muted">{{ $campaign->description }}</span>@endif</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    <a href="{{ route('dealer.promotions.index') }}" class="mt-3 inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline">Tüm kampanya ve kuponlar <x-icon name="arrow-right" class="size-3.5" /></a>
                    @if ($promo['next'])
                        <p class="mt-3 rounded-lg bg-primary-soft/60 px-3 py-2 text-sm">{{ Money::format($promo['next']['missing']) }} (KDV hariç) daha ekleyin, <b>{{ $promo['next']['promotion']->name }}</b> kampanyasından yararlanın: {{ $promo['next']['promotion']->summary() }}.</p>
                    @endif
                </x-card>

                <x-card title="Sevk ve gönder">
                    @if ($addresses->isEmpty())
                        <p class="text-sm text-danger">Kayıtlı sevk adresiniz yok.</p>
                        <x-button class="mt-3" variant="secondary" :href="route('dealer.addresses.index')">Adres ekle</x-button>
                    @else
                        <form method="POST" action="{{ route('dealer.cart.submit') }}" class="space-y-4" x-data="{ sending: false }" @submit="sending = true">
                            @csrf
                            <input type="hidden" name="submit_token" value="{{ $submitToken }}">
                            <fieldset class="space-y-2">
                                <legend class="mb-1.5 text-sm font-medium">Sevk adresi</legend>
                                @foreach ($addresses as $address)
                                    <label class="flex cursor-pointer gap-3 rounded-lg border border-line p-3 text-sm has-checked:border-primary has-checked:bg-primary-soft/40">
                                        <input type="radio" name="address_id" value="{{ $address->id }}" @checked(old('address_id', $addresses->firstWhere('is_default', true)?->id ?? $addresses->first()->id) == $address->id) class="mt-0.5">
                                        <span><b>{{ $address->title }}</b><span class="block text-muted">{{ $address->recipient_name }} · {{ $address->oneLine() }}</span></span>
                                    </label>
                                @endforeach
                            </fieldset>
                            @if ($paymentMethods)
                                @php
                                    $methodHints = [
                                        'card' => 'Onaydan sonra onaylanan tutarı kartla ödersiniz (iyzico güvenli ödeme).',
                                        'transfer' => 'Onaydan sonra hesap bilgilerimizi görür, havale / EFT ile ödersiniz.',
                                    ];
                                    $methodHints['pay_later'] = $payLaterBlock ?? 'Şimdi ödemezsiniz; son ödeme günü teslimatla belli olur. Kartla, havaleyle veya teslimatta nakit / POS ile ödersiniz.';
                                    $selectable = array_keys(array_filter($paymentMethods, fn ($v, $k) => $k !== 'pay_later' || ! $payLaterBlock, ARRAY_FILTER_USE_BOTH));
                                @endphp
                                <fieldset class="space-y-2">
                                    <legend class="mb-1.5 text-sm font-medium">Ödeme yöntemi</legend>
                                    @foreach ($paymentMethods as $key => $label)
                                        @php $blocked = ! in_array($key, $selectable, true); @endphp
                                        <label @class(['flex gap-3 rounded-lg border border-line p-3 text-sm', 'cursor-pointer has-checked:border-primary has-checked:bg-primary-soft/40' => ! $blocked, 'opacity-70' => $blocked])>
                                            <input type="radio" name="payment_method" value="{{ $key }}" @checked(! $blocked && old('payment_method', $selectable[0] ?? null) === $key) @disabled($blocked) class="mt-0.5">
                                            <span><b>{{ $label }}</b><span @class(['block', 'text-muted' => ! $blocked, 'text-red-700' => $blocked])>{{ $methodHints[$key] }}</span></span>
                                        </label>
                                    @endforeach
                                </fieldset>
                            @endif
                            <x-field name="note" label="Sipariş notu" textarea placeholder="Toptancıya iletmek istedikleriniz" />
                            @if ($summary['has_issues'])
                                <p class="rounded-lg bg-danger-soft px-3 py-2 text-sm text-red-900">İşaretli ürünleri düzeltmeden sipariş gönderilemez.</p>
                            @endif
                            <x-button class="w-full" :disabled="$summary['has_issues']" x-bind:disabled="sending || {{ $summary['has_issues'] ? 'true' : 'false' }}">
                                <span x-text="sending ? 'Gönderiliyor…' : 'Siparişi gönder'">Siparişi gönder</span>
                            </x-button>
                            <p class="text-xs text-muted">Siparişiniz toptancı onayından sonra kesinleşir; stok o anda kontrol edilir.@if ($paymentMethods) Ödeme onaylanan tutar üzerinden alınır; miktar değişirse size bildirilir.@endif</p>
                        </form>
                    @endif
                </x-card>
            </div>
        </div>
    @endif
</x-layouts.panel>
