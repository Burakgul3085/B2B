@php use App\Models\StockMovement; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Business" title="Stok Hareketleri">
    <x-page-header title="Stok Hareketleri" subtitle="Tüm stok giriş ve çıkışlarının değiştirilemez kaydı." />

    <x-card :padding="false">
        <form method="GET" class="grid gap-3 border-b border-line p-4 sm:grid-cols-2 lg:grid-cols-6 lg:items-end">
            @if ($filters['product'] ?? null)<input type="hidden" name="product" value="{{ $filters['product'] }}">@endif
            <x-field name="q" label="Ürün" :value="$filters['q'] ?? ''" placeholder="Ad, kod veya barkod" class="lg:col-span-2" />
            <x-field name="warehouse" label="Depo" :options="['' => 'Tümü'] + $warehouses->all()" :value="$filters['warehouse'] ?? ''" />
            <x-field name="type" label="Hareket" :options="['' => 'Tümü'] + StockMovement::TYPES" :value="$filters['type'] ?? ''" />
            <x-field name="from" label="Başlangıç" type="date" :value="$filters['from'] ?? ''" />
            <div class="flex gap-2">
                <x-field name="to" label="Bitiş" type="date" :value="$filters['to'] ?? ''" class="flex-1" />
                <x-button variant="secondary" class="self-end" aria-label="Filtrele"><x-icon name="search" class="size-4" /></x-button>
            </div>
        </form>
        @if ($filters['product'] ?? null)
            <p class="border-b border-line bg-primary-soft/50 px-4 py-2 text-sm">Tek ürünün hareketleri gösteriliyor. <a href="{{ route('business.stock-movements.index') }}" class="font-medium text-primary hover:underline">Tümünü göster</a></p>
        @endif
        @include('business.inventory._movement-table', ['movements' => $movements, 'showProduct' => true])
        @if ($movements->hasPages())<div class="border-t border-line p-4">{{ $movements->links() }}</div>@endif
    </x-card>
</x-layouts.panel>
