@php use App\Domain\Catalog\StockLevel; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Dealer" title="Katalog">
    <x-page-header title="Katalog" :subtitle="number_format($products->total(), 0, ',', '.').' ürün · fiyatlar size özeldir'" />

    @error('cart')<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror
    @error('request')<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror

    <div class="grid gap-6 lg:grid-cols-4">
        <aside class="lg:col-span-1">
            <form method="GET" class="space-y-5 rounded-xl border border-line bg-white p-4" x-data>
                <x-field name="q" label="Ara" :value="$filters['q'] ?? ''" placeholder="Ad, kod, barkod" />
                <div>
                    <label for="f_category" class="mb-1.5 block text-sm font-medium">Kategori</label>
                    <select id="f_category" name="category" class="field-input" @change="$el.form.submit()">
                        <option value="">Tümü</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}" @selected(($filters['category'] ?? null) == $cat->id)>{{ $cat->name }}</option>
                            @foreach ($cat->children as $child)
                                <option value="{{ $child->id }}" @selected(($filters['category'] ?? null) == $child->id)>&nbsp;&nbsp;— {{ $child->name }}</option>
                            @endforeach
                        @endforeach
                    </select>
                </div>
                <x-field name="brand" label="Marka" :options="['' => 'Tümü'] + $brands->all()" :value="$filters['brand'] ?? ''" @change="$el.form.submit()" />
                <x-field name="sort" label="Sırala" :options="['name' => 'Ada göre', 'new' => 'En yeni', 'stock' => 'Stok (çoktan aza)']" :value="$filters['sort'] ?? 'name'" @change="$el.form.submit()" />
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="in_stock" value="1" @checked($filters['in_stock'] ?? false) class="size-4 rounded" @change="$el.form.submit()"> Sadece stoktakiler</label>
                <div class="flex gap-2">
                    <x-button variant="secondary" size="sm" class="flex-1">Uygula</x-button>
                    <x-button variant="ghost" size="sm" :href="route('dealer.catalog')">Temizle</x-button>
                </div>
            </form>
        </aside>

        <div class="lg:col-span-3">
            @if ($products->isEmpty())
                <x-card><x-empty-state icon="search" title="Ürün bulunamadı" text="Arama veya filtreleri değiştirmeyi deneyin." /></x-card>
            @else
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($products as $product)
                        @php $level = StockLevel::for((string) $product->available_qty, (string) $product->critical_stock_level, $settings->stock_display, $product->unit->name); @endphp
                        <article class="flex flex-col rounded-xl border border-line bg-white p-4 shadow-xs">
                            <a href="{{ route('dealer.catalog.show', $product) }}" class="mb-3 flex aspect-[4/3] items-center justify-center overflow-hidden rounded-lg bg-slate-50">
                                @if ($product->primaryImage)
                                    <img src="{{ route('dealer.images.show', $product->primaryImage) }}" alt="{{ $product->name }}" class="size-full object-contain" loading="lazy">
                                @else
                                    <x-icon name="image" class="size-8 text-slate-300" />
                                @endif
                            </a>
                            <p class="text-xs font-medium tracking-wide text-muted uppercase">{{ $product->brand?->name }}</p>
                            <a href="{{ route('dealer.catalog.show', $product) }}" class="mt-0.5 line-clamp-2 font-medium text-ink hover:text-primary">{{ $product->name }}</a>
                            <p class="text-xs text-muted">{{ $product->sku }}@if ((float) $product->pack_quantity > 1) · Koli {{ \App\Domain\Inventory\Quantity::format($product->pack_quantity) }}@endif</p>
                            <div class="mt-3 flex items-end justify-between gap-2">
                                <x-price-tag :price="$prices[$product->id]" :display="$settings->price_display" />
                                <x-badge :tone="$level['tone']">{{ $level['label'] }}</x-badge>
                            </div>
                            <div class="mt-auto pt-4">
                                @include('dealer.catalog._add-form', ['product' => $product, 'level' => $level, 'hasOpenRequest' => $openRequests->has($product->id)])
                            </div>
                        </article>
                    @endforeach
                </div>
                @if ($products->hasPages())<div class="mt-6">{{ $products->links() }}</div>@endif
            @endif
        </div>
    </div>
</x-layouts.panel>
