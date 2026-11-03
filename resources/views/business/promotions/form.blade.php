@php
    use App\Domain\Support\Money;
    use App\Models\Order;
    use App\Models\Promotion;

    $exists = $promotion->exists;
    $money = fn ($v) => $v !== null ? number_format((float) $v, 2, ',', '') : null;
    $local = fn ($v) => $v?->timezone('Europe/Istanbul')->format('Y-m-d\TH:i');
    $kind = old('kind', $exists && ! $promotion->isCoupon() ? 'campaign' : 'coupon');
    $selectedDealers = array_map('intval', old('dealer_ids', $promotion->dealer_ids ?? []));
    $selectedCategories = array_map('intval', old('category_ids', $promotion->category_ids ?? []));
    $title = $exists ? $promotion->name : 'Yeni kupon / kampanya';
@endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Business" :title="$title">
    <x-page-header :title="$title" subtitle="İndirim KDV hariç sipariş tutarından düşülür; ürün indirimlerinin ve bayiye özel fiyatların üzerine uygulanır. Siparişte tek kupon veya kampanya geçerlidir.">
        <x-button variant="secondary" :href="route('business.promotions.index')">Listeye dön</x-button>
    </x-page-header>
    @error('promotion')<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror

    <form method="POST" action="{{ $exists ? route('business.promotions.update', $promotion) : route('business.promotions.store') }}" class="grid gap-6 lg:grid-cols-3">
        @csrf
        @if ($exists) @method('PUT') @endif
        <div class="space-y-6 lg:col-span-2">
            <x-card title="İndirim">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field name="name" label="Ad" required :value="$promotion->name" placeholder="Ör. Okul sezonu kampanyası" class="sm:col-span-2" />
                    <fieldset class="sm:col-span-2">
                        <legend class="mb-1.5 text-sm font-medium">Tür</legend>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <label class="flex cursor-pointer gap-3 rounded-lg border border-line p-3 text-sm has-checked:border-primary has-checked:bg-primary-soft/40">
                                <input type="radio" name="kind" value="coupon" @checked($kind === 'coupon') class="mt-0.5">
                                <span><b>Kupon</b><span class="block text-muted">Bayi kodu sepette girer.</span></span>
                            </label>
                            <label class="flex cursor-pointer gap-3 rounded-lg border border-line p-3 text-sm has-checked:border-primary has-checked:bg-primary-soft/40">
                                <input type="radio" name="kind" value="campaign" @checked($kind === 'campaign') class="mt-0.5">
                                <span><b>Otomatik kampanya</b><span class="block text-muted">Kodsuz; koşulu tutan siparişe uygulanır.</span></span>
                            </label>
                        </div>
                    </fieldset>
                    <x-field name="code" label="Kupon kodu" :value="$promotion->code" placeholder="Ör. OKUL10" hint="Yalnızca kupon için. Harf, rakam, - ve _ (Türkçe karakter olmadan)." />
                    <x-field name="type" label="İndirim türü" :options="Promotion::TYPES" :value="$promotion->type" />
                    <x-field name="value" label="İndirim değeri" required :value="$money($promotion->value)" placeholder="Ör. 10 veya 500,00" hint="Yüzdede oran (%), tutarda KDV hariç ₺." />
                    <x-field name="max_discount" label="En fazla indirim (₺)" :value="$money($promotion->max_discount)" hint="Yalnızca yüzde indirimde; boş: sınırsız." />
                    <x-field name="min_subtotal" label="En düşük sipariş tutarı (₺, KDV hariç)" :value="$money($promotion->min_subtotal)" hint="Tutar indiriminde zorunlu ve indirimden büyük olmalı. Kapsam seçildiyse yalnızca kapsamdaki ürünlerin tutarı sayılır." />
                    <x-field name="description" label="Açıklama (bayiye görünür)" :value="$promotion->description" placeholder="Ör. Okul sezonu boyunca geçerli" />
                </div>
            </x-card>

            <x-card title="Kime">
                <div class="space-y-4">
                    <x-field name="target" label="Kime" :options="Promotion::TARGETS" :value="$promotion->target" />
                    <x-field name="dealer_group_id" label="Bayi grubu (Kime: bayi grubu ise)" :options="['' => 'Seçin'] + $groups" :value="$promotion->dealer_group_id" />
                    <fieldset>
                        <legend class="mb-1.5 text-sm font-medium">Bayiler (Kime: seçili bayiler ise)</legend>
                        @error('dealer_ids')<p class="mb-1.5 text-sm text-danger">{{ $message }}</p>@enderror
                        <div class="max-h-60 space-y-1 overflow-y-auto rounded-lg border border-line p-3 text-sm">
                            @forelse ($dealers as $id => $label)
                                <label class="flex items-center gap-2"><input type="checkbox" name="dealer_ids[]" value="{{ $id }}" @checked(in_array($id, $selectedDealers, true))> {{ $label }}</label>
                            @empty
                                <p class="text-muted">Henüz bayi yok.</p>
                            @endforelse
                        </div>
                    </fieldset>
                </div>
            </x-card>

            <x-card title="Kapsam (isteğe bağlı)">
                <p class="mb-3 text-sm text-muted">Boş bırakılırsa siparişteki tüm ürünlere uygulanır. Seçilirse indirim yalnızca bu kategorilerdeki (alt kategorileriyle) ve bu stok kodlarındaki ürünlere uygulanır.</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <fieldset>
                        <legend class="mb-1.5 text-sm font-medium">Kategoriler</legend>
                        <div class="max-h-60 space-y-1 overflow-y-auto rounded-lg border border-line p-3 text-sm">
                            @forelse ($categories as $id => $label)
                                <label class="flex items-center gap-2"><input type="checkbox" name="category_ids[]" value="{{ $id }}" @checked(in_array($id, $selectedCategories, true))> {{ $label }}</label>
                            @empty
                                <p class="text-muted">Kategori yok.</p>
                            @endforelse
                        </div>
                    </fieldset>
                    <x-field name="product_skus" label="Ürün stok kodları" textarea :value="$skus" hint="Her satıra veya virgülle bir stok kodu." />
                </div>
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Süre ve sınır">
                <div class="space-y-4">
                    <x-field name="starts_at" label="Başlangıç" type="datetime-local" :value="$local($promotion->starts_at)" hint="Boş: hemen." />
                    <x-field name="ends_at" label="Bitiş" type="datetime-local" :value="$local($promotion->ends_at)" hint="Boş: süresiz." />
                    <x-field name="usage_limit" label="Toplam kullanım sınırı" type="number" min="1" :value="$promotion->usage_limit" hint="Boş: sınırsız." />
                    <x-field name="per_dealer_limit" label="Bayi başına kullanım" type="number" min="1" :value="$promotion->per_dealer_limit" hint="Boş: sınırsız." />
                    <label class="flex items-center gap-2 text-sm">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $exists ? $promotion->is_active : true))> Etkin
                    </label>
                    <label class="flex items-start gap-2 text-sm">
                        <input type="hidden" name="show_to_dealers" value="0">
                        <input type="checkbox" name="show_to_dealers" value="1" class="mt-0.5" @checked(old('show_to_dealers', $exists && $promotion->isCoupon() && $promotion->show_to_dealers))>
                        <span>Kupon kodunu bayilerin Kampanyalar sayfasında göster<span class="block text-xs text-muted">Yalnızca kupon için; hedefteki bayiler kodu görüp tek tıkla uygular. Kişiye özel verdiğiniz kodlarda kapalı tutun. Otomatik kampanyalar hedefteki bayilere her zaman gösterilir.</span></span>
                    </label>
                </div>
            </x-card>
            <x-button class="w-full">{{ $exists ? 'Kaydet' : 'Oluştur' }}</x-button>
            <p class="text-xs text-muted">Reddedilen veya iptal edilen siparişin kullanımı sayılmaz. Toptancı daha az miktar onaylarsa indirim onaylanan tutara göre yeniden hesaplanır.</p>
        </div>
    </form>

    @if ($exists)
        <x-card title="Kullanımlar" class="mt-6" :padding="false">
            @if ($redemptions->isEmpty())
                <p class="px-5 py-4 text-sm text-muted">Henüz kullanılmadı.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-line text-left text-xs text-muted">
                            <tr><th class="px-5 py-3 font-medium">Sipariş</th><th class="px-3 py-3 font-medium">Bayi</th><th class="px-3 py-3 font-medium">Tarih</th><th class="px-3 py-3 text-right font-medium">İndirim</th><th class="px-5 py-3 font-medium">Durum</th></tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($redemptions as $r)
                                <tr @class(['text-muted' => $r->released_at])>
                                    <td class="px-5 py-3"><a href="{{ route('business.orders.show', $r->order_id) }}" class="font-medium hover:text-primary">{{ $r->order?->order_no }}</a></td>
                                    <td class="px-3 py-3">{{ $r->relationship?->dealer?->legal_name }}</td>
                                    <td class="px-3 py-3">{{ $r->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</td>
                                    <td class="px-3 py-3 text-right tabular-nums">{{ Money::format($r->amount) }}</td>
                                    <td class="px-5 py-3">{{ Order::STATUSES[$r->order?->status] ?? '' }}{{ $r->released_at ? ' · kullanım sayılmadı' : '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>
        @if ($redemptions->isEmpty())
            <form method="POST" action="{{ route('business.promotions.destroy', $promotion) }}" class="mt-4" data-confirm="Silinsin mi?">@csrf @method('DELETE')
                <button class="text-sm text-danger hover:underline">Sil</button>
            </form>
        @endif
    @endif
</x-layouts.panel>
