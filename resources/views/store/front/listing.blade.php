@php
    use App\Domain\Store\Storefront\Catalog;

    $selectedBrands = array_map('intval', $filters['marka'] ?? []);
    $hasFilters = $selectedBrands || ($filters['min'] ?? null) || ($filters['max'] ?? null) || ($filters['stokta'] ?? false);
    $crumbJson = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => collect([['Ana sayfa', route('store.home')], ...$breadcrumbs])
        ->values()->map(fn ($c, $i) => array_filter(['@type' => 'ListItem', 'position' => $i + 1, 'name' => $c[0], 'item' => $c[1] ?? url()->current()]))->all()];
@endphp
<x-layouts.store :title="$seoTitle ?? $title" :description="$seoDescription ?? $description ?? null" :canonical="$canonical ?? null" :noindex="$noindex" :json-ld="[$crumbJson]">
    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8" x-data="{ filters: false }">
        @include('store.front._breadcrumbs', ['items' => $breadcrumbs])

        <div class="mt-4 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ $title }}</h1>
                <p class="mt-1 text-sm text-muted">{{ number_format($products->total(), 0, ',', '.') }} ürün</p>
            </div>
            @if (! empty($brand?->logo_path))
                <img src="{{ route('store.media.brand', $brand) }}?v={{ $brand->updated_at?->timestamp }}" alt="{{ $brand->name }} logosu" class="h-14 w-auto max-w-40 object-contain">
            @endif
        </div>
        @if (! empty($description))<p class="mt-3 max-w-3xl text-sm leading-relaxed text-slate-600">{{ $description }}</p>@endif

        @if (! empty($subcategories) && $subcategories->isNotEmpty())
            <div class="mt-5 flex flex-wrap gap-2">
                @foreach ($subcategories as $sub)
                    <a href="{{ route('store.category', $sub->slug) }}" class="rounded-full border border-line px-4 py-1.5 text-sm hover:border-primary hover:text-primary">{{ $sub->name }}</a>
                @endforeach
            </div>
        @endif

        <form method="GET" class="mt-6 grid gap-8 lg:grid-cols-[15rem_1fr]">
            @isset($searchTerm)<input type="hidden" name="q" value="{{ $searchTerm }}">@endisset

            {{-- Filtreler --}}
            <aside :class="filters ? 'block' : 'hidden'" class="space-y-6 lg:block" aria-label="Filtreler">
                @if ($brandFacets->isNotEmpty())
                    <fieldset>
                        <legend class="text-sm font-semibold">Marka</legend>
                        <div class="mt-3 max-h-64 space-y-2 overflow-y-auto pr-1">
                            @foreach ($brandFacets as $facet)
                                <label class="flex items-center gap-2.5 text-sm">
                                    <input type="checkbox" name="marka[]" value="{{ $facet->id }}" @checked(in_array($facet->id, $selectedBrands, true)) class="size-4 rounded border-line text-primary" data-autosubmit>
                                    <span class="flex-1">{{ $facet->name }}</span>
                                    <span class="text-xs text-muted tabular-nums">{{ $facet->products_count }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endif
                <fieldset>
                    <legend class="text-sm font-semibold">Fiyat (₺)</legend>
                    <div class="mt-3 flex items-center gap-2">
                        <input name="min" value="{{ $filters['min'] ?? '' }}" inputmode="decimal" placeholder="En az" aria-label="En düşük fiyat" class="field-input w-full">
                        <span class="text-muted">–</span>
                        <input name="max" value="{{ $filters['max'] ?? '' }}" inputmode="decimal" placeholder="En çok" aria-label="En yüksek fiyat" class="field-input w-full">
                    </div>
                </fieldset>
                <label class="flex items-center gap-2.5 text-sm">
                    <input type="hidden" name="stokta" value="0">
                    <input type="checkbox" name="stokta" value="1" @checked($filters['stokta'] ?? false) class="size-4 rounded border-line text-primary" data-autosubmit>
                    Yalnızca stoktakiler
                </label>
                <div class="flex gap-2">
                    <x-button size="sm" class="flex-1">Uygula</x-button>
                    @if ($hasFilters)<x-button size="sm" variant="ghost" :href="url()->current().(isset($searchTerm) ? '?q='.urlencode($searchTerm) : '')">Temizle</x-button>@endif
                </div>
            </aside>

            <div>
                <div class="mb-5 flex items-center justify-between gap-3">
                    <button type="button" class="inline-flex items-center gap-2 rounded-lg border border-line px-3 py-2 text-sm font-medium lg:hidden" @click="filters = ! filters" :aria-expanded="filters">
                        <x-icon name="filter" class="size-4" /> Filtrele @if ($hasFilters)<span class="size-2 rounded-full bg-primary"></span>@endif
                    </button>
                    <label class="ml-auto flex items-center gap-2 text-sm">
                        <span class="text-muted">Sırala</span>
                        <select name="sirala" class="field-input w-auto py-1.5" data-autosubmit>
                            @foreach (Catalog::SORTS as $key => $label)
                                <option value="{{ $key }}" @selected(($filters['sirala'] ?? 'onerilen') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                @if ($products->isEmpty())
                    <div class="rounded-2xl border border-dashed border-line px-6 py-16 text-center">
                        <x-icon name="search" class="mx-auto size-8 text-slate-300" />
                        <p class="mt-3 font-medium">{{ isset($searchTerm) && $searchTerm === '' ? 'Aramak istediğiniz ürünü yazın.' : 'Aradığınız kriterlere uygun ürün bulunamadı.' }}</p>
                        @if ($hasFilters)<p class="mt-1 text-sm text-muted">Filtreleri azaltmayı deneyin.</p>@endif
                    </div>
                @else
                    <div class="grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 xl:grid-cols-4">
                        @foreach ($products as $product)
                            @include('store.front._card', ['product' => $product])
                        @endforeach
                    </div>
                    @if ($products->hasPages())<div class="mt-10">{{ $products->links() }}</div>@endif
                @endif
            </div>
        </form>
    </div>
</x-layouts.store>
