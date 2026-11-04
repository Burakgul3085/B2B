@php
    $editing = $product->exists;
    $title = $editing ? 'Ürünü düzenle' : 'Yeni ürün';
@endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Business" :title="$title">
    <x-page-header :title="$editing ? $product->name : 'Yeni ürün'" :subtitle="$editing ? 'Ürün bilgilerini düzenleyin.' : 'Zorunlu alanlar * ile işaretlidir. Görselleri, stoğu ve renk gibi diğer seçenekleri de bu sayfada girebilirsiniz.'">
        <x-slot:breadcrumb><a href="{{ route('business.products.index') }}" class="hover:underline">Ürünler</a></x-slot:breadcrumb>
    </x-page-header>

    <form method="POST" action="{{ $editing ? route('business.products.update', $product) : route('business.products.store') }}" class="space-y-6" @unless ($editing) enctype="multipart/form-data" @endunless>
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-card title="Temel bilgiler">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="name" label="Ürün adı" :value="$product->name" required class="sm:col-span-2" />
                <x-field name="sku" label="Ürün kodu (SKU)" :value="$product->sku" required hint="İşletmeniz içinde benzersiz olmalıdır." />
                <x-field name="barcode" label="Barkod" :value="$product->barcode" inputmode="numeric" />
                <x-field name="category_id" label="Kategori" :options="['' => 'Seçilmedi'] + $categories" :value="$product->category_id" />
                <x-field name="brand_id" label="Marka" :options="['' => 'Seçilmedi'] + $brands->all()" :value="$product->brand_id" />
                <x-field name="model" label="Model" :value="$product->model" />
                <x-field name="description" label="Açıklama" :value="$product->description" textarea class="sm:col-span-2" />
            </div>
            <p class="mt-4 text-xs text-muted">Kategori veya marka listede yoksa <a href="{{ route('business.categories.index') }}" class="text-primary hover:underline">Kategoriler</a> veya <a href="{{ route('business.brands.index') }}" class="text-primary hover:underline">Markalar</a> sayfasından ekleyin.</p>
        </x-card>

        @php $hasSale = (bool) old('has_sale', $product->dealer_sale_price !== null); @endphp
        <x-card title="Fiyat ve KDV" description="Bayiye tek fiyat gider: bayi fiyatı. İndirim açıksa tarihler arasında indirimli fiyat uygulanır.">
            <div class="grid gap-5 sm:grid-cols-3">
                <x-field name="list_price" label="Bayi fiyatı (₺)" type="number" step="0.0001" min="0" :value="$product->exists ? $product->regularDealerPrice() : $product->list_price" required hint="Bayinin ödediği birim fiyat." />
                <x-field name="purchase_price" label="Alış fiyatı (₺)" type="number" step="0.0001" min="0" :value="$product->purchase_price" hint="Yalnızca siz görürsünüz; kâr ve stok değeri için. İsteğe bağlı." />
                <x-field name="tax_rate_id" label="KDV oranı" :options="$taxRates->all()" :value="$product->tax_rate_id" required />
                <x-toggle name="price_includes_tax" label="Fiyatlar KDV dahil" :checked="$product->price_includes_tax" hint="İşaretli değilse girilen fiyatlar KDV hariçtir." class="sm:col-span-3" />
            </div>
            <div class="mt-6 border-t border-line pt-5" x-data="{ sale: @alpine($hasSale) }">
                <input type="hidden" name="has_sale" value="0">
                <label class="flex cursor-pointer items-start gap-3">
                    <input type="checkbox" name="has_sale" value="1" x-model="sale" class="mt-0.5 size-4 rounded" @checked($hasSale)>
                    <span>
                        <span class="block text-sm font-semibold text-ink">İndirim uygula</span>
                        <span class="block text-xs text-muted">Belirli tarihlerde tüm bayilere daha düşük fiyat. Bayi eski fiyatı üstü çizili görür; ürün "İndirimdeki ürünler"de listelenir.</span>
                    </span>
                </label>
                <div class="mt-4 grid gap-5 sm:grid-cols-3" x-show="sale" x-cloak>
                    <x-field name="dealer_sale_price" label="İndirimli bayi fiyatı (₺)" type="number" step="0.0001" min="0" :value="$product->dealer_sale_price" hint="Bayi fiyatından düşük olmalı." x-bind:required="sale" />
                    <x-field name="dealer_sale_starts_on" label="Başlangıç" type="date" :value="$product->dealer_sale_starts_on?->toDateString()" hint="Boş: hemen." />
                    <x-field name="dealer_sale_ends_on" label="Bitiş" type="date" :value="$product->dealer_sale_ends_on?->toDateString()" hint="Boş: süresiz. Bitiş günü dahil." />
                </div>
            </div>
        </x-card>

        @php
            $unitName = mb_strtolower($product->unit?->name ?? 'adet', 'UTF-8');
            $qty = fn ($v) => rtrim(rtrim((string) $v, '0'), '.');
            $mode = old('sale_mode', $product->sale_mode ?? 'single');
            $mode = is_string($mode) && isset(\App\Models\Product::SALE_MODES[$mode]) ? $mode : 'single';
        @endphp
        <x-card title="Satış şekli" description="Fiyat ve stok her zaman {{ $unitName }} başınadır; koli yalnızca bayinin nasıl sipariş vereceğini belirler.">
            <div x-data="{ mode: @alpine($mode) }" class="space-y-5">
                <fieldset>
                    <legend class="sr-only">Satış şekli</legend>
                    <div class="grid gap-3 sm:grid-cols-3">
                        @foreach (['single' => 'Bayi istediği kadar alır (1, 2, 3…).', 'case' => 'Bayi yalnızca tam koli alır.', 'custom' => 'Ör. en az 15, 15\'er artar.'] as $value => $hint)
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-line p-3 has-[:checked]:border-brand has-[:checked]:bg-brand/5">
                                <input type="radio" name="sale_mode" value="{{ $value }}" x-model="mode" class="mt-1" @checked($mode === $value)>
                                <span>
                                    <span class="block text-sm font-medium">{{ \App\Models\Product::SALE_MODES[$value] }}</span>
                                    <span class="block text-xs text-muted">{{ $hint }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('sale_mode')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@enderror
                </fieldset>

                <div class="grid gap-5 sm:grid-cols-3">
                    <div x-show="mode === 'case'">
                        <x-field name="pack_quantity" label="Koli içi adet" type="number" step="1" min="2" :value="$qty($product->pack_quantity)" required x-bind:disabled="mode !== 'case'" hint="Bayi bu sayının katlarıyla sipariş verir." />
                    </div>
                    <div x-show="mode === 'case'">
                        <x-field name="min_cases" label="En az koli" type="number" step="1" min="1" :value="$product->minCases()" required x-bind:disabled="mode !== 'case'" />
                    </div>
                    <div x-show="mode !== 'case'">
                        <x-field name="min_order_qty" label="En az sipariş ({{ $unitName }})" type="number" step="1" min="1" :value="$qty($product->min_order_qty)" required x-bind:disabled="mode === 'case'" hint="Tek tek satışta genelde 1." />
                    </div>
                    <div x-show="mode === 'custom'">
                        <x-field name="order_step" label="Artış ({{ $unitName }})" type="number" step="1" min="1" :value="$qty($product->order_step)" required x-bind:disabled="mode !== 'custom'" hint="Ör. 15 ise bayi 15, 30, 45… sipariş verebilir. En az sipariş bunun katı olmalı." />
                    </div>
                    <x-field name="critical_stock_level" label="Kritik stok seviyesi ({{ $unitName }})" type="number" step="1" min="0" :value="$qty($product->critical_stock_level)" required hint="Bu seviyede ve altında uyarı verilir." />
                </div>
            </div>
        </x-card>

        <x-card title="Seçenek" description="Ürünün renk, boyut gibi seçenekleri varsa doldurun. Her seçenek kendi kodu, stoğu ve fiyatı olan ayrı üründür; bayi ürün sayfasında seçenekler arasında geçer.">
            @if ($optionOf)
                <input type="hidden" name="option_of" value="{{ $optionOf->id }}">
                <p class="mb-4 rounded-lg bg-primary/5 px-3 py-2.5 text-sm">“<a href="{{ route('business.products.show', $optionOf) }}" class="font-medium text-primary hover:underline">{{ $optionOf->name }}</a>” ürününe yeni seçenek ekliyorsunuz. Bilgiler ondan kopyalandı; ürün kodu, barkod ve seçenek değerini girin.</p>
            @endif
            <div class="space-y-5" x-data="{ optionName: @alpine((string) old('option_name', $product->option_name ?? '')) }">
            <div class="grid gap-5 sm:grid-cols-3">
                <x-field name="option_name" label="Seçenek adı" :value="$product->option_name" maxlength="40" placeholder="Ör. Renk" :required="(bool) $optionOf" hint="Tek seçenekli üründe boş bırakın." x-model="optionName" />
                <x-field name="option_value" :label="$optionOf ? 'Yeni seçeneğin değeri' : 'Bu ürünün değeri'" :value="$product->option_value" maxlength="60" placeholder="Ör. Kırmızı" :required="(bool) ($optionOf || $product->variant_group_id)" />
                @if ($optionOf && blank($optionOf->option_value))
                    <x-field name="source_option_value" label="“{{ \Illuminate\Support\Str::limit($optionOf->name, 30) }}” ürününün değeri" maxlength="60" placeholder="Ör. Mavi" required hint="Mevcut ürünün seçeneği (bir kez sorulur)." />
                @endif
            </div>
            @if (! $editing && ! $optionOf)
                @include('business.products._option-rows')
            @endif
            </div>
        </x-card>

        @unless ($editing)
            @include('business.products._create-extras')
        @endunless

        <x-card title="Satış durumu">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-toggle name="is_active" label="Aktif" :checked="$product->is_active" hint="Pasif ürün siparişe kapalıdır." />
                <x-toggle name="visible_to_dealers" label="Bayilere görünür" :checked="$product->visible_to_dealers" hint="İşaretsizse ürün bayi kataloğunda gösterilmez." />
            </div>
        </x-card>

        <div class="flex justify-end gap-2">
            <x-button variant="secondary" :href="$editing ? route('business.products.show', $product) : route('business.products.index')">Vazgeç</x-button>
            <x-button>{{ $editing ? 'Değişiklikleri kaydet' : 'Ürünü kaydet' }}</x-button>
        </div>
    </form>
</x-layouts.panel>
