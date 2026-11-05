<x-layouts.panel :area="\App\Domain\Auth\Area::Business" title="Kategoriler">
    <x-page-header title="Kategoriler" subtitle="Ürünlerinizi gruplamak için kategoriler. İstediğiniz kadar alt kategori açabilirsiniz; ürün herhangi bir seviyede durabilir." />

    @foreach (['category'] as $key)
        @error($key)<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror
    @endforeach

    <div class="grid gap-6 lg:grid-cols-3">
        <div>
            @if ($editing)
                <x-card :title="'Düzenle: '.$editing->name">
                    <form method="POST" action="{{ route('business.categories.update', $editing) }}" class="space-y-4">
                        @csrf @method('PUT')
                        <x-field name="name" label="Kategori adı" :value="$editing->name" required />
                        <x-field name="parent_id" label="Üst kategori" :options="['' => 'Yok (ana kategori)'] + $editParents" :value="$editing->parent_id" hint="Taşırsanız alt kategorileri de birlikte taşınır." />
                        <x-field name="sort" label="Sıra" type="number" min="0" :value="$editing->sort" />
                        <label class="flex items-center gap-2 text-sm"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editing->is_active)) class="size-4 rounded"> Aktif</label>
                        <div class="flex gap-2">
                            <x-button>Kaydet</x-button>
                            <x-button variant="ghost" :href="route('business.categories.index')">Vazgeç</x-button>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('business.categories.destroy', $editing) }}" class="mt-4 border-t border-line pt-4" data-confirm="&quot;{{ $editing->name }}&quot; silinsin mi?">
                        @csrf @method('DELETE')
                        <button class="text-sm text-danger hover:underline">Kategoriyi sil</button>
                        <p class="mt-1 text-xs text-muted">Ürünü veya alt kategorisi olan kategori silinemez; pasif yapabilirsiniz.</p>
                    </form>
                </x-card>
            @else
                <x-card title="Yeni kategori">
                    <form method="POST" action="{{ route('business.categories.store') }}" class="space-y-4">
                        @csrf
                        <x-field name="name" label="Kategori adı" required />
                        <x-field name="parent_id" label="Üst kategori" :options="['' => 'Yok (ana kategori)'] + $parents" :value="request('ust')" hint="Boş bırakırsanız ana kategori olur." />
                        <x-field name="sort" label="Sıra" type="number" min="0" value="0" />
                        <x-button icon="plus">Ekle</x-button>
                    </form>
                </x-card>
            @endif
        </div>

        <x-card title="Kategoriler" class="lg:col-span-2" :padding="false">
            @if ($tree->isEmpty())
                <x-empty-state icon="list" title="Henüz kategori yok" text="Ana kategorileri ekleyin; gerekirse altlarına alt kategoriler açın." />
            @else
                <ul class="divide-y divide-line text-sm">@include('business.catalog._category-branch', ['nodes' => $tree])</ul>
            @endif
        </x-card>
    </div>
</x-layouts.panel>
