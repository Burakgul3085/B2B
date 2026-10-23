@php use App\Models\Store\StoreCategory; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" title="Kategoriler">
    <x-page-header title="Kategoriler" :subtitle="'Mağaza menüsü ve filtreleri bu ağaçtan oluşur. En fazla '.StoreCategory::MAX_DEPTH.' seviye.'" />

    @error('category')<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror

    <div class="grid gap-6 lg:grid-cols-5">
        <x-card title="Kategori ağacı" :padding="false" class="lg:col-span-3">
            @if ($tree->isEmpty())
                <x-empty-state icon="list" title="Henüz kategori yok" text="Sağdaki formdan ilk kategoriyi ekleyin (ör. Kalemler, Defterler)." />
            @else
                <ul class="divide-y divide-line">@include('store.admin.catalog._category-branch', ['nodes' => $tree, 'depth' => 0])</ul>
            @endif
        </x-card>

        <div class="space-y-6 lg:col-span-2">
            @if ($editing)
                <x-card :title="'Düzenle: '.$editing->name">
                    <form method="POST" action="{{ route('store_admin.categories.update', $editing) }}" enctype="multipart/form-data" class="space-y-4">
                        @csrf @method('PUT')
                        <x-field name="name" label="Kategori adı" :value="$editing->name" required maxlength="120" />
                        <x-field name="parent_id" label="Üst kategori" :options="['' => 'Ana kategori (en üst)'] + $editParents" :value="$editing->parent_id" />
                        <x-field name="description" label="Açıklama" :value="$editing->description" textarea hint="Kategori sayfasının üstünde görünür." />
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-field name="color" label="Renk" :options="['' => 'Otomatik (sırayla)'] + \App\Domain\Store\Storefront\Palette::COLORS" :value="$editing->color"
                                hint="Logodaki yapboz renkleri; menüde ve ana sayfa kutusunda kullanılır." />
                            <x-field name="tagline" label="Kısa tanıtım" :value="$editing->tagline" maxlength="120" placeholder="ör. Defter, ajanda ve bloknotlar" />
                        </div>
                        <div>
                            <p class="mb-1.5 text-sm font-medium text-ink">Kategori görseli</p>
                            @if ($editing->image_path)
                                <img src="{{ route('store_admin.categories.image', $editing) }}" alt="" class="mb-2 h-24 w-auto rounded-lg object-cover">
                                <x-toggle name="remove_image" label="Görseli kaldır" class="mb-2" />
                            @endif
                            <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="field-input">
                            @error('image')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@else<p class="mt-1.5 text-xs text-muted">Ana kategoriler için; arka planı sade ürün fotoğrafı (kare veya yatay).</p>@enderror
                        </div>
                        <x-field name="slug" label="Adres (kısa ad)" :value="$editing->slug" maxlength="160" hint="/kategori/kisa-ad" />
                        <x-field name="seo_title" label="SEO başlığı" :value="$editing->seo_title" maxlength="160" />
                        <x-field name="seo_description" label="SEO açıklaması" :value="$editing->seo_description" maxlength="320" />
                        <x-toggle name="is_active" label="Mağazada görünür" :checked="$editing->is_active" />
                        <div class="flex gap-2">
                            <x-button>Kaydet</x-button>
                            <x-button variant="ghost" :href="route('store_admin.categories.index')">Vazgeç</x-button>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('store_admin.categories.destroy', $editing) }}" class="mt-4 border-t border-line pt-4" data-confirm="&quot;{{ $editing->name }}&quot; silinsin mi?">
                        @csrf @method('DELETE')
                        <button class="text-sm text-danger hover:underline">Kategoriyi sil</button>
                        <p class="mt-1 text-xs text-muted">Ürünü veya alt kategorisi olan kategori silinemez; pasif yapabilirsiniz.</p>
                    </form>
                </x-card>
            @else
                <x-card title="Yeni kategori">
                    <form method="POST" action="{{ route('store_admin.categories.store') }}" class="space-y-4">
                        @csrf
                        <x-field name="name" label="Kategori adı" required maxlength="120" />
                        <x-field name="parent_id" label="Üst kategori" :options="['' => 'Ana kategori (en üst)'] + $parents" :value="request('ust')" />
                        <x-button icon="plus">Ekle</x-button>
                    </form>
                </x-card>
            @endif
        </div>
    </div>
</x-layouts.panel>
