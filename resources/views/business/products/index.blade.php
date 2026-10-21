@php use App\Domain\Inventory\Quantity; use App\Domain\Support\Money; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Business" title="Ürünler">
    <x-page-header title="Ürünler" subtitle="Ürün bilgileri, fiyatlar ve satış durumu.">
        @if ($currentBusiness->moduleEnabled('import'))
            <x-button variant="secondary" icon="upload" :href="route('business.products.import')">İçe aktar</x-button>
        @endif
        <x-button variant="secondary" icon="percent" :href="route('business.products.price', request()->only(['q', 'category', 'brand', 'status', 'stock']))">Toplu fiyat</x-button>
        <x-button icon="plus" :href="route('business.products.create')">Yeni ürün</x-button>
    </x-page-header>

    @if (! $hasAnyProduct)
        <x-card>
            <x-empty-state icon="package" title="Henüz ürün yok" text="İlk ürününüzü ekleyin veya Excel şablonuyla ürünlerinizi toplu olarak aktarın.">
                <div class="flex flex-wrap justify-center gap-2">
                    <x-button icon="plus" :href="route('business.products.create')">Ürün ekle</x-button>
                    @if ($currentBusiness->moduleEnabled('import'))
                        <x-button variant="secondary" icon="upload" :href="route('business.products.import')">Excel ile aktar</x-button>
                    @endif
                </div>
            </x-empty-state>
        </x-card>
    @else
        <x-card :padding="false" x-data="{ selected: [], all: false, preview: null }" @keydown.escape.window="preview = null">
            <form method="GET" class="grid gap-3 border-b border-line p-4 sm:grid-cols-2 lg:grid-cols-6 lg:items-end">
                <x-field name="q" label="Ara" :value="$filters['q'] ?? ''" placeholder="Ad, kod veya barkod" class="lg:col-span-2" />
                <x-field name="category" label="Kategori" :options="['' => 'Tümü'] + $categories" :value="$filters['category'] ?? ''" />
                <x-field name="brand" label="Marka" :options="['' => 'Tümü'] + $brands->all()" :value="$filters['brand'] ?? ''" />
                <x-field name="status" label="Durum" :options="['' => 'Aktif ve pasif', 'active' => 'Aktif', 'passive' => 'Pasif', 'archived' => 'Arşiv']" :value="$filters['status'] ?? ''" />
                <div class="flex gap-2">
                    <x-field name="stock" label="Stok" :options="['' => 'Tümü', 'critical' => 'Kritik', 'out' => 'Stokta yok']" :value="$filters['stock'] ?? ''" class="flex-1" />
                    <x-button variant="secondary" class="self-end" aria-label="Filtrele"><x-icon name="search" class="size-4" /></x-button>
                </div>
            </form>

            @if ($products->isEmpty())
                <x-empty-state icon="search" title="Filtrelere uyan ürün yok">
                    <x-button variant="secondary" :href="route('business.products.index')">Filtreleri temizle</x-button>
                </x-empty-state>
            @else
                <form method="POST" action="{{ route('business.products.bulk') }}" id="bulk-form">
                    @csrf
                    <div x-cloak x-show="selected.length" class="flex flex-wrap items-center gap-3 border-b border-line bg-primary-soft/50 px-4 py-2.5 text-sm">
                        <span><b x-text="selected.length"></b> ürün seçildi</span>
                        <button name="action" value="activate" class="font-medium text-primary hover:underline">Aktif yap</button>
                        <button name="action" value="deactivate" class="font-medium text-primary hover:underline">Pasif yap</button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table-base">
                            <thead>
                                <tr>
                                    <th class="w-10"><input type="checkbox" class="size-4 rounded" aria-label="Tümünü seç" x-model="all" @change="selected = all ? @js($products->pluck('id')) : []"></th>
                                    <th><x-sort-link column="name" label="Ürün" :filters="$filters" /></th>
                                    <th class="hidden md:table-cell">Kategori / Marka</th>
                                    <th class="text-right"><x-sort-link column="list_price" label="Liste fiyatı" :filters="$filters" /></th>
                                    <th class="hidden text-right sm:table-cell">Bayi fiyatı</th>
                                    <th class="text-right"><x-sort-link column="stock" label="Stok" :filters="$filters" /></th>
                                    <th>Durum</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($products as $product)
                                    @php $stock = (string) ($product->total_stock ?? '0'); @endphp
                                    <tr>
                                        <td><input type="checkbox" name="ids[]" value="{{ $product->id }}" x-model.number="selected" class="size-4 rounded" aria-label="{{ $product->name }} seç"></td>
                                        <td>
                                            <div class="flex items-center gap-3">
                                                @if ($product->primaryImage)
                                                    {{-- Küçük görsele tıklanınca büyük önizleme açılır --}}
                                                    <button type="button" class="group/thumb relative flex size-10 shrink-0 cursor-zoom-in items-center justify-center overflow-hidden rounded-lg border border-line bg-white p-0.5 transition hover:border-primary hover:ring-2 hover:ring-primary/20"
                                                        @click="preview = {{ Js::from(['src' => route('business.images.show', $product->primaryImage), 'name' => $product->name, 'sku' => $product->sku, 'url' => route('business.products.show', $product)]) }}"
                                                        aria-label="{{ $product->name }} görselini büyüt">
                                                        <img src="{{ route('business.images.show', $product->primaryImage) }}" alt="" class="max-h-full max-w-full object-contain" loading="lazy">
                                                        <span class="absolute inset-0 flex items-center justify-center bg-slate-900/40 text-white opacity-0 transition group-hover/thumb:opacity-100"><x-icon name="search" class="size-4" /></span>
                                                    </button>
                                                @else
                                                    <div class="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-line bg-slate-50">
                                                        <x-icon name="image" class="size-4 text-slate-300" />
                                                    </div>
                                                @endif
                                                <div class="min-w-0">
                                                    <a href="{{ route('business.products.show', $product) }}" class="font-medium text-ink hover:text-primary">{{ $product->name }}</a>
                                                    <p class="text-xs text-muted">{{ $product->sku }}@if ($product->variantLabel()) · {{ $product->variantLabel() }}@endif</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="hidden text-muted md:table-cell">
                                            {{ $product->category?->fullName() ?? '—' }}<br><span class="text-xs">{{ $product->brand?->name }}</span>
                                        </td>
                                        <td class="text-right tabular-nums">{{ Money::formatUnit($product->list_price) }}</td>
                                        <td class="hidden text-right tabular-nums sm:table-cell">{{ $product->dealer_price !== null ? Money::formatUnit($product->dealer_price) : '—' }}</td>
                                        <td class="text-right tabular-nums">
                                            <span @class([
                                                'font-medium',
                                                'text-danger' => bccomp($stock, '0', 3) <= 0,
                                                'text-warning' => bccomp($stock, '0', 3) > 0 && bccomp($stock, (string) $product->critical_stock_level, 3) <= 0,
                                            ])>{{ Quantity::format($stock) }}</span>
                                            <span class="text-xs text-muted">{{ $product->unit->name }}</span>
                                        </td>
                                        <td>
                                            @if ($product->isArchived())<x-badge>Arşiv</x-badge>
                                            @elseif ($product->is_active)<x-badge tone="success">Aktif</x-badge>
                                            @else<x-badge tone="warning">Pasif</x-badge>@endif
                                            @if (! $product->visible_to_dealers && ! $product->isArchived())<x-badge class="mt-1">Bayiye gizli</x-badge>@endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </form>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line p-4 text-sm text-muted">
                    <span>Toplam {{ number_format($products->total(), 0, ',', '.') }} ürün</span>
                    {{ $products->links() }}
                </div>
            @endif

            {{-- Görsel önizleme --}}
            <template x-teleport="body">
                <div x-cloak x-show="preview" role="dialog" aria-modal="true" :aria-label="preview?.name"
                    class="lightbox fixed inset-0 z-50 flex items-center justify-center bg-slate-950/75 p-4 backdrop-blur-sm" @click.self="preview = null">
                    <figure class="relative flex max-h-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
                        <button type="button" class="absolute top-3 right-3 z-10 flex size-9 items-center justify-center rounded-full bg-white/90 text-ink shadow hover:bg-white" @click="preview = null" aria-label="Kapat">
                            <x-icon name="x" class="size-5" />
                        </button>
                        <img :src="preview?.src" :alt="preview?.name" class="max-h-[75vh] w-auto max-w-full object-contain">
                        <figcaption class="flex items-center justify-between gap-4 border-t border-line px-5 py-3">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-ink" x-text="preview?.name"></p>
                                <p class="font-mono text-xs text-muted" x-text="preview?.sku"></p>
                            </div>
                            <a :href="preview?.url" class="shrink-0 text-sm font-medium text-primary hover:underline">Ürüne git →</a>
                        </figcaption>
                    </figure>
                </div>
            </template>
        </x-card>
    @endif
</x-layouts.panel>
