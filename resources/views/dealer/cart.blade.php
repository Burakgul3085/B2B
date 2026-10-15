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
                            <div class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-line bg-slate-50">
                                @if ($p->primaryImage)<img src="{{ route('dealer.images.show', $p->primaryImage) }}" alt="" class="size-full object-cover">@else<x-icon name="image" class="size-5 text-slate-300" />@endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('dealer.catalog.show', $p) }}" class="font-medium hover:text-primary">{{ $p->name }}</a>
                                <p class="text-xs text-muted">{{ $p->sku }}</p>
                                @if ($line['price'])
                                    <p class="mt-1 text-sm tabular-nums">{{ Money::formatUnit($line['price']->basePrice) }} / {{ $p->unit->name }}
                                        @if ($line['price']->hasDiscount())<span class="text-success"> · %{{ rtrim(rtrim(number_format((float) $line['price']->discountPercent, 2, ',', ''), '0'), ',') }} indirim</span>@endif
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
                        @if (bccomp($summary['totals']['discount_total'], '0', 2) > 0)
                            <div class="flex justify-between text-success"><dt>İndirim</dt><dd class="tabular-nums">−{{ Money::format($summary['totals']['discount_total']) }}</dd></div>
                        @endif
                        <div class="flex justify-between"><dt class="text-muted">KDV</dt><dd class="tabular-nums">{{ Money::format($summary['totals']['vat_total']) }}</dd></div>
                        <div class="flex justify-between border-t border-line pt-2 text-base font-semibold"><dt>Genel toplam</dt><dd class="tabular-nums">{{ Money::format($summary['totals']['grand_total']) }}</dd></div>
                    </dl>
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
                            <x-field name="note" label="Sipariş notu" textarea placeholder="Toptancıya iletmek istedikleriniz" />
                            @if ($summary['has_issues'])
                                <p class="rounded-lg bg-danger-soft px-3 py-2 text-sm text-red-900">İşaretli ürünleri düzeltmeden sipariş gönderilemez.</p>
                            @endif
                            <x-button class="w-full" :disabled="$summary['has_issues']" x-bind:disabled="sending || {{ $summary['has_issues'] ? 'true' : 'false' }}">
                                <span x-text="sending ? 'Gönderiliyor…' : 'Siparişi gönder'">Siparişi gönder</span>
                            </x-button>
                            <p class="text-xs text-muted">Siparişiniz toptancı onayından sonra kesinleşir; stok o anda kontrol edilir.</p>
                        </form>
                    @endif
                </x-card>
            </div>
        </div>
    @endif
</x-layouts.panel>
