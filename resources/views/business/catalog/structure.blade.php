<x-layouts.panel :area="\App\Domain\Auth\Area::Business" title="Kategori ve Markalar">
    <x-page-header title="Kategori ve Markalar" subtitle="Ürünlerinizi düzenlemek için kullanılan yapılar." />

    @foreach (['category', 'brand', 'logo'] as $key)
        @error($key)<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror
    @endforeach

    <nav class="mb-6 flex gap-1 border-b border-line" aria-label="Sekmeler">
        @foreach (['categories' => 'Kategoriler', 'brands' => 'Markalar', 'variants' => 'Varyant grupları'] as $key => $label)
            <a href="{{ route('business.catalog.structure', ['tab' => $key]) }}" @class([
                '-mb-px border-b-2 px-4 py-2.5 text-sm font-medium',
                'border-primary text-primary' => $tab === $key,
                'border-transparent text-muted hover:text-ink' => $tab !== $key,
            ])>{{ $label }}</a>
        @endforeach
    </nav>

    @if ($tab === 'categories')
        <div class="grid gap-6 lg:grid-cols-3">
            <x-card title="Yeni kategori">
                <form method="POST" action="{{ route('business.categories.store') }}" class="space-y-4">
                    @csrf
                    <x-field name="name" label="Kategori adı" required />
                    <x-field name="parent_id" label="Üst kategori" :options="['' => 'Yok (ana kategori)'] + $categories->pluck('name', 'id')->all()" hint="En fazla iki seviye: ana ve alt kategori." />
                    <x-field name="sort" label="Sıra" type="number" min="0" value="0" />
                    <x-button icon="plus">Ekle</x-button>
                </form>
            </x-card>

            <x-card title="Kategoriler" class="lg:col-span-2" :padding="false">
                @if ($categories->isEmpty())
                    <x-empty-state icon="list" title="Henüz kategori yok" text="Ana kategorileri ekleyin; gerekirse alt kategoriler oluşturun." />
                @else
                    <ul class="divide-y divide-line text-sm">
                        @foreach ($categories as $category)
                            @include('business.catalog._category-row', ['category' => $category, 'level' => 0])
                            @foreach ($category->children as $child)
                                @include('business.catalog._category-row', ['category' => $child, 'level' => 1])
                            @endforeach
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>
    @elseif ($tab === 'brands')
        <div class="grid gap-6 lg:grid-cols-3">
            <x-card title="Yeni marka">
                <form method="POST" action="{{ route('business.brands.store') }}" class="space-y-4">
                    @csrf
                    <x-field name="name" label="Marka adı" required />
                    <x-button icon="plus">Ekle</x-button>
                </form>
            </x-card>
            <x-card title="Markalar" class="lg:col-span-2" :padding="false">
                @if ($brands->isEmpty())
                    <x-empty-state icon="list" title="Henüz marka yok" />
                @else
                    <ul class="divide-y divide-line text-sm">
                        @foreach ($brands as $brand)
                            <li class="flex flex-wrap items-center gap-3 px-5 py-3" x-data="{ edit: false, logo: false }">
                                {{-- Logo: bayi Markalar sayfasında görünür --}}
                                <button type="button" @click="logo = !logo" title="Logoyu değiştir"
                                    class="flex h-11 w-20 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-line bg-white p-1.5 transition hover:border-primary hover:ring-2 hover:ring-primary/15">
                                    @if ($brand->logo_path)
                                        <img src="{{ route('business.brands.logo', $brand) }}?v={{ $brand->updated_at?->timestamp }}" alt="{{ $brand->name }} logosu" class="max-h-full max-w-full object-contain" loading="lazy">
                                    @else
                                        <span class="flex flex-col items-center text-[10px] text-muted"><x-icon name="image" class="size-4" />Logo ekle</span>
                                    @endif
                                </button>
                                <div class="flex-1" x-show="!edit">
                                    <span class="font-medium">{{ $brand->name }}</span>
                                    <span class="text-muted">· {{ $brand->products_count }} ürün</span>
                                    @unless ($brand->is_active)<x-badge class="ml-1">Pasif</x-badge>@endunless
                                </div>
                                <form x-cloak x-show="edit" method="POST" action="{{ route('business.brands.update', $brand) }}" class="flex flex-1 flex-wrap items-center gap-2">
                                    @csrf @method('PUT')
                                    <input name="name" value="{{ $brand->name }}" class="field-input max-w-60" required maxlength="120" aria-label="Marka adı">
                                    <label class="flex items-center gap-1.5"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($brand->is_active) class="size-4 rounded"> Aktif</label>
                                    <x-button size="sm">Kaydet</x-button>
                                </form>
                                <button type="button" @click="edit = !edit" class="text-primary hover:underline" x-text="edit ? 'Vazgeç' : 'Düzenle'"></button>
                                @if ($brand->products_count === 0)
                                    <form method="POST" action="{{ route('business.brands.destroy', $brand) }}" data-confirm="Marka silinsin mi?">@csrf @method('DELETE')<button class="text-danger hover:underline">Sil</button></form>
                                @endif
                                <div x-cloak x-show="logo" class="flex w-full flex-wrap items-center gap-3 rounded-lg bg-slate-50 px-3 py-3">
                                    <form method="POST" action="{{ route('business.brands.logo.update', $brand) }}" enctype="multipart/form-data" class="flex flex-1 flex-wrap items-center gap-2">
                                        @csrf
                                        <input type="file" name="logo" accept="image/jpeg,image/png,image/webp" required aria-label="{{ $brand->name }} logosu"
                                            class="min-w-0 flex-1 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-white file:px-3 file:py-1.5 file:text-sm file:font-medium file:shadow-xs">
                                        <x-button size="sm" icon="upload">Yükle</x-button>
                                    </form>
                                    @if ($brand->logo_path)
                                        <form method="POST" action="{{ route('business.brands.logo.destroy', $brand) }}" data-confirm="Logo kaldırılsın mı?">@csrf @method('DELETE')<button class="text-sm text-danger hover:underline">Logoyu kaldır</button></form>
                                    @endif
                                    <p class="w-full text-xs text-muted">JPG, PNG veya WEBP; en fazla 2 MB. Şeffaf arka planlı, yatay logolar en iyi görünür (ör. 400×160).</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>
    @else
        <div class="grid gap-6 lg:grid-cols-3">
            <x-card title="Yeni varyant grubu" description="Ör. ad: “Jel Kalem 0.7”, özellikler: “Renk”.">
                <form method="POST" action="{{ route('business.variant-groups.store') }}" class="space-y-4">
                    @csrf
                    <x-field name="name" label="Grup adı" required />
                    <x-field name="attributes" label="Özellikler" required hint="Virgülle ayırın, en fazla üç (ör. Renk, Boyut)." />
                    <x-button icon="plus">Ekle</x-button>
                </form>
            </x-card>
            <x-card title="Varyant grupları" class="lg:col-span-2" :padding="false">
                @if ($variantGroups->isEmpty())
                    <x-empty-state icon="layers" title="Varyant grubu yok" text="Renk veya boyut seçenekleri olan ürünleriniz varsa bir grup oluşturun; ardından ürün formunda bu grubu seçin." />
                @else
                    <ul class="divide-y divide-line text-sm">
                        @foreach ($variantGroups as $group)
                            <li class="px-5 py-3" x-data="{ edit: false }">
                                <div class="flex items-center gap-3" x-show="!edit">
                                    <span class="flex-1"><span class="font-medium">{{ $group->name }}</span> <span class="text-muted">· {{ implode(', ', $group->attribute_names) }} · {{ $group->products_count }} ürün</span></span>
                                    <button type="button" @click="edit = true" class="text-primary hover:underline">Düzenle</button>
                                </div>
                                <form x-cloak x-show="edit" method="POST" action="{{ route('business.variant-groups.update', $group) }}" class="flex flex-wrap items-center gap-2">
                                    @csrf @method('PUT')
                                    <input name="name" value="{{ $group->name }}" class="field-input max-w-60" required aria-label="Grup adı">
                                    <input name="attributes" value="{{ implode(', ', $group->attribute_names) }}" class="field-input max-w-60" required aria-label="Özellikler">
                                    <x-button size="sm">Kaydet</x-button>
                                    <button type="button" @click="edit = false" class="text-muted hover:underline">Vazgeç</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>
    @endif
</x-layouts.panel>
