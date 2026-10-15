@php
    use App\Domain\Support\Money;
    use App\Models\DealerRelationship;
    $r = $relationship;
    $user = $r->dealer->user;
@endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Business" :title="$r->dealer->legal_name">
    <x-page-header :title="$r->dealer->legal_name" :subtitle="$r->dealer_code.($r->group ? ' · '.$r->group->name : '')">
        <x-slot:breadcrumb><a href="{{ route('business.dealers.index') }}" class="hover:underline">Bayiler</a></x-slot:breadcrumb>
        <x-badge :tone="['active' => 'success', 'suspended' => 'warning', 'closed' => 'neutral'][$r->status]">{{ DealerRelationship::STATUSES[$r->status] }}</x-badge>
    </x-page-header>

    <x-activation-link />
    @error('activation')<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror

    <nav class="mb-6 flex gap-1 overflow-x-auto border-b border-line" aria-label="Sekmeler">
        @foreach (['general' => 'Genel', 'addresses' => 'Sevk adresleri', 'prices' => 'Özel fiyatlar', 'preview' => 'Fiyat önizleme'] as $key => $label)
            <a href="{{ route('business.dealers.show', [$r, 'tab' => $key]) }}" @class([
                '-mb-px shrink-0 border-b-2 px-4 py-2.5 text-sm font-medium',
                'border-primary text-primary' => $tab === $key,
                'border-transparent text-muted hover:text-ink' => $tab !== $key,
            ])>{{ $label }}</a>
        @endforeach
    </nav>

    @if ($tab === 'general')
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <x-card title="İşletme bilgileri">
                    <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                        @foreach ([
                            'Yetkili' => $user?->name, 'Giriş e-postası' => $user?->email, 'Telefon' => $r->dealer->phone,
                            'Vergi dairesi / no' => trim(($r->dealer->tax_office ?? '').' '.($r->dealer->tax_number ?? '')),
                            'Adres' => trim(($r->dealer->address ?? '').', '.($r->dealer->district ?? '').' / '.($r->dealer->city ?? ''), ', /'),
                            'Cari hesap kodu' => $r->currentAccount?->code,
                        ] as $label => $value)
                            <div><dt class="text-muted">{{ $label }}</dt><dd class="mt-0.5 font-medium">{{ $value ?: '—' }}</dd></div>
                        @endforeach
                    </dl>
                </x-card>

                <x-card title="Ticari koşullar">
                    <form method="POST" action="{{ route('business.dealers.update', $r) }}" class="grid gap-5 sm:grid-cols-3">
                        @csrf @method('PUT')
                        <x-field name="dealer_code" label="Bayi kodu" :value="$r->dealer_code" required />
                        <x-field name="dealer_group_id" label="Bayi grubu" :options="['' => 'Grupsuz'] + $groups->all()" :value="$r->dealer_group_id" />
                        <x-field name="payment_term_days" label="Vade (gün)" type="number" min="0" max="365" :value="$r->payment_term_days" required />
                        <x-toggle name="can_edit_addresses" label="Bayi sevk adreslerini değiştirebilir" :checked="$r->can_edit_addresses" class="sm:col-span-2" />
                        <div class="sm:text-right"><x-button variant="secondary">Kaydet</x-button></div>
                    </form>
                    @if ($r->group?->priceList)
                        <p class="mt-4 text-sm text-muted">Grup fiyat listesi: <a href="{{ route('business.price-lists.show', $r->group->priceList) }}" class="text-primary hover:underline">{{ $r->group->priceList->name }}</a></p>
                    @endif
                </x-card>
            </div>

            <div class="space-y-6">
                <x-card title="Hesap">
                    @if ($user?->status === 'pending_activation')
                        <x-badge tone="warning">Aktivasyon bekliyor</x-badge>
                        <p class="mt-2 text-sm text-muted">Bayi henüz parolasını belirlemedi.</p>
                        <form method="POST" action="{{ route('business.dealers.activation', $r) }}" class="mt-3">@csrf<x-button variant="secondary" size="sm" icon="refresh">Yeni aktivasyon bağlantısı</x-button></form>
                    @else
                        <x-badge tone="success">Etkin</x-badge>
                        <p class="mt-2 text-xs text-muted">Son giriş: {{ $user?->last_login_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i') ?? '—' }}</p>
                    @endif
                </x-card>

                <x-card title="Cari bakiye">
                    <p class="text-2xl font-semibold tabular-nums">{{ Money::format($r->currentAccount?->balance ?? 0) }}</p>
                    <p class="mt-1 text-xs text-muted">Cari hareketler Aşama 6'da eklenecek.</p>
                </x-card>

                <x-card title="Bayi durumu">
                    <form method="POST" action="{{ route('business.dealers.status', $r) }}" class="space-y-4" x-data="{ status: '{{ $r->status }}' }">
                        @csrf @method('PUT')
                        <x-field name="status" label="Durum" :options="DealerRelationship::STATUSES" :value="$r->status" x-model="status" />
                        <div x-show="status !== 'active'" x-cloak><x-field name="reason" label="Gerekçe" textarea hint="Askıya alınan bayi sizinle ilgili ekranlara erişemez ve sipariş veremez." /></div>
                        <x-button variant="secondary">Durumu güncelle</x-button>
                    </form>
                </x-card>
            </div>
        </div>
    @elseif ($tab === 'addresses')
        <div class="grid gap-6 lg:grid-cols-2">
            <x-card title="Kayıtlı adresler" :padding="false">
                @forelse ($r->addresses as $address)
                    <div class="border-b border-line px-5 py-4 last:border-0" x-data="{ edit: false }">
                        <div x-show="!edit" class="flex gap-3">
                            <div class="flex-1 text-sm">
                                <p class="font-medium">{{ $address->title }} @if ($address->is_default)<x-badge tone="primary" class="ml-1">Varsayılan</x-badge>@endif</p>
                                <p class="text-muted">{{ $address->recipient_name }} · {{ $address->phone }}</p>
                                <p>{{ $address->oneLine() }}</p>
                                @if ($address->note)<p class="text-xs text-muted">{{ $address->note }}</p>@endif
                            </div>
                            <div class="flex flex-col items-end gap-1 text-sm">
                                <button type="button" @click="edit = true" class="text-primary hover:underline">Düzenle</button>
                                <form method="POST" action="{{ route('business.dealers.addresses.destroy', [$r, $address]) }}" onsubmit="return confirm('Adres kaldırılsın mı?')">@csrf @method('DELETE')<button class="text-danger hover:underline">Kaldır</button></form>
                            </div>
                        </div>
                        <div x-show="edit" x-cloak>
                            @include('partials.address-form', ['action' => route('business.dealers.addresses.update', [$r, $address]), 'address' => $address])
                            <button type="button" @click="edit = false" class="mt-2 text-sm text-muted hover:underline">Vazgeç</button>
                        </div>
                    </div>
                @empty
                    <x-empty-state icon="building" title="Kayıtlı sevk adresi yok" class="py-8" />
                @endforelse
            </x-card>
            <x-card title="Yeni adres" description="Siparişte adresin kopyası saklanır; sonradan değişiklik geçmiş siparişleri etkilemez.">
                @include('partials.address-form', ['action' => route('business.dealers.addresses.store', $r), 'submit' => 'Adres ekle'])
            </x-card>
        </div>
    @elseif ($tab === 'prices')
        <div class="grid gap-6 lg:grid-cols-3">
            <x-card title="Bayiye özel fiyatlar" description="Özel fiyat nihai fiyattır; üzerine indirim uygulanmaz." class="lg:col-span-2" :padding="false">
                @if ($prices->isEmpty())
                    <x-empty-state icon="percent" title="Bu bayiye özel fiyat tanımlanmadı" class="py-8" />
                @else
                    <table class="table-base">
                        <thead><tr><th>Ürün</th><th class="text-right">Fiyat</th><th>Geçerlilik</th><th></th></tr></thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($prices as $price)
                                @php $active = (! $price->starts_on || $price->starts_on->isPast() || $price->starts_on->isToday()) && (! $price->ends_on || $price->ends_on->endOfDay()->isFuture()); @endphp
                                <tr>
                                    <td><span class="font-medium">{{ $price->product->name }}</span><p class="text-xs text-muted">{{ $price->product->sku }}</p></td>
                                    <td class="text-right font-medium tabular-nums">{{ Money::formatUnit($price->price) }}</td>
                                    <td class="text-sm">
                                        {{ $price->starts_on?->format('d.m.Y') ?? 'Hemen' }} – {{ $price->ends_on?->format('d.m.Y') ?? 'Süresiz' }}
                                        @unless ($active)<x-badge class="ml-1">Geçerli değil</x-badge>@endunless
                                    </td>
                                    <td class="text-right"><form method="POST" action="{{ route('business.dealers.prices.destroy', [$r, $price]) }}">@csrf @method('DELETE')<button class="text-sm text-danger hover:underline">Kaldır</button></form></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if ($prices->hasPages())<div class="border-t border-line p-4">{{ $prices->links() }}</div>@endif
                @endif
            </x-card>
            <x-card title="Özel fiyat ekle">
                <form method="POST" action="{{ route('business.dealers.prices.store', $r) }}" class="space-y-4">
                    @csrf
                    <x-product-picker />
                    <x-field name="price" label="Birim fiyat (₺)" type="number" step="0.0001" min="0" required hint="Ürünün KDV dahil/hariç ayarına göre yorumlanır." />
                    <div class="grid grid-cols-2 gap-3">
                        <x-field name="starts_on" label="Başlangıç" type="date" />
                        <x-field name="ends_on" label="Bitiş" type="date" />
                    </div>
                    <x-button>Kaydet</x-button>
                </form>
            </x-card>
        </div>
    @else
        <x-card title="Bu bayinin göreceği fiyatlar" description="Fiyat kuralları bugünkü tarihe göre uygulanır. KDV hariç birim fiyatlar." :padding="false">
            <form method="GET" class="flex gap-2 border-b border-line p-4">
                <input type="hidden" name="tab" value="preview">
                <input name="q" value="{{ request('q') }}" class="field-input max-w-sm" placeholder="Ürün adı veya kodu" aria-label="Ara">
                <x-button variant="secondary" icon="search">Ara</x-button>
            </form>
            @if ($previewProducts->isEmpty())
                <x-empty-state icon="package" title="Aktif ürün bulunamadı" class="py-8" />
            @else
                <div class="overflow-x-auto">
                    <table class="table-base">
                        <thead><tr><th>Ürün</th><th class="text-right">Liste</th><th class="text-right">Taban</th><th>Kaynak</th><th class="text-right">İndirim</th><th class="text-right">Bayi fiyatı</th></tr></thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($previewProducts as $product)
                                @php $p = $previewPrices[$product->id]; @endphp
                                <tr>
                                    <td><span class="font-medium">{{ $product->name }}</span><p class="text-xs text-muted">{{ $product->sku }}</p></td>
                                    <td class="text-right text-muted tabular-nums">{{ Money::formatUnit($p->listPrice) }}</td>
                                    <td class="text-right tabular-nums">{{ Money::formatUnit($p->basePrice) }}</td>
                                    <td class="text-sm">{{ $p->sourceLabel() }}</td>
                                    <td class="text-right text-sm">@if ($p->hasDiscount())%{{ rtrim(rtrim(number_format((float) $p->discountPercent, 2, ',', ''), '0'), ',') }} <span class="block text-xs text-muted">{{ $p->discountName }}</span>@else — @endif</td>
                                    <td class="text-right font-semibold tabular-nums">{{ Money::formatUnit($p->unitPrice) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>
    @endif
</x-layouts.panel>
