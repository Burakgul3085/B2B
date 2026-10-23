@php
    use App\Domain\Store\Storefront\Catalog;
    use App\Domain\Store\Storefront\Palette;

    $selectedBrands = array_map('intval', $filters['marka'] ?? []);
    $hasFilters = $selectedBrands || ($filters['min'] ?? null) || ($filters['max'] ?? null) || ($filters['stokta'] ?? false) || ($filters['indirim'] ?? false);
    $crumbJson = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => collect([['Ana sayfa', route('store.home')], ...$breadcrumbs])
        ->values()->map(fn ($c, $i) => array_filter(['@type' => 'ListItem', 'position' => $i + 1, 'name' => $c[0], 'item' => $c[1] ?? url()->current()]))->all()];
@endphp
<x-layouts.store :title="$seoTitle ?? $title" :description="$seoDescription ?? $description ?? null" :canonical="$canonical ?? null" :noindex="$noindex" :json-ld="[$crumbJson]">
    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8" x-data="{ filters: false }">
        @include('store.front._breadcrumbs', ['items' => $breadcrumbs])

        @if (! empty($category))
            {{-- Kategori başlığı: ana kategorinin yapboz rengi; görsel kategorinin kendisinden, yoksa ana kategoriden. --}}
            @php
                $tone = Palette::classes($rootCategory?->palette);
                $heroImage = $category->image_path ? $category->id : ($rootCategory?->image_path ? $rootCategory->id : null);
                $tagline = $category->tagline ?: $rootCategory?->tagline;
            @endphp
            <header class="relative mt-4 overflow-hidden rounded-3xl px-6 py-8 sm:px-10 sm:py-10 {{ $tone['soft'] }}">
                <span class="absolute top-0 left-0 h-1.5 w-full {{ $tone['solid'] }}" aria-hidden="true"></span>
                <div class="relative z-10 max-w-2xl">
                    <p class="text-xs font-bold tracking-[0.14em] uppercase {{ $tone['text'] }}">{{ number_format($products->total(), 0, ',', '.') }} ürün</p>
                    <h1 class="mt-2 font-display text-3xl font-extrabold tracking-tight sm:text-4xl">{{ $title }}</h1>
                    @if ($tagline)<p class="mt-2 text-base text-ink/75">{{ $tagline }}</p>@endif
                    @if (! empty($description))<p class="mt-3 text-sm leading-relaxed text-ink/70">{{ $description }}</p>@endif
                </div>
                @if ($heroImage)
                    <img src="{{ route('store.media.category', ['storeCategory' => $heroImage, 'w' => 480]) }}" alt="" class="absolute -right-6 -bottom-8 hidden size-48 rotate-6 rounded-3xl object-cover shadow-xl md:block lg:right-10 lg:size-56">
                @else
                    <span class="absolute -right-6 -bottom-8 hidden size-44 rotate-12 items-center justify-center rounded-[2rem] font-display text-7xl font-extrabold text-white/90 md:flex lg:right-12 {{ $tone['solid'] }}" aria-hidden="true">{{ mb_substr($category->name, 0, 1) }}</span>
                @endif
            </header>
        @else
            <div class="mt-4 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="font-display text-2xl font-extrabold tracking-tight sm:text-3xl">{{ $title }}</h1>
                    <p class="mt-1 text-sm text-muted">{{ number_format($products->total(), 0, ',', '.') }} ürün</p>
                </div>
                @if (! empty($brand?->logo_path))
                    <img src="{{ route('store.media.brand', $brand) }}?v={{ $brand->updated_at?->timestamp }}" alt="{{ $brand->name }} logosu" class="h-14 w-auto max-w-40 object-contain">
                @endif
            </div>
            @if (! empty($description))<p class="mt-3 max-w-3xl text-sm leading-relaxed text-slate-600">{{ $description }}</p>@endif
        @endif

        @if (! empty($subcategories) && $subcategories->isNotEmpty())
            <nav class="mt-5 flex gap-2 overflow-x-auto pb-1" aria-label="Alt kategoriler">
                @foreach ($subcategories as $sub)
                    <a href="{{ route('store.category', $sub->slug) }}" class="shrink-0 rounded-full border border-line bg-white px-4 py-2 text-sm font-medium transition hover:border-transparent hover:text-white {{ $tone['hover'] ?? 'hover:bg-navy' }}">{{ $sub->name }}</a>
                @endforeach
            </nav>
        @endif

        <div class="mt-6 grid gap-8 lg:grid-cols-[15rem_1fr]">
            {{-- Filtre formu yalnızca kenar çubuğunu sarar; sıralama "form" özniteliğiyle ona bağlıdır (ızgaradaki sepet formları iç içe olmasın). --}}

            {{-- Filtreler --}}
            <aside :class="filters ? 'block' : 'hidden'" class="lg:block" aria-label="Filtreler">
            <form id="filtreler" method="GET" class="space-y-6">
                @isset($searchTerm)<input type="hidden" name="q" value="{{ $searchTerm }}">@endisset
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
                <label class="flex items-center gap-2.5 text-sm">
                    <input type="hidden" name="indirim" value="0">
                    <input type="checkbox" name="indirim" value="1" @checked($filters['indirim'] ?? false) class="size-4 rounded border-line text-primary" data-autosubmit>
                    Yalnızca indirimdekiler
                </label>
                <div class="flex gap-2">
                    <x-button size="sm" class="flex-1">Uygula</x-button>
                    @if ($hasFilters)<x-button size="sm" variant="ghost" :href="url()->current().(isset($searchTerm) ? '?q='.urlencode($searchTerm) : '')">Temizle</x-button>@endif
                </div>
            </form>
            </aside>

            <div>
                <div class="mb-5 flex items-center justify-between gap-3">
                    <button type="button" class="inline-flex items-center gap-2 rounded-lg border border-line px-3 py-2 text-sm font-medium lg:hidden" @click="filters = ! filters" :aria-expanded="filters">
                        <x-icon name="filter" class="size-4" /> Filtrele @if ($hasFilters)<span class="size-2 rounded-full bg-primary"></span>@endif
                    </button>
                    <label class="ml-auto flex items-center gap-2 text-sm">
                        <span class="text-muted">Sırala</span>
                        <select name="sirala" form="filtreler" class="field-input w-auto py-1.5" data-autosubmit>
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
        </div>
    </div>
</x-layouts.store>
