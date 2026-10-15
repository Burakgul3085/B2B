@php
    $editing = $product->exists;
    $title = $editing ? 'Ürünü düzenle' : 'Yeni ürün';
    $groups = $variantGroups->mapWithKeys(fn ($g) => [$g->id => $g->attribute_names]);
@endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Business" :title="$title">
    <x-page-header :title="$editing ? $product->name : 'Yeni ürün'" :subtitle="$editing ? 'Ürün bilgilerini düzenleyin.' : 'Zorunlu alanlar * ile işaretlidir. Görsel ve stoku kayıttan sonra ekleyebilirsiniz.'">
        <x-slot:breadcrumb><a href="{{ route('business.products.index') }}" class="hover:underline">Ürünler</a></x-slot:breadcrumb>
    </x-page-header>

    <form method="POST" action="{{ $editing ? route('business.products.update', $product) : route('business.products.store') }}" class="space-y-6">
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
            <p class="mt-4 text-xs text-muted">Kategori veya marka listede yoksa <a href="{{ route('business.catalog.structure') }}" class="text-primary hover:underline">Kategori ve Markalar</a> sayfasından ekleyin.</p>
        </x-card>

        <x-card title="Fiyat ve KDV" description="Alış fiyatı yalnızca sizin panelinizde görünür; bayiye gösterilmez.">
            <div class="grid gap-5 sm:grid-cols-3">
                <x-field name="list_price" label="Liste fiyatı (₺)" type="number" step="0.0001" min="0" :value="$product->list_price" required hint="Genel liste fiyatı." />
                <x-field name="dealer_price" label="Standart bayi fiyatı (₺)" type="number" step="0.0001" min="0" :value="$product->dealer_price" hint="Boşsa bayiye liste fiyatı uygulanır." />
                <x-field name="purchase_price" label="Alış fiyatı (₺)" type="number" step="0.0001" min="0" :value="$product->purchase_price" hint="Stok değeri hesabında kullanılır." />
                <x-field name="tax_rate_id" label="KDV oranı" :options="$taxRates->all()" :value="$product->tax_rate_id" required />
                <x-toggle name="price_includes_tax" label="Fiyatlar KDV dahil" :checked="$product->price_includes_tax" hint="İşaretli değilse girilen fiyatlar KDV hariçtir." class="sm:col-span-2 sm:self-end" />
            </div>
        </x-card>

        <x-card title="Birim ve sipariş kuralları" description="Bayi siparişlerinde miktar bu kurallara göre doğrulanır.">
            <div class="grid gap-5 sm:grid-cols-3">
                <x-field name="unit_id" label="Birim" :options="$units->pluck('name', 'id')->all()" :value="$product->unit_id" required />
                <x-field name="pack_quantity" label="Koli / paket içi miktar" type="number" step="0.001" min="0.001" :value="$product->pack_quantity" required />
                <x-field name="critical_stock_level" label="Kritik stok seviyesi" type="number" step="0.001" min="0" :value="$product->critical_stock_level" required hint="Bu seviyede ve altında uyarı verilir." />
                <x-field name="min_order_qty" label="Minimum sipariş miktarı" type="number" step="0.001" min="0.001" :value="$product->min_order_qty" required />
                <x-field name="order_step" label="Sipariş artış adımı" type="number" step="0.001" min="0.001" :value="$product->order_step" required hint="Ör. 12 ise bayi 12, 24, 36… sipariş verebilir." />
            </div>
        </x-card>

        @if ($variantGroups->isNotEmpty())
            <x-card title="Varyant" description="Renk, boyut gibi seçenekleri olan ürünleri aynı grupta toplayın. Her varyant kendi kodu, stoğu ve fiyatı olan ayrı üründür."
                x-data="{ group: '{{ old('variant_group_id', $product->variant_group_id) }}', groups: {{ Js::from($groups) }} }">
                <div class="grid gap-5 sm:grid-cols-3">
                    <div>
                        <label for="f_variant_group_id" class="mb-1.5 block text-sm font-medium">Varyant grubu</label>
                        <select id="f_variant_group_id" name="variant_group_id" x-model="group" class="field-input">
                            <option value="">Varyant yok</option>
                            @foreach ($variantGroups as $g)<option value="{{ $g->id }}">{{ $g->name }}</option>@endforeach
                        </select>
                    </div>
                    <template x-for="(attr, i) in (groups[group] || [])" :key="group + i">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium" x-text="attr"></label>
                            <input type="text" :name="'variant_values[' + attr + ']'" class="field-input" maxlength="60"
                                :value="{{ Js::from(old('variant_values', $product->variant_values ?? [])) }}[attr] ?? ''">
                        </div>
                    </template>
                </div>
            </x-card>
        @endif

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
