<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" title="Yeni ürün">
    <x-page-header title="Yeni ürün" subtitle="Ürünü ilk seçeneğiyle oluşturun; görselleri ve diğer seçenekleri (renk, boyut) sonraki sayfada eklersiniz.">
        <x-slot:breadcrumb><a href="{{ route('store_admin.products.index') }}" class="hover:underline">Ürünler</a></x-slot:breadcrumb>
    </x-page-header>

    <form method="POST" action="{{ route('store_admin.products.store') }}" class="space-y-6" x-data="{ option: @alpine(old('variant_name', '')) }">
        @csrf
        <x-card title="Ürün bilgileri">
            @include('store.admin.catalog.products._fields', ['product' => $product])
        </x-card>

        <x-card title="Fiyat ve stok kodu" description="Fiyatlar KDV dahildir. Stok miktarı Stok bölümünden girilecek.">
            {{-- Seçenek adı yazılırsa ilk seçeneğin değeri de istenir --}}
            <div x-show="option.trim() !== ''" x-cloak class="mb-4 max-w-xs">
                <label for="variant_option" class="mb-1.5 block text-sm font-medium text-ink">İlk seçeneğin değeri<span class="text-danger"> *</span></label>
                <input id="variant_option" name="variant[option_value]" value="{{ old('variant.option_value') }}" class="field-input" placeholder="ör. Mavi" maxlength="80">
                @error('variant.option_value')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@enderror
            </div>
            @include('store.admin.catalog.products._variant-fields', ['prefix' => 'variant', 'bag' => 'default', 'variant' => null, 'optionLabel' => null, 'useOld' => true])
        </x-card>

        <div class="flex gap-3">
            <x-button icon="plus">Ürünü oluştur</x-button>
            <x-button :href="route('store_admin.products.index')" variant="ghost">Vazgeç</x-button>
        </div>
    </form>
</x-layouts.panel>
