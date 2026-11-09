@php
    use App\Domain\Store\PaymentCodes\PaymentCodeService;
    use App\Domain\Support\Money;
    use App\Models\Store\StorePaymentCode;
@endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" title="Ödeme Kodları">
    <x-page-header title="Ödeme Kodları" subtitle="Müşteriyle anlaştığınız özel bir tutar için (ör. özel fiyatlı fotokopi, sitede olmayan bir ürün) kod oluşturun. Müşteri kodu mağazanın arama kutusuna yazar, sepete ekler ve normal ödemeyle öder. Tutarı yalnızca siz belirlersiniz; kod tek kullanımlıktır. Kargo, sepetteki diğer ürünler gibi ücretsiz kargo sınırına göre hesaplanır." />

    @error('payment_code')<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror

    @if ($created)
        <div class="mb-6 rounded-xl border border-emerald-200 bg-success-soft p-5" x-data="copyCode(@alpine($created->display()))">
            <p class="text-sm font-semibold text-emerald-900">Kod hazır: müşterinize iletin</p>
            <div class="mt-3 flex flex-wrap items-center gap-3">
                <span class="rounded-lg bg-white px-4 py-2 font-mono text-2xl font-bold tracking-[0.2em] text-ink ring-1 ring-emerald-200">{{ $created->display() }}</span>
                <button type="button" class="rounded-lg px-3 py-2 text-sm font-semibold text-primary ring-1 ring-line hover:bg-white" @click="copy()">
                    <span x-show="!copied">Kopyala</span><span x-show="copied" x-cloak>Kopyalandı</span>
                </button>
            </div>
            <p class="mt-3 text-sm text-emerald-900">{{ $created->title }} · <strong>{{ Money::format($created->amount) }}</strong> (KDV dahil)@if ($created->expires_at) · {{ $created->expires_at->timezone('Europe/Istanbul')->format('d.m.Y') }} tarihine kadar geçerli @endif</p>
            <p class="mt-1 text-xs text-emerald-800">Müşteriye: “Mağazamızın arama kutusuna <strong>{{ $created->display() }}</strong> yazın, çıkan ödemeyi sepete ekleyip ödeyin.”</p>
        </div>
    @endif

    <x-card title="Yeni ödeme kodu" description="Açıklama müşteriye, sepette, siparişte ve faturada görünür. Tutar KDV dahildir ve kod oluşturulduktan sonra değiştirilemez; yanlışsa kodu silip yenisini oluşturun.">
        <form method="POST" action="{{ route('store_admin.payment-codes.store') }}" class="grid gap-5 lg:grid-cols-[1fr_12rem_8rem_10rem]">
            @csrf
            <x-field name="title" label="Açıklama" maxlength="150" required placeholder="Örn. 500 sayfa fotokopi, özel fiyat" />
            <x-field name="amount" label="Tutar (₺, KDV dahil)" inputmode="decimal" required placeholder="0,00" />
            <x-field name="vat_rate" label="KDV" :options="PaymentCodeService::VAT_RATES" value="20" />
            <x-field name="days" label="Geçerlilik (gün)" type="number" min="1" max="365" value="7" hint="Süresiz için boş bırakın." />
            <x-field name="note" label="Not (yalnızca yönetimde görünür)" maxlength="500" class="lg:col-span-3" placeholder="Örn. Ahmet Bey, telefonla görüşüldü" />
            <div class="flex items-end"><x-button icon="plus" class="w-full justify-center">Kod oluştur</x-button></div>
        </form>
    </x-card>

    <nav class="mb-4 mt-8 flex flex-wrap items-center gap-2 text-sm" aria-label="Durum">
        <a href="{{ route('store_admin.payment-codes.index') }}" @class(['rounded-lg px-3 py-1.5 font-medium', 'bg-primary text-white' => ! $state, 'border border-line text-ink hover:bg-slate-50' => $state])>Tümü</a>
        @foreach (StorePaymentCode::STATES as $key => [$label])
            <a href="{{ route('store_admin.payment-codes.index', ['durum' => $key]) }}" @class(['rounded-lg px-3 py-1.5 font-medium', 'bg-primary text-white' => $state === $key, 'border border-line text-ink hover:bg-slate-50' => $state !== $key])>{{ $label }}</a>
        @endforeach
        <form method="GET" action="{{ route('store_admin.payment-codes.index') }}" class="ml-auto flex gap-2">
            @if ($state)<input type="hidden" name="durum" value="{{ $state }}">@endif
            <label for="kod-ara" class="sr-only">Kod veya açıklama ara</label>
            <input id="kod-ara" name="q" value="{{ $term }}" class="field-input w-56" placeholder="Kod veya açıklama">
            <x-button variant="secondary">Ara</x-button>
        </form>
    </nav>

    <x-card :padding="false">
        @if ($codes->isEmpty())
            <x-empty-state icon="key" :title="$state || $term ? 'Eşleşen kod yok' : 'Henüz ödeme kodu yok'" text="Müşteriyle tutarda anlaştığınızda yukarıdan kod oluşturun." />
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>Kod</th><th>Açıklama</th><th class="text-right">Tutar</th><th>Geçerlilik</th><th>Durum</th><th>Sipariş</th><th><span class="sr-only">İşlem</span></th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($codes as $code)
                            @php
                                $codeState = $code->state();
                                [$label, $tone] = StorePaymentCode::STATES[$codeState];
                                $order = $code->order;
                            @endphp
                            <tr>
                                <td class="whitespace-nowrap font-mono text-sm font-semibold tracking-wider">{{ $code->display() }}</td>
                                <td><span class="font-medium text-ink">{{ $code->title }}</span>
                                    @if ($code->note)<span class="block text-xs text-muted">{{ $code->note }}</span>@endif
                                    <span class="block text-xs text-muted">{{ $code->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}@if ($code->creator) · {{ $code->creator->name }}@endif · KDV %{{ (int) $code->vat_rate }}</span></td>
                                <td class="text-right font-semibold tabular-nums">{{ Money::format($code->amount) }}</td>
                                <td class="whitespace-nowrap text-sm text-muted">{{ $code->expires_at ? $code->expires_at->timezone('Europe/Istanbul')->format('d.m.Y') : 'Süresiz' }}</td>
                                <td><x-badge :tone="$tone">{{ $label }}</x-badge></td>
                                <td class="text-sm">
                                    @if ($order)
                                        <a href="{{ route('store_admin.orders.show', $order) }}" class="font-medium text-primary hover:underline">{{ $order->order_no }}</a>
                                        <span class="block text-xs text-muted">{{ $order->statusLabel() }}@if ($codeState !== 'used') · kod yeniden kullanılabilir @endif</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <div class="flex items-center justify-end gap-3 whitespace-nowrap">
                                        @if ($codeState !== 'used' && ! $code->is_cancelled)
                                            <form method="POST" action="{{ route('store_admin.payment-codes.cancel', $code) }}" data-confirm="{{ $code->display() }} kodu pasif edilsin mi? Müşteri bu kodla ödeme yapamaz; istediğiniz zaman yeniden açabilirsiniz.">
                                                @csrf
                                                <button class="text-sm font-medium text-ink hover:underline">Pasif et</button>
                                            </form>
                                        @elseif ($code->is_cancelled && $codeState !== 'used')
                                            <form method="POST" action="{{ route('store_admin.payment-codes.reactivate', $code) }}">
                                                @csrf
                                                <button class="text-sm font-medium text-primary hover:underline">Yeniden aç</button>
                                            </form>
                                        @endif
                                        @if (PaymentCodeService::deletable($code))
                                            <form method="POST" action="{{ route('store_admin.payment-codes.destroy', $code) }}" data-confirm="{{ $code->display() }} kodu silinsin mi? Bu işlem geri alınamaz; kod müşterilerin sepetlerinden de kalkar.">
                                                @csrf @method('DELETE')
                                                <button class="text-sm font-medium text-danger hover:underline">Sil</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
    @if ($codes->hasPages())<div class="mt-4">{{ $codes->links() }}</div>@endif

    <form id="ayarlar" method="POST" action="{{ route('store_admin.payment-codes.settings') }}" class="mt-8 space-y-6">
        @csrf @method('PUT')
        <div class="grid gap-6 xl:grid-cols-2">
            <x-card title="Kodla ödeme" description="Kapatırsanız arama kutusu kodları tanımaz ve sepetteki kodlar sipariş edilemez.">
                <div class="space-y-5">
                    <x-toggle name="enabled" label="Ödeme koduyla ödeme açık" :checked="$s['enabled']" />
                    <x-field name="item_name" label="Kalem adı" :value="$s['item_name']" maxlength="60" required hint="Sepette, siparişte ve faturada açıklamanın önüne yazılır. Örn. Özel ödeme: 500 sayfa fotokopi" />
                    <x-field name="code_hint" label="Kod alanının açıklaması" :value="$s['code_hint']" maxlength="120" required />
                </div>
            </x-card>
            <x-card title="“Aradığınızı bulamadınız mı?” kutusu" description="Ürün listelerinin altında küçük bir kutu olarak, ürün bulunamayan aramalarda büyük olarak görünür. Altında mağazanızın iletişim bilgileri (Mağaza Ayarları → İletişim) ve kod alanı yer alır.">
                <div class="space-y-5">
                    <x-toggle name="box_enabled" label="Kutuyu göster" :checked="$s['box_enabled']" />
                    <x-field name="box_title" label="Başlık" :value="$s['box_title']" maxlength="80" required />
                    <x-field name="box_text" label="Mesaj" :value="$s['box_text']" maxlength="400" textarea required />
                </div>
            </x-card>
        </div>
        <x-button>Ayarları kaydet</x-button>
    </form>
</x-layouts.panel>
