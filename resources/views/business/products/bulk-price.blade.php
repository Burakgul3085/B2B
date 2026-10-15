<x-layouts.panel :area="\App\Domain\Auth\Area::Business" title="Toplu fiyat güncelleme">
    <x-page-header title="Toplu fiyat güncelleme" subtitle="Filtreye uyan ürünlerin fiyatını yüzde veya tutar olarak değiştirin. Geçmiş siparişler etkilenmez.">
        <x-slot:breadcrumb><a href="{{ route('business.products.index') }}" class="hover:underline">Ürünler</a></x-slot:breadcrumb>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card title="1. Ürünleri seçin" class="lg:col-span-1">
            <form method="GET" class="space-y-4">
                <x-field name="q" label="Ara" :value="$filters['q'] ?? ''" />
                <x-field name="category" label="Kategori" :options="['' => 'Tümü'] + $categories" :value="$filters['category'] ?? ''" />
                <x-field name="brand" label="Marka" :options="['' => 'Tümü'] + $brands->all()" :value="$filters['brand'] ?? ''" />
                <x-field name="status" label="Durum" :options="['' => 'Aktif ve pasif', 'active' => 'Aktif', 'passive' => 'Pasif']" :value="$filters['status'] ?? ''" />
                <x-button variant="secondary">Filtreyi uygula</x-button>
            </form>
        </x-card>

        <x-card title="2. Değişikliği tanımlayın" class="lg:col-span-2">
            <div class="mb-5 rounded-lg bg-primary-soft px-4 py-3 text-sm text-primary">
                Seçili filtreye uyan <b>{{ number_format($matching, 0, ',', '.') }}</b> ürün var. Fiyatı boş olan ürünler atlanır.
            </div>
            <form method="POST" action="{{ route('business.products.price.update', $filters) }}" class="space-y-5">
                @csrf
                <div class="grid gap-5 sm:grid-cols-3">
                    <x-field name="field" label="Hangi fiyat" :options="['list_price' => 'Liste fiyatı', 'dealer_price' => 'Bayi fiyatı', 'purchase_price' => 'Alış fiyatı']" />
                    <x-field name="mode" label="Değişim türü" :options="['percent' => 'Yüzde (%)', 'amount' => 'Tutar (₺)']" />
                    <x-field name="value" label="Değer" type="number" step="0.0001" required hint="Artış için pozitif, indirim için negatif (ör. 10 veya -5)." />
                </div>
                <label class="flex items-start gap-3 text-sm">
                    <input type="checkbox" name="confirm" value="1" class="mt-0.5 size-4 rounded">
                    <span>{{ number_format($matching, 0, ',', '.') }} ürünün fiyatının değişeceğini anladım. Her değişiklik ürün geçmişine kaydedilir.</span>
                </label>
                @error('confirm')<p class="text-sm text-danger">{{ $message }}</p>@enderror
                <x-button :disabled="$matching === 0">Fiyatları güncelle</x-button>
            </form>
        </x-card>
    </div>
</x-layouts.panel>
