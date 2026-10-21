<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" title="Markalar">
    <x-page-header title="Markalar" subtitle="Mağazadaki markalar ve logoları. Logolar mağazanın Markalar sayfasında ve ürün sayfalarında görünür." />

    @error('brand')<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card :padding="false" class="lg:col-span-2" title="Markalar" :description="$brands->count().' marka'">
            @if ($brands->isEmpty())
                <x-empty-state icon="layers" title="Henüz marka yok" text="Sağdaki formdan marka ekleyin; Excel aktarımında eksik markalar otomatik de oluşturulabilir." />
            @else
                <ul class="divide-y divide-line">
                    @foreach ($brands as $brand)
                        <li class="flex flex-wrap items-center gap-4 px-5 py-3" x-data="{ edit: false }">
                            <span class="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-line bg-white p-1">
                                @if ($brand->logo_path)
                                    <img src="{{ route('store_admin.brands.logo.show', $brand) }}?v={{ $brand->updated_at?->timestamp }}" alt="{{ $brand->name }} logosu" class="max-h-full max-w-full object-contain">
                                @else
                                    <span class="text-lg font-semibold text-muted">{{ mb_substr($brand->name, 0, 1) }}</span>
                                @endif
                            </span>
                            <div class="min-w-0 flex-1" x-show="! edit">
                                <p class="font-medium text-ink">{{ $brand->name }} @unless ($brand->is_active)<x-badge class="ml-1">Pasif</x-badge>@endunless</p>
                                <p class="text-xs text-muted">{{ $brand->products_count }} ürün</p>
                            </div>
                            <form x-show="edit" x-cloak method="POST" action="{{ route('store_admin.brands.update', $brand) }}" class="flex min-w-0 flex-1 flex-wrap items-center gap-3">
                                @csrf @method('PUT')
                                <input name="name" value="{{ $brand->name }}" required maxlength="120" class="field-input max-w-xs" aria-label="Marka adı">
                                <x-toggle name="is_active" label="Görünür" :checked="$brand->is_active" />
                                <x-button size="sm">Kaydet</x-button>
                            </form>
                            <div class="flex items-center gap-3 text-sm">
                                <button type="button" @click="edit = ! edit" class="font-medium text-primary hover:underline" x-text="edit ? 'Vazgeç' : 'Düzenle'">Düzenle</button>
                                <form method="POST" action="{{ route('store_admin.brands.logo', $brand) }}" enctype="multipart/form-data" x-ref="logo">
                                    @csrf
                                    <label class="cursor-pointer font-medium text-primary hover:underline">
                                        {{ $brand->logo_path ? 'Logoyu değiştir' : 'Logo yükle' }}
                                        <input type="file" name="logo" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="$refs.logo.submit()">
                                    </label>
                                </form>
                                @if ($brand->logo_path)
                                    <form method="POST" action="{{ route('store_admin.brands.logo.destroy', $brand) }}" data-confirm="Logo kaldırılsın mı?">@csrf @method('DELETE')<button class="text-muted hover:underline">Logoyu kaldır</button></form>
                                @endif
                                @if ($brand->products_count === 0)
                                    <form method="POST" action="{{ route('store_admin.brands.destroy', $brand) }}" data-confirm="&quot;{{ $brand->name }}&quot; silinsin mi?">@csrf @method('DELETE')<button class="text-danger hover:underline">Sil</button></form>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        <x-card title="Yeni marka">
            <form method="POST" action="{{ route('store_admin.brands.store') }}" class="space-y-4">
                @csrf
                <x-field name="name" label="Marka adı" required maxlength="120" />
                <x-button icon="plus">Ekle</x-button>
            </form>
            <p class="mt-4 text-xs text-muted">Logo: JPG, PNG veya WEBP, en fazla 2 MB. Kare veya şeffaf arka planlı logolar en iyi görünür.</p>
        </x-card>
    </div>
</x-layouts.panel>
