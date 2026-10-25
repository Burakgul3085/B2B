@php
    use App\Domain\Support\Money;
    use App\Models\Store\StoreDiscount;

    $d = $discount;
    $money = fn ($v) => $v === null ? '' : number_format((float) $v, 2, ',', '');
    $local = fn ($t) => $t?->timezone('Europe/Istanbul')->format('Y-m-d\TH:i');
    $kind = old('kind', $copyKind ?? ($d->exists ? ($d->code ? 'coupon' : 'campaign') : 'coupon'));
    $selectedCats = array_map('intval', old('category_ids', $d->category_ids ?? []));
    $isNew = ! $d->exists && ! $copyKind;
    $state = [
        'kind' => $kind,
        'type' => old('type', $d->type),
        'audience' => old('audience', $d->audience ?? 'all'),
        'name' => (string) old('name', $d->name),
        'value' => (string) old('value', $money($d->value)),
        'minSubtotal' => (string) old('min_subtotal', $money($d->min_subtotal)),
        'newDays' => (string) old('new_member_days', $d->new_member_days),
        'products' => $products,
        'categoryCount' => count($selectedCats),
    ];
    $templates = [
        'cart' => ['Sepet tutarına indirim', 'Ör. 1.500 ₺ ve üzeri alışverişlerde %5', 'percent'],
        'products' => ['Seçili ürün / kategori', 'Belirli ürünlerde veya kategorilerde indirim', 'layers'],
        'welcome' => ['Yeni üyeye hoş geldin', 'Yeni üyenin ilk siparişine kendiliğinden', 'gift'],
        'shipping' => ['Ücretsiz kargo', 'Belli tutar üzerine kargo bizden', 'truck'],
        'coupon' => ['Kupon kodu', 'Müşteri sepette kodu girer', 'key'],
    ];
@endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" :title="$d->exists ? $d->name : 'Yeni indirim'">
    <x-page-header :title="$d->exists ? $d->name : 'Yeni indirim'" :subtitle="$d->exists ? $d->summary() : 'Bir şablon seçin veya kupon / otomatik kampanyayı kendiniz tanımlayın.'">
        <x-slot:breadcrumb><a href="{{ route('store_admin.discounts.index') }}" class="hover:underline">Kampanya ve Kuponlar</a></x-slot:breadcrumb>
        @if ($d->exists)
            <x-button :href="route('store_admin.discounts.create', ['kopya' => $d->id])" variant="secondary" icon="copy">Kopyala</x-button>
        @endif
    </x-page-header>

    <form method="POST" enctype="multipart/form-data" action="{{ $d->exists ? route('store_admin.discounts.update', $d) : route('store_admin.discounts.store') }}"
        class="grid gap-6 xl:grid-cols-3" x-data="discountForm(@alpine($state), @alpine(route('store_admin.discounts.lookup')))">
        @csrf @if ($d->exists) @method('PUT') @endif

        <div class="space-y-6 xl:col-span-2">
            @if ($isNew)
                <x-card title="Ne tür bir kampanya?" description="Bir şablon seçin; ilgili alanlar ayarlanır, siz yalnızca tutarları girersiniz.">
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($templates as $key => [$title, $text, $icon])
                            <button type="button" @click="applyTemplate('{{ $key }}')" :class="isTemplate('{{ $key }}') ? 'border-primary ring-2 ring-primary/30' : 'border-line hover:border-primary/50'"
                                class="flex items-start gap-3 rounded-xl border bg-white p-3.5 text-left transition">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"><x-icon :name="$icon" class="size-[18px]" /></span>
                                <span><span class="block text-sm font-semibold text-ink">{{ $title }}</span><span class="block text-xs text-muted">{{ $text }}</span></span>
                            </button>
                        @endforeach
                    </div>
                </x-card>
            @endif

            <x-card title="İndirim">
                <div class="space-y-5">
                    <fieldset class="flex flex-wrap gap-4 text-sm" @if ($used) disabled @endif>
                        <label class="flex items-center gap-2"><input type="radio" name="kind" value="coupon" x-model="kind" class="text-primary"> Kupon (müşteri kodu girer)</label>
                        <label class="flex items-center gap-2"><input type="radio" name="kind" value="campaign" x-model="kind" class="text-primary"> Otomatik kampanya (kodsuz)</label>
                    </fieldset>
                    @if ($used)<input type="hidden" name="kind" value="{{ $kind }}">@endif
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-field name="name" label="Ad" required maxlength="120" :value="$d->name" x-model="name" hint="Kampanyada sepette ve siparişte müşteriye görünür." />
                        <div x-show="kind === 'coupon'">
                            <x-field name="code" label="Kupon kodu" maxlength="40" :value="$d->code" class="uppercase" :readonly="$used" x-ref="focus_coupon"
                                :hint="$used ? 'Kullanılmış kuponun kodu değiştirilemez.' : 'Harf, rakam, - ve _ (ör. HOSGELDIN10).'" />
                        </div>
                    </div>
                    <div class="grid gap-5 sm:grid-cols-3">
                        <x-field name="type" label="Tür" :options="StoreDiscount::TYPES" :value="$d->type" x-model="type" required />
                        <div x-show="type !== 'free_shipping'">
                            <x-field name="value" label="İndirim" inputmode="decimal" :value="$money($d->value)" x-model="value" x-ref="focus_products" hint="Yüzde için 10, tutar için 50,00" />
                        </div>
                        <div x-show="type === 'percent'">
                            <x-field name="max_discount" label="En fazla indirim (₺)" inputmode="decimal" :value="$money($d->max_discount)" hint="Boş: sınırsız" />
                        </div>
                    </div>
                </div>
            </x-card>

            <x-card title="Kapsam" description="Boş bırakılırsa sepetteki tüm ürünlere uygulanır. Seçilirse en düşük tutar ve indirim yalnızca bu ürünler üzerinden hesaplanır.">
                <div class="space-y-5">
                    <fieldset @change="countCategories">
                        <legend class="text-sm font-medium">Kategoriler <span class="font-normal text-muted">(alt kategorileriyle)</span></legend>
                        @if ($categories->isEmpty())
                            <p class="mt-2 text-sm text-muted">Henüz kategori yok.</p>
                        @else
                            <div class="mt-2 max-h-72 space-y-1 overflow-y-auto rounded-lg border border-line p-3">
                                @foreach ($categories as $cat)
                                    <label @class(['flex items-center gap-2 text-sm', 'ml-6' => $cat->depth === 1, 'ml-12' => $cat->depth >= 2, 'font-semibold' => $cat->depth === 0])>
                                        <input type="checkbox" name="category_ids[]" value="{{ $cat->id }}" @checked(in_array($cat->id, $selectedCats, true)) class="size-4 rounded text-primary"> {{ $cat->name }}
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </fieldset>

                    <div>
                        <p class="text-sm font-medium">Ürünler</p>
                        <div class="relative mt-2" @click.outside="open = false">
                            <label for="urun-ara" class="sr-only">Ürün ara</label>
                            <input id="urun-ara" type="search" x-model="q" @input.debounce.300ms="search" @keydown.escape="open = false" autocomplete="off"
                                placeholder="Ürün adı veya kodu yazın…" class="field-input">
                            <div x-show="open" x-cloak class="absolute inset-x-0 top-full z-20 mt-1 max-h-72 overflow-y-auto rounded-lg border border-line bg-white shadow-lg">
                                <template x-if="results.length === 0"><p class="px-3 py-2.5 text-sm text-muted">Ürün bulunamadı.</p></template>
                                <template x-for="p in results" :key="p.id">
                                    <button type="button" @click="add(p)" :disabled="isPicked(p.id)" class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-slate-50 disabled:opacity-50">
                                        <span x-text="p.name"></span><span class="font-mono text-xs text-muted" x-text="p.sku"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                        <ul class="mt-3 flex flex-wrap gap-2">
                            <template x-for="p in products" :key="p.id">
                                <li class="flex items-center gap-1.5 rounded-lg bg-slate-100 py-1 pr-1 pl-2.5 text-sm">
                                    <input type="hidden" name="product_ids[]" :value="p.id">
                                    <span x-text="p.name"></span>
                                    <button type="button" @click="remove(p.id)" class="rounded p-0.5 text-muted hover:bg-slate-200 hover:text-danger" aria-label="Kaldır"><x-icon name="x" class="size-3.5" /></button>
                                </li>
                            </template>
                        </ul>
                        <details class="mt-3 text-sm">
                            <summary class="cursor-pointer text-muted hover:text-ink">Ürün kodlarıyla toplu ekle</summary>
                            <x-field name="product_skus" label="Ürün kodları (SKU)" textarea :value="$skus" class="mt-2"
                                hint="Virgül veya satır başıyla ayırın. Bir seçeneğin kodu girilse de ürünün tüm seçenekleri kapsama girer." />
                        </details>
                        @error('product_skus')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                    </div>
                </div>
            </x-card>

            <x-card title="Kime ve hangi koşulla?">
                <div class="space-y-5">
                    <div class="grid gap-5 sm:grid-cols-3">
                        <x-field name="audience" label="Kime" :options="StoreDiscount::AUDIENCES" :value="$d->audience ?? 'all'" x-model="audience" x-ref="focus_welcome" />
                        <div x-show="audience === 'new_members'">
                            <x-field name="new_member_days" label="Yeni üye süresi (gün)" type="number" min="1" max="3650" :value="$d->new_member_days" x-model="newDays"
                                hint="Üye olduktan sonra kaç gün geçerli. Boş: ilk siparişe kadar." />
                        </div>
                    </div>
                    <p x-show="audience === 'new_members'" class="rounded-lg bg-primary/5 px-3 py-2 text-xs text-ink">
                        Yeni üye indirimi giriş yapmış ve daha önce siparişi olmayan üyeye bir kez uygulanır. Otomatik kampanya seçerseniz kod gerekmez; ilk siparişin sepetine kendiliğinden düşer.
                    </p>
                    <div class="grid gap-5 sm:grid-cols-3">
                        <x-field name="min_subtotal" label="En düşük sepet tutarı (₺)" inputmode="decimal" :value="$money($d->min_subtotal)" x-model="minSubtotal" x-ref="focus_cart"
                            hint="KDV dahil ürün toplamı. Ör. 1500" />
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
                    <x-toggle name="first_order_only" label="Yalnızca ilk siparişte geçerli (üye olmayanlar dahil)" :checked="$d->first_order_only" hint="Daha önce siparişi olan e-posta veya telefonla kullanılamaz." />
                    <x-toggle name="is_active" label="Etkin" :checked="$d->exists ? $d->is_active : true" />
                </div>
            </x-card>

            <x-card title="Vitrinde gösterim">
                <div class="space-y-5">
                    <x-toggle name="show_on_page" label="Kampanyalar sayfasında göster" :checked="$d->show_on_page" hint="Mağazanın üst menüsündeki Kampanyalar sayfasında kart olarak görünür. Toplu kodlu kuponlarda gösterilmez." />
                    <div x-show="kind === 'coupon'">
                        <x-toggle name="show_on_home" label="Ana sayfada kart olarak göster" :checked="$d->show_on_home" hint="Kodlu kuponlarda; ziyaretçi kodu kartın üzerinden kopyalar (en fazla 3 kart)." />
                    </div>
                    <x-field name="description" label="Açıklama" textarea maxlength="500" :value="$d->description" hint="Kampanyalar sayfasındaki kartta görünür (en fazla 500 karakter)." />
                    <div>
                        <x-field name="image" label="Kart görseli" type="file" accept="image/*" hint="İsteğe bağlı. Yatay görsel önerilir; en fazla 6 MB." />
                        @if ($d->image_path)
                            <div class="mt-3 flex items-center gap-4">
                                <img src="{{ route('store_admin.discounts.image', $d) }}" alt="" class="h-20 w-32 rounded-lg border border-line object-cover">
                                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remove_image" value="1" class="size-4 rounded text-primary"> Görseli kaldır</label>
                            </div>
                        @endif
                    </div>
                </div>
            </x-card>

            @error('code')<p role="alert" class="text-sm text-danger">{{ $message }}</p>@enderror
        </div>

        <div class="space-y-6">
            <div class="space-y-4 xl:sticky xl:top-6">
                <x-card title="Müşterinin göreceği">
                    <p class="text-sm font-semibold text-ink" x-text="name || 'Kampanya adı'">{{ $d->name ?: 'Kampanya adı' }}</p>
                    <p class="mt-1 text-sm text-muted" x-text="headline">{{ $d->exists ? $d->summary() : '' }}</p>
                    <div class="mt-4 flex justify-end"><x-button icon="check">Kaydet</x-button></div>
                </x-card>

                @if ($d->exists)
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
                @endif
            </div>
        </div>
    </form>

    @if ($d->exists)
        <div class="mt-6 grid gap-6 xl:grid-cols-3">
            <div class="hidden xl:col-span-2 xl:block"></div>
            <div class="space-y-6">
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
        </div>
    @endif
</x-layouts.panel>
