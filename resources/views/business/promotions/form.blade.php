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
    <x-page-header :title="$title" subtitle="İndirim KDV hariç sipariş tutarından düşülür; indirimli bayi fiyatlarının üzerine uygulanır. Siparişte tek kupon veya kampanya geçerlidir.">
        <x-button variant="secondary" :href="route('business.promotions.index')">Listeye dön</x-button>
    </x-page-header>
    @error('promotion')<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror

    <form method="POST" action="{{ $exists ? route('business.promotions.update', $promotion) : route('business.promotions.store') }}" enctype="multipart/form-data" class="grid gap-6 lg:grid-cols-3">
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
                </div>
            </x-card>

            <x-card title="Bayiye görünen tanıtım">
                <div class="space-y-5">
                    <x-field name="description" label="Açıklama" textarea :value="$promotion->description" placeholder="Ör. Okul sezonu boyunca defter ve kalemlerde geçerli. Stoklarla sınırlıdır." hint="Bayinin Kampanyalar sayfasında ve kampanya detayında görünür. En fazla 2.000 karakter; satır atlayabilirsiniz." />

                    {{-- Kapak: kampanya kartında ve detayın başında; detay fotoğrafları galeride. --}}
                    @php $cover = $images->firstWhere('is_cover', true); $details = $images->where('is_cover', false)->values(); @endphp
                    <div x-data="imagePicks">
                        <p class="mb-1.5 text-sm font-medium">Kapak fotoğrafı</p>
                        <div class="flex flex-wrap items-start gap-4">
                            @if ($cover)
                                <figure class="w-48">
                                    <img src="{{ route('business.promotions.image', ['promotionImage' => $cover, 'w' => 640]) }}" alt="Kapak" class="aspect-video w-full rounded-lg border border-line object-cover">
                                    <label class="mt-1.5 flex items-center gap-1.5 text-xs text-danger"><input type="checkbox" name="remove_cover" value="1"> Kapağı kaldır</label>
                                </figure>
                            @endif
                            <template x-for="url in previews" :key="url">
                                <img :src="url" alt="Yeni kapak" class="aspect-video w-48 rounded-lg border-2 border-primary object-cover">
                            </template>
                        </div>
                        <input type="file" name="cover" accept="image/jpeg,image/png,image/webp" @change="pick($event)" class="mt-2 block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium">
                        <p class="mt-1 text-xs text-muted">Yatay fotoğraf önerilir (1200×675). JPG, PNG veya WEBP; en fazla 5 MB. {{ $cover ? 'Yeni seçerseniz eskisinin yerine geçer.' : 'Eklemezseniz hazır renkli kapak kullanılır.' }}</p>
                        @error('cover')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                    </div>

                    <div x-data="imagePicks">
                        <p class="mb-1.5 text-sm font-medium">Detay fotoğrafları <span class="font-normal text-muted">(en fazla {{ \App\Domain\Pricing\PromotionAdmin::MAX_IMAGES }})</span></p>
                        @if ($details->isNotEmpty())
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                @foreach ($details as $image)
                                    <figure class="rounded-lg border border-line p-2">
                                        <img src="{{ route('business.promotions.image', ['promotionImage' => $image, 'w' => 320]) }}" alt="Detay {{ $loop->iteration }}" class="aspect-square w-full rounded-md object-cover">
                                        <div class="mt-2 flex items-center justify-between gap-2 text-xs">
                                            <label class="flex items-center gap-1">Sıra <input type="number" name="image_sort[{{ $image->id }}]" value="{{ $image->sort }}" min="0" max="1000" class="field-input w-14 px-1.5 py-1 text-xs"></label>
                                            <label class="flex items-center gap-1 text-danger"><input type="checkbox" name="remove_images[]" value="{{ $image->id }}"> Kaldır</label>
                                        </div>
                                    </figure>
                                @endforeach
                            </div>
                        @endif
                        <div class="mt-2 flex flex-wrap gap-2">
                            <template x-for="url in previews" :key="url">
                                <img :src="url" alt="Yeni fotoğraf" class="size-20 rounded-md border-2 border-primary object-cover">
                            </template>
                        </div>
                        <input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp" @change="pick($event)" class="mt-2 block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium">
                        <p class="mt-1 text-xs text-muted">Birden fazla seçebilirsiniz; her biri en fazla 5 MB. Küçük sıra numarası önce gösterilir.</p>
                        @error('images')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                        @error('images.*')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                    </div>
                </div>
            </x-card>

            <x-card title="Kime">
                <div class="space-y-4">
                    <x-field name="target" label="Kime" :options="Promotion::TARGETS" :value="$promotion->target" />
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
        @php $usedIn = $promotion->redemptions()->whereNull('released_at')->count(); @endphp
        <form method="POST" action="{{ route('business.promotions.destroy', $promotion) }}" class="mt-4"
            data-confirm="“{{ $promotion->name }}” silinsin mi? Bayilerden ve sepetlerden hemen kalkar.{{ $usedIn ? ' '.$usedIn.' siparişte kullanıldı; bu siparişlerdeki indirim değişmez.' : '' }} Bu işlem geri alınamaz.">@csrf @method('DELETE')
            <button class="text-sm text-danger hover:underline">Sil</button>
        </form>
    @endif
</x-layouts.panel>
