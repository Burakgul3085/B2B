<x-layouts.panel :area="\App\Domain\Auth\Area::Business" title="Stok hareketi">
    <x-page-header title="Stok hareketi" subtitle="Giriş, çıkış veya düzeltme kaydedin. Kayıt değiştirilemez; hata ters hareketle düzeltilir.">
        <x-slot:breadcrumb><a href="{{ route('business.stock.index') }}" class="hover:underline">Stok Durumu</a></x-slot:breadcrumb>
    </x-page-header>

    <x-card class="max-w-2xl">
        <form method="POST" action="{{ route('business.stock.store') }}" class="space-y-5" x-data="{ type: '{{ old('type', 'increase') }}' }">
            @csrf
            <x-product-picker :product="$product" />
            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="warehouse_id" label="Depo" :options="$warehouses->pluck('name', 'id')->all()" :value="$defaultId" required />
                <x-field name="type" label="Hareket türü" :options="$types" x-model="type" required />
            </div>
            <x-field name="quantity" label="Miktar" inputmode="decimal" required
                hint="Manuel düzeltmede artış için pozitif, azalış için negatif girin (ör. -3)." />
            <x-field name="note" label="Açıklama" textarea x-bind:required="type === 'manual_adjust'" hint="Manuel düzeltmede zorunludur." />
            <div class="flex justify-end gap-2">
                <x-button variant="secondary" :href="$product ? route('business.products.show', $product) : route('business.stock.index')">Vazgeç</x-button>
                <x-button>Kaydet</x-button>
            </div>
        </form>
    </x-card>
</x-layouts.panel>
