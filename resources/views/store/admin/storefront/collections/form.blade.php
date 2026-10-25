<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" :title="$collection->exists ? $collection->title : 'Yeni seçki'">
    <x-page-header :title="$collection->exists ? $collection->title : 'Yeni seçki'">
        <x-slot:breadcrumb><a href="{{ route('store_admin.collections.index') }}" class="hover:underline">Seçkiler</a></x-slot:breadcrumb>
        @if ($collection->exists)<x-button :href="route('store.collection', $collection->slug)" variant="secondary" icon="eye" target="_blank">Mağazada gör</x-button>@endif
    </x-page-header>

    <form method="POST" action="{{ $collection->exists ? route('store_admin.collections.update', $collection) : route('store_admin.collections.store') }}" enctype="multipart/form-data" class="grid gap-6 xl:grid-cols-3">
        @csrf @if ($collection->exists) @method('PUT') @endif
        <div class="space-y-6 xl:col-span-2">
            <x-card>
                <div class="space-y-5">
                    <x-field name="title" label="Başlık" :value="$collection->title" required maxlength="80" placeholder="ör. Okula dönüş" />
                    <x-field name="subtitle" label="Alt başlık" :value="$collection->subtitle" maxlength="160" placeholder="ör. Çanta, defter ve kalemde yeni sezon" />
                    <x-field name="product_skus" label="Ürünler (stok kodları)" :value="$skus" textarea rows="8" class="font-mono"
                        hint="Her satıra veya virgülle ayırarak bir stok kodu (SKU). Ürünler bu sırayla gösterilir; en fazla {{ \App\Models\Store\StoreCollection::MAX_PRODUCTS }} ürün." />
                </div>
            </x-card>
        </div>
        <div class="space-y-6">
            <x-card title="Görünüm">
                <div class="space-y-5">
                    <x-field name="color" label="Renk" :options="['' => 'Turuncu (varsayılan)'] + $colors" :value="$collection->color" />
                    <div>
                        <p class="mb-1.5 text-sm font-medium">Görsel</p>
                        @if ($collection->image_path)
                            <img src="{{ route('store_admin.collections.image', $collection) }}" alt="" class="mb-2 aspect-[4/3] w-full rounded-lg bg-surface object-contain">
                            <x-toggle name="remove_image" label="Görseli kaldır" class="mb-2" />
                        @endif
                        <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="field-input">
                        @error('image')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@else<p class="mt-1.5 text-xs text-muted">Yatay (4:3) fotoğraf; ana sayfa kutusunda ve seçki sayfasında görünür.</p>@enderror
                    </div>
                    <x-toggle name="is_active" label="Yayında" :checked="$collection->exists ? $collection->is_active : true" />
                    <x-button class="w-full justify-center">Kaydet</x-button>
                </div>
            </x-card>
            @if ($collection->exists)
                <x-card>
                    <button form="sil" class="text-sm text-danger hover:underline">Seçkiyi sil</button>
                    <p class="mt-1 text-xs text-muted">Ürünler silinmez; yalnızca seçki kaldırılır.</p>
                </x-card>
            @endif
        </div>
    </form>
    @if ($collection->exists)
        <form id="sil" method="POST" action="{{ route('store_admin.collections.destroy', $collection) }}" data-confirm="Seçki silinsin mi?">@csrf @method('DELETE')</form>
    @endif
</x-layouts.panel>
