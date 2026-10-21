{{-- Ürün (grup) alanları: $product, $categories, $brands --}}
<div class="grid gap-5 sm:grid-cols-2">
    <x-field name="name" label="Ürün adı" :value="$product->name" required class="sm:col-span-2" maxlength="200" />
    <x-field name="category_id" label="Kategori" :options="['' => 'Kategorisiz'] + $categories" :value="$product->category_id" />
    <x-field name="brand_id" label="Marka" :options="['' => 'Markasız'] + $brands" :value="$product->brand_id" />
    <x-field name="description" label="Açıklama" :value="$product->description" textarea class="sm:col-span-2"
        hint="Ürün sayfasında görünür. Ölçü, içerik, kullanım gibi bilgileri yazın." />
    {{-- x-model: formu saran x-data'daki "option" (yeni üründe ilk seçenek değeri alanını açar) --}}
    <x-field name="variant_name" label="Seçenek adı (isteğe bağlı)" :value="$product->variant_name" maxlength="60" x-model="option"
        hint="Ürün renk, boyut gibi seçeneklerle satılıyorsa yazın (ör. Renk). Tek seçenekli ürünlerde boş bırakın." />
</div>

<details class="mt-6 rounded-lg border border-line" @if ($errors->hasAny(['slug', 'seo_title', 'seo_description'])) open @endif>
    <summary class="cursor-pointer px-4 py-3 text-sm font-medium text-ink">Arama motoru (SEO) ve adres</summary>
    <div class="grid gap-5 border-t border-line p-4 sm:grid-cols-2">
        <x-field name="slug" label="Adres (kısa ad)" :value="$product->slug" maxlength="220"
            hint="Ürün sayfasının adresi: /urun/kisa-ad. Boş bırakılırsa üründen üretilir." class="sm:col-span-2" />
        <x-field name="seo_title" label="SEO başlığı" :value="$product->seo_title" maxlength="160" hint="Boşsa ürün adı kullanılır." />
        <x-field name="seo_description" label="SEO açıklaması" :value="$product->seo_description" maxlength="320" hint="Google sonuçlarında görünen kısa açıklama (150–160 karakter ideal)." />
    </div>
</details>

<div class="mt-6 flex flex-wrap gap-6">
    <x-toggle name="is_published" label="Mağazada yayında" :checked="$product->is_published" hint="Kapalıysa ürün mağazada görünmez." />
    <x-toggle name="is_featured" label="Öne çıkan ürün" :checked="$product->is_featured" hint="Ana sayfadaki öne çıkanlarda gösterilir." />
</div>
