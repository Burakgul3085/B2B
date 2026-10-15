<x-layouts.panel :area="\App\Domain\Auth\Area::Business" title="Depolar arası transfer">
    <x-page-header title="Depolar arası transfer" subtitle="Kaynak depodan çıkış ve hedef depoya giriş tek işlemde kaydedilir.">
        <x-slot:breadcrumb><a href="{{ route('business.stock.index') }}" class="hover:underline">Stok Durumu</a></x-slot:breadcrumb>
    </x-page-header>

    @if ($warehouses->count() < 2)
        <x-card>
            <x-empty-state icon="warehouse" title="Transfer için en az iki aktif depo gerekir">
                <x-button :href="route('business.warehouses.index')">Depo ekle</x-button>
            </x-empty-state>
        </x-card>
    @else
        <x-card class="max-w-2xl">
            <form method="POST" action="{{ route('business.stock.transfer.store') }}" class="space-y-5">
                @csrf
                <x-product-picker :product="$product" />
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-field name="from_warehouse_id" label="Kaynak depo" :options="$warehouses->pluck('name', 'id')->all()" required />
                    <x-field name="to_warehouse_id" label="Hedef depo" :options="$warehouses->pluck('name', 'id')->all()" :value="$warehouses->get(1)?->id" required />
                </div>
                <x-field name="quantity" label="Miktar" inputmode="decimal" required />
                <x-field name="note" label="Açıklama" textarea />
                <div class="flex justify-end"><x-button icon="arrows">Transferi kaydet</x-button></div>
            </form>
        </x-card>
    @endif
</x-layouts.panel>
