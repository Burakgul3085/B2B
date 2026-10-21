@php
    use App\Domain\Catalog\StockLevel;
    use App\Domain\Inventory\Quantity;

    // Seçili bir filtreyi kaldıran bağlantı (diğer filtreler korunur).
    $without = function (string $key, $value = null) {
        $query = request()->except('page');
        if ($value === null) {
            unset($query[$key]);
        } else {
            $query[$key] = array_values(array_diff((array) ($query[$key] ?? []), [(string) $value]));
        }

        return route('dealer.catalog', $query);
    };
    $categoryNames = $categories->flatMap(fn ($c) => collect([$c])->merge($c->children))->pluck('name', 'id');
    $brandNames = $brands->pluck('name', 'id');
    $hasFilters = filled($filters['q'] ?? null) || $selectedCategories || $selectedBrands || ($filters['in_stock'] ?? false) || $showcase || $onlyNew;
    $newDays = (int) ($settings->new_product_days ?: 30);
    $taxLabel = fn ($rate) => '%'.rtrim(rtrim(number_format((float) $rate, 2, ',', ''), '0'), ',');
@endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Dealer" title="Katalog" wide>
    @error('cart')<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror
    @error('request')<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror

    <div class="flex flex-col gap-6 lg:flex-row lg:items-start" x-data="{ filtersOpen: false }">
        {{-- ============ Filtre paneli ============ --}}
        <aside class="lg:sticky lg:top-6 lg:w-72 lg:shrink-0">
            <button type="button" class="flex w-full items-center justify-between rounded-xl border border-line bg-white px-4 py-3 text-sm font-semibold shadow-xs lg:hidden" @click="filtersOpen = !filtersOpen" :aria-expanded="filtersOpen">
                <span class="inline-flex items-center gap-2"><x-icon name="filter" class="size-4" /> Filtreler</span>
                <x-icon name="chevron-down" class="size-4 transition" x-bind:class="filtersOpen && 'rotate-180'" />
            </button>

            <form id="catalog-filters" method="GET" action="{{ route('dealer.catalog') }}"
                x-show="filtersOpen" x-cloak class="mt-3 rounded-2xl border border-line bg-white shadow-xs lg:mt-0 lg:block!">
                <input type="hidden" name="sort" value="{{ $filters['sort'] ?? 'name' }}">
                <input type="hidden" name="view" value="{{ $view }}">
                @if ($onlyNew)<input type="hidden" name="yeni" value="1">@endif
                @if ($filters['in_stock'] ?? false)<input type="hidden" name="in_stock" value="1">@endif

                <div class="flex items-center justify-between border-b border-line px-5 py-4">
                    <h2 class="text-base font-semibold text-ink">Filtreler</h2>
                    @if ($hasFilters)
                        <a href="{{ route('dealer.catalog', array_filter(['view' => $view, 'sort' => $filters['sort'] ?? null])) }}" class="text-xs font-medium text-primary hover:underline">Tümünü temizle</a>
                    @endif
                </div>

                <div class="border-b border-line px-5 py-4">
                    <label for="f_q" class="sr-only">Ürün ara</label>
                    <div class="relative">
                        <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                        <input id="f_q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Ad, kod veya barkod" class="field-input pl-9">
                    </div>
                </div>

                {{-- Kategoriler --}}
                <div role="group" aria-labelledby="f_cat_title" class="border-b border-line px-5 py-4">
                    <h3 id="f_cat_title" class="mb-3 text-xs font-semibold tracking-wider text-muted uppercase">Kategoriler</h3>
                    <div class="filter-scroll max-h-72 space-y-0.5 overflow-y-auto pr-1">
                        @foreach ($categories as $cat)
                            @continue($cat->product_count === 0 && ! in_array($cat->id, $selectedCategories, true))
                            <label class="flex cursor-pointer items-center gap-3 rounded-lg px-2 py-1.5 hover:bg-slate-50">
                                <input type="checkbox" name="category[]" value="{{ $cat->id }}" @checked(in_array($cat->id, $selectedCategories, true)) data-autosubmit class="size-4 rounded border-slate-300 text-primary">
                                <span class="flex-1 text-sm font-medium text-ink">{{ $cat->name }}</span>
                                <span class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[11px] font-semibold text-muted tabular-nums">{{ number_format($cat->product_count, 0, ',', '.') }}</span>
                            </label>
                            @foreach ($cat->children as $child)
                                @continue($child->product_count === 0 && ! in_array($child->id, $selectedCategories, true))
                                <label class="ml-5 flex cursor-pointer items-center gap-3 rounded-lg px-2 py-1.5 hover:bg-slate-50">
                                    <input type="checkbox" name="category[]" value="{{ $child->id }}" @checked(in_array($child->id, $selectedCategories, true)) data-autosubmit class="size-4 rounded border-slate-300 text-primary">
                                    <span class="flex-1 text-sm text-ink/80">{{ $child->name }}</span>
                                    <span class="text-[11px] text-muted tabular-nums">{{ number_format($child->product_count, 0, ',', '.') }}</span>
                                </label>
                            @endforeach
                        @endforeach
                    </div>
                </div>

                {{-- Markalar --}}
                <div role="group" aria-labelledby="f_brand_title" class="px-5 py-4" x-data="{ term: '' }">
                    <h3 id="f_brand_title" class="mb-3 text-xs font-semibold tracking-wider text-muted uppercase">Markalar</h3>
                    @if ($brands->count() > 8)
                        <input type="search" x-model="term" placeholder="Marka ara" aria-label="Marka ara" class="field-input mb-2 py-1.5 text-xs">
                    @endif
                    <div class="filter-scroll max-h-64 space-y-0.5 overflow-y-auto pr-1">
                        @foreach ($brands as $brand)
                            <label class="flex cursor-pointer items-center gap-3 rounded-lg px-2 py-1.5 hover:bg-slate-50"
                                x-show="! term || {{ Js::from(mb_strtolower($brand->name)) }}.includes(term.toLocaleLowerCase('tr'))">
                                <input type="checkbox" name="brand[]" value="{{ $brand->id }}" @checked(in_array($brand->id, $selectedBrands, true)) data-autosubmit class="size-4 rounded border-slate-300 text-primary">
                                <span class="flex-1 text-sm text-ink">{{ $brand->name }}</span>
                                <span class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[11px] font-semibold text-muted tabular-nums">{{ $brand->product_count }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="border-t border-line px-5 py-4">
                    <x-button class="w-full" icon="search">Filtrele</x-button>
                </div>
            </form>
        </aside>

        {{-- ============ Sonuçlar ============ --}}
        <section class="min-w-0 flex-1">
            {{-- Araç çubuğu --}}
            <div class="flex flex-wrap items-center gap-x-4 gap-y-3 rounded-2xl border border-line bg-white px-4 py-3 shadow-xs">
                <p class="text-sm text-muted"><span class="text-lg font-semibold text-ink tabular-nums">{{ number_format($products->total(), 0, ',', '.') }}</span> ürün</p>

                <div class="ml-auto flex flex-wrap items-center gap-3">
                    <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-ink">
                        <input type="checkbox" name="in_stock" value="1" form="catalog-filters-stock" @checked($filters['in_stock'] ?? false) data-autosubmit class="size-4 rounded border-slate-300 text-primary">
                        Sadece stoktakiler
                    </label>
                    <form id="catalog-filters-stock" method="GET" action="{{ route('dealer.catalog') }}" class="contents">
                        @foreach (request()->except(['in_stock', 'page', 'sort']) as $key => $value)
                            @foreach ((array) $value as $v)<input type="hidden" name="{{ is_array($value) ? $key.'[]' : $key }}" value="{{ $v }}">@endforeach
                        @endforeach
                        <label for="f_sort" class="text-sm text-muted">Sırala</label>
                        <select id="f_sort" name="sort" data-autosubmit class="field-input w-auto py-1.5 pr-8">
                            @foreach (['name' => 'Ada göre (A-Z)', 'new' => 'En yeni ürünler', 'stock' => 'Stok (çoktan aza)'] as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['sort'] ?? 'name') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </form>

                    <div class="flex overflow-hidden rounded-lg border border-line" role="group" aria-label="Görünüm">
                        @foreach (['list' => 'Liste görünümü', 'grid' => 'Izgara görünümü'] as $mode => $label)
                            <a href="{{ route('dealer.catalog', array_merge(request()->except('page'), ['view' => $mode])) }}" title="{{ $label }}" aria-label="{{ $label }}" @if ($view === $mode) aria-current="true" @endif
                                @class(['flex size-9 items-center justify-center transition', 'bg-primary text-white' => $view === $mode, 'text-muted hover:bg-slate-50 hover:text-ink' => $view !== $mode])>
                                <x-icon :name="$mode" class="size-4" />
                            </a>
                        @endforeach
                    </div>
                </div>

                @if ($hasFilters)
                    <div class="flex w-full flex-wrap items-center gap-2 border-t border-line pt-3">
                        @if ($onlyNew)
                            <a href="{{ $without('yeni') }}" class="chip">Son {{ $newDays }} günde eklenenler <x-icon name="x" class="size-3" /></a>
                        @endif
                        @if ($showcase)
                            <a href="{{ $without('showcase') }}" class="chip">Vitrin: {{ $showcase->name }} <x-icon name="x" class="size-3" /></a>
                        @endif
                        @if (filled($filters['q'] ?? null))
                            <a href="{{ $without('q') }}" class="chip">“{{ $filters['q'] }}” <x-icon name="x" class="size-3" /></a>
                        @endif
                        @foreach ($selectedCategories as $id)
                            <a href="{{ $without('category', $id) }}" class="chip">{{ $categoryNames[$id] ?? 'Kategori' }} <x-icon name="x" class="size-3" /></a>
                        @endforeach
                        @foreach ($selectedBrands as $id)
                            <a href="{{ $without('brand', $id) }}" class="chip">{{ $brandNames[$id] ?? 'Marka' }} <x-icon name="x" class="size-3" /></a>
                        @endforeach
                        @if ($filters['in_stock'] ?? false)
                            <a href="{{ $without('in_stock') }}" class="chip">Stoktakiler <x-icon name="x" class="size-3" /></a>
                        @endif
                    </div>
                @endif
            </div>

            @if ($products->isEmpty() && $latest->isNotEmpty())
                {{-- Yeni ürün yok: boş sayfa yerine son eklenenler --}}
                <div class="mt-5 flex items-start gap-3 rounded-2xl border border-blue-200 bg-info-soft px-5 py-4 text-sky-900">
                    <x-icon name="alert" class="mt-0.5 size-5 shrink-0" />
                    <div>
                        <p class="font-semibold">Son {{ $newDays }} günde yeni ürün eklenmedi.</p>
                        <p class="mt-0.5 text-sm">En son eklenen ürünlerimize göz atabilirsiniz. Yeni ürünler eklendiğinde burada ve menüdeki "Yeni Ürünler" rozetinde görünür.</p>
                    </div>
                </div>
                <h2 class="mt-8 text-lg font-bold tracking-tight text-ink">En son eklenen ürünler</h2>
                <div class="mt-4 grid gap-5 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                    @foreach ($latest as $product)
                        @include('dealer.catalog._card', ['product' => $product])
                    @endforeach
                </div>
            @elseif ($products->isEmpty())
                <x-card class="mt-5"><x-empty-state icon="search" title="Ürün bulunamadı" text="Arama veya filtreleri değiştirmeyi deneyin." /></x-card>
            @elseif ($view === 'list')
                {{-- Liste görünümü --}}
                <div class="mt-5 divide-y divide-line overflow-hidden rounded-2xl border border-line bg-white shadow-xs">
                    @foreach ($products as $product)
                        @php $level = StockLevel::for((string) $product->available_qty, (string) $product->critical_stock_level, $settings->stock_display, $product->unit->name); @endphp
                        <article class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center">
                            <a href="{{ route('dealer.catalog.show', $product) }}" class="flex size-24 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-line bg-white">
                                @if ($product->primaryImage)
                                    <x-product-image :src="route('dealer.images.show', $product->primaryImage)" :alt="$product->name" />
                                @else
                                    <x-icon name="image" class="size-7 text-slate-300" />
                                @endif
                            </a>
                            <div class="min-w-0 flex-1">
                                <p class="text-[11px] font-semibold tracking-wider text-muted uppercase">{{ $product->brand?->name }}</p>
                                <a href="{{ route('dealer.catalog.show', $product) }}" class="font-semibold text-ink hover:text-primary">{{ $product->name }}</a>
                                <p class="mt-0.5 font-mono text-xs text-muted">{{ $product->sku }}@if ((float) $product->pack_quantity > 1)<span class="font-sans"> · Koli {{ Quantity::format($product->pack_quantity) }}</span>@endif</p>
                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    <x-badge :tone="$level['tone']">{{ $level['label'] }}</x-badge>
@if (\App\Domain\Catalog\NewProducts::isNew($product, $settings))<span class="rounded-md bg-violet-600 px-1.5 py-0.5 text-[11px] font-bold tracking-wide text-white uppercase">Yeni</span>@endif
                                    <span class="rounded-md border border-emerald-200 bg-emerald-50 px-1.5 py-0.5 text-[11px] font-semibold text-emerald-700">+KDV {{ $taxLabel($product->taxRate?->rate) }}</span>
                                </div>
                            </div>
                            <x-price-tag :price="$prices[$product->id]" :display="$settings->price_display" class="sm:w-40 sm:text-right" />
                            <div class="sm:w-72">
                                @include('dealer.catalog._add-form', ['product' => $product, 'level' => $level, 'hasOpenRequest' => $openRequests->has($product->id)])
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                {{-- Izgara görünümü --}}
                <div class="mt-5 grid gap-5 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                    @foreach ($products as $product)
                        @include('dealer.catalog._card', ['product' => $product])
                    @endforeach
                </div>
            @endif

            @if ($products->hasPages())<div class="mt-6">{{ $products->links() }}</div>@endif
        </section>
    </div>
</x-layouts.panel>
