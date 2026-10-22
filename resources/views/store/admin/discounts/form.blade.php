@php
    use App\Domain\Support\Money;
    use App\Models\Store\StoreDiscount;

    $d = $discount;
    $money = fn ($v) => $v === null ? '' : number_format((float) $v, 2, ',', '');
    $local = fn ($t) => $t?->timezone('Europe/Istanbul')->format('Y-m-d\TH:i');
    $kind = old('kind', $d->exists ? ($d->code ? 'coupon' : 'campaign') : 'coupon');
@endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" :title="$d->exists ? $d->name : 'Yeni indirim'">
    <x-page-header :title="$d->exists ? $d->name : 'Yeni indirim'" :subtitle="$d->exists ? $d->summary() : 'Kupon veya otomatik kampanya tanımlayın.'">
        <x-slot:breadcrumb><a href="{{ route('store_admin.discounts.index') }}" class="hover:underline">Kampanya ve Kuponlar</a></x-slot:breadcrumb>
    </x-page-header>

    <div class="grid gap-6 xl:grid-cols-3">
        <form method="POST" action="{{ $d->exists ? route('store_admin.discounts.update', $d) : route('store_admin.discounts.store') }}"
            class="space-y-6 xl:col-span-2" x-data="{ kind: @alpine($kind), type: @alpine(old('type', $d->type)) }">
            @csrf @if ($d->exists) @method('PUT') @endif

            <x-card title="İndirim">
                <div class="space-y-5">
                    <fieldset class="flex flex-wrap gap-4 text-sm" @if ($used) disabled @endif>
                        <label class="flex items-center gap-2"><input type="radio" name="kind" value="coupon" x-model="kind" class="text-primary"> Kupon (müşteri kodu girer)</label>
                        <label class="flex items-center gap-2"><input type="radio" name="kind" value="campaign" x-model="kind" class="text-primary"> Otomatik kampanya (kodsuz)</label>
                    </fieldset>
                    @if ($used)<input type="hidden" name="kind" value="{{ $kind }}">@endif
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-field name="name" label="Ad" required maxlength="120" :value="$d->name" hint="Kampanyada sepette ve siparişte müşteriye görünür." />
                        <div x-show="kind === 'coupon'">
                            <x-field name="code" label="Kupon kodu" maxlength="40" :value="$d->code" class="uppercase" :readonly="$used"
                                :hint="$used ? 'Kullanılmış kuponun kodu değiştirilemez.' : 'Harf, rakam, - ve _ (ör. HOSGELDIN10).'" />
                        </div>
                    </div>
                    <div class="grid gap-5 sm:grid-cols-3">
                        <x-field name="type" label="Tür" :options="StoreDiscount::TYPES" :value="$d->type" x-model="type" required />
                        <div x-show="type !== 'free_shipping'">
                            <x-field name="value" label="İndirim" inputmode="decimal" :value="$money($d->value)" hint="Yüzde için 10, tutar için 50,00" />
                        </div>
                        <div x-show="type === 'percent'">
                            <x-field name="max_discount" label="En fazla indirim (₺)" inputmode="decimal" :value="$money($d->max_discount)" hint="Boş: sınırsız" />
                        </div>
                    </div>
                </div>
            </x-card>

            <x-card title="Kapsam" description="Boş bırakılırsa sepetteki tüm ürünlere uygulanır. Seçilirse en düşük tutar ve indirim yalnızca bu ürünler üzerinden hesaplanır.">
                @php $selectedCats = array_map('intval', old('category_ids', $d->category_ids ?? [])); @endphp
                <div class="space-y-5">
                    <fieldset>
                        <legend class="text-sm font-medium">Kategoriler <span class="font-normal text-muted">(alt kategorileriyle)</span></legend>
                        @if ($categories->isEmpty())
                            <p class="mt-2 text-sm text-muted">Henüz kategori yok.</p>
                        @else
                            <div class="mt-2 grid max-h-56 grid-cols-2 gap-2 overflow-y-auto sm:grid-cols-3">
                                @foreach ($categories as $cat)
                                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="category_ids[]" value="{{ $cat->id }}" @checked(in_array($cat->id, $selectedCats, true)) class="size-4 rounded text-primary"> {{ $cat->name }}</label>
                                @endforeach
                            </div>
                        @endif
                    </fieldset>
                    <x-field name="product_skus" label="Ürünler (SKU)" textarea :value="$skus" hint="Virgül veya satır başıyla ayırın. Bir seçeneğin SKU'su girilse de ürünün tüm seçenekleri kapsama girer." />
                    @error('product_skus')<p class="text-sm text-danger">{{ $message }}</p>@enderror
                </div>
            </x-card>

            <x-card title="Koşullar">
                <div class="space-y-5">
                    <div class="grid gap-5 sm:grid-cols-3">
                        <x-field name="min_subtotal" label="En düşük sepet tutarı (₺)" inputmode="decimal" :value="$money($d->min_subtotal)" hint="KDV dahil ürün toplamı" />
                        <x-field name="usage_limit" label="Toplam kullanım" type="number" min="1" :value="$d->usage_limit" hint="Boş: sınırsız" />
                        <x-field name="per_customer_limit" label="Kişi başı kullanım" type="number" min="1" :value="$d->per_customer_limit" hint="Aynı e-posta veya telefon" />
                    </div>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-field name="starts_at" label="Başlangıç" type="datetime-local" :value="$local($d->starts_at)" hint="Boş: hemen" />
                        <x-field name="ends_at" label="Bitiş" type="datetime-local" :value="$local($d->ends_at)" hint="Boş: süresiz" />
                    </div>
                    <div x-show="kind === 'coupon'">
                        <x-toggle name="codes_only" label="Yalnızca toplu kodlarla kullanılsın" :checked="$d->codes_only" hint="Ana kod kabul edilmez; müşteriler aşağıda üretilen tek kullanımlık kodları kullanır." />
                    </div>
                    <x-toggle name="first_order_only" label="Yalnızca ilk siparişte geçerli" :checked="$d->first_order_only" hint="Daha önce siparişi olan e-posta veya telefonla kullanılamaz." />
                    <x-toggle name="is_active" label="Etkin" :checked="$d->exists ? $d->is_active : true" />
                </div>
            </x-card>

            @error('code')<p role="alert" class="text-sm text-danger">{{ $message }}</p>@enderror
            <div class="flex justify-end"><x-button icon="check">Kaydet</x-button></div>
        </form>

        @if ($d->exists)
            <div class="space-y-6">
                <x-card title="Son kullanımlar" :padding="false">
                    @if ($recent->isEmpty())
                        <p class="p-5 text-sm text-muted">Henüz kullanılmadı.</p>
                    @else
                        <ul class="divide-y divide-line text-sm">
                            @foreach ($recent as $r)
                                <li class="flex justify-between gap-2 px-5 py-2.5">
                                    <a href="{{ route('store_admin.orders.show', $r->order_id) }}" @class(['text-primary hover:underline', 'line-through opacity-60' => $r->status === 'void'])>{{ $r->order?->order_no }}</a>
                                    <span class="tabular-nums">{{ Money::format($r->amount) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>
                @if ($d->isCoupon())
                    <x-card title="Toplu kodlar" :description="'Her kod bir kez kullanılır ve bu kuponun kurallarını taşır. Üretilen: '.$codeCount.' · kullanılan: '.$usedCodeCount">
                        <form method="POST" action="{{ route('store_admin.discounts.codes', $d) }}" class="space-y-3">
                            @csrf
                            <div class="grid grid-cols-2 gap-3">
                                <x-field name="count" label="Adet" type="number" min="1" max="1000" value="50" required />
                                <x-field name="prefix" label="Önek" maxlength="12" :placeholder="$d->code" />
                            </div>
                            <x-button variant="secondary" class="w-full">Kod üret</x-button>
                        </form>
                        @if ($codeCount)
                            <a href="{{ route('store_admin.discounts.codes.export', $d) }}" class="mt-3 inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline"><x-icon name="download" class="size-4" /> Kodları indir (CSV)</a>
                        @endif
                    </x-card>
                @endif
                @unless ($used)
                    <form method="POST" action="{{ route('store_admin.discounts.destroy', $d) }}" data-confirm="{{ $d->name }} silinsin mi?">
                        @csrf @method('DELETE')
                        <x-button variant="secondary" class="w-full text-danger">İndirimi sil</x-button>
                    </form>
                @endunless
            </div>
        @endif
    </div>
</x-layouts.panel>
