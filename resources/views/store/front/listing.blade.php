@php
    use App\Domain\Store\Storefront\Catalog;
    use App\Domain\Store\Storefront\Palette;

    $selectedBrands = array_map('intval', $filters['marka'] ?? []);
    $selectedCategories = array_map('intval', $filters['kategori'] ?? []);
    $term = $filters['q'] ?? null;
    $isOn = fn (string $key) => filter_var($filters[$key] ?? false, FILTER_VALIDATE_BOOL);
    $hasFilters = $selectedBrands || $selectedCategories || filled($term) || filled($filters['min'] ?? null) || filled($filters['max'] ?? null) || $isOn('stokta') || $isOn('indirim') || $onlyNew;

    // Bu sayfanın adresi, verilen sorgu değerleriyle (sayfa numarası sıfırlanır).
    $urlWith = function (array $query) {
        $query = array_filter($query, fn ($v) => $v !== null && $v !== [] && $v !== '');

        return url()->current().($query ? '?'.http_build_query($query) : '');
    };
    // Seçili bir filtreyi kaldıran bağlantı (diğer filtreler korunur).
    $without = function (string $key, $value = null) use ($urlWith) {
        $query = request()->except('page');
        if ($value === null) {
            unset($query[$key]);
        } else {
            $query[$key] = array_values(array_diff((array) ($query[$key] ?? []), [(string) $value]));
        }

        return $urlWith($query);
    };
    $clearUrl = $urlWith(['q' => $searchTerm ?? null, 'sirala' => request('sirala'), 'gorunum' => request('gorunum')]);
    $flatten = function ($nodes) use (&$flatten) {
        return $nodes->flatMap(fn ($c) => collect([$c])->merge($flatten($c->children)));
    };
    $categoryNames = $flatten($categoryFacets)->pluck('name', 'id');
    $brandNames = $brandFacets->pluck('name', 'id');
    $lower = fn (string $name) => mb_strtolower(strtr($name, ['I' => 'ı', 'İ' => 'i']), 'UTF-8');

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
                <h1 class="font-display text-2xl font-extrabold tracking-tight sm:text-3xl">{{ $title }}</h1>
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

        <div class="mt-6 flex flex-col gap-6 lg:flex-row lg:items-start">
            {{-- ============ Filtre paneli ============ --}}
            {{-- Filtre formu yalnızca paneli sarar; araç çubuğundaki seçimler kendi formuyla gönderilir (sepet formları iç içe olmasın). --}}
            <aside class="lg:w-72 lg:shrink-0" aria-label="Filtreler">
                <button type="button" class="flex w-full items-center justify-between rounded-xl border border-line bg-white px-4 py-3 text-sm font-semibold shadow-xs lg:hidden" @click="filters = ! filters" :aria-expanded="filters">
                    <span class="inline-flex items-center gap-2"><x-icon name="filter" class="size-4" /> Filtreler @if ($hasFilters)<span class="size-2 rounded-full bg-primary"></span>@endif</span>
                    <x-icon name="chevron-down" class="size-4 transition" ::class="filters && 'rotate-180'" />
                </button>

                <form id="filtreler" method="GET" action="{{ url()->current() }}" :class="filters ? 'block' : 'hidden'"
                    class="mt-3 rounded-2xl border border-line bg-white shadow-xs lg:mt-0 lg:block!">
                    @if (filled($filters['sirala'] ?? null))<input type="hidden" name="sirala" value="{{ $filters['sirala'] }}">@endif
                    @if (filled($filters['gorunum'] ?? null))<input type="hidden" name="gorunum" value="{{ $filters['gorunum'] }}">@endif
                    @if ($onlyNew)<input type="hidden" name="yeni" value="1">@endif
                    @if ($isOn('stokta'))<input type="hidden" name="stokta" value="1">@endif

                    <div class="flex items-center justify-between border-b border-line px-5 py-4">
                        <h2 class="text-base font-semibold text-ink">Filtreler</h2>
                        @if ($hasFilters)<a href="{{ $clearUrl }}" class="text-xs font-medium text-primary hover:underline">Tümünü temizle</a>@endif
                    </div>

                    <div class="border-b border-line px-5 py-4">
                        <label for="f_q" class="sr-only">Ürün ara</label>
                        <div class="relative">
                            <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                            <input id="f_q" name="q" value="{{ $searchTerm ?? $term }}" maxlength="100" placeholder="Ürün adı, marka veya kod" class="field-input pl-9">
                        </div>
                    </div>

                    @if ($categoryFacets->contains(fn ($c) => $c->product_count > 0))
                        <div role="group" aria-labelledby="f_cat_title" class="border-b border-line px-5 py-4">
                            <h3 id="f_cat_title" class="mb-3 text-xs font-semibold tracking-wider text-muted uppercase">Kategoriler</h3>
                            <div class="filter-scroll max-h-72 space-y-0.5 overflow-y-auto pr-1">
                                @include('store.front._category-filter', ['nodes' => $categoryFacets, 'selected' => $selectedCategories, 'depth' => 0])
                            </div>
                        </div>
                    @endif

                    @if ($brandFacets->isNotEmpty())
                        <div role="group" aria-labelledby="f_brand_title" class="border-b border-line px-5 py-4" x-data="textFilter">
                            <h3 id="f_brand_title" class="mb-3 text-xs font-semibold tracking-wider text-muted uppercase">Markalar</h3>
                            @if ($brandFacets->count() > 8)
                                <input type="search" x-model="term" placeholder="Marka ara" aria-label="Marka ara" class="field-input mb-2 py-1.5 text-xs">
                            @endif
                            <div class="filter-scroll max-h-64 space-y-0.5 overflow-y-auto pr-1">
                                @foreach ($brandFacets as $facet)
                                    <label class="flex cursor-pointer items-center gap-3 rounded-lg px-2 py-1.5 hover:bg-slate-50" x-show="match(@alpine($lower($facet->name)))">
                                        <input type="checkbox" name="marka[]" value="{{ $facet->id }}" @checked(in_array($facet->id, $selectedBrands, true)) data-autosubmit class="size-4 rounded border-line text-primary">
                                        <span class="flex-1 text-sm text-ink">{{ $facet->name }}</span>
                                        <span class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[11px] font-semibold text-muted tabular-nums">{{ $facet->products_count }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="space-y-4 px-5 py-4">
                        <fieldset>
                            <legend class="mb-3 text-xs font-semibold tracking-wider text-muted uppercase">Fiyat (₺)</legend>
                            <div class="flex items-center gap-2">
                                <input name="min" value="{{ $filters['min'] ?? '' }}" inputmode="decimal" placeholder="En az" aria-label="En düşük fiyat" class="field-input w-full">
                                <span class="text-muted">–</span>
                                <input name="max" value="{{ $filters['max'] ?? '' }}" inputmode="decimal" placeholder="En çok" aria-label="En yüksek fiyat" class="field-input w-full">
                            </div>
                        </fieldset>
                        <label class="flex cursor-pointer items-center gap-2.5 text-sm">
                            <input type="checkbox" name="indirim" value="1" @checked($isOn('indirim')) class="size-4 rounded border-line text-primary" data-autosubmit>
                            Yalnızca indirimdekiler
                        </label>
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

                    <form method="GET" action="{{ url()->current() }}" class="ml-auto flex flex-wrap items-center gap-3">
                        @foreach (request()->except(['stokta', 'page', 'sirala']) as $key => $value)
                            @foreach ((array) $value as $v)<input type="hidden" name="{{ is_array($value) ? $key.'[]' : $key }}" value="{{ $v }}">@endforeach
                        @endforeach
                        <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-ink">
                            <input type="checkbox" name="stokta" value="1" @checked($isOn('stokta')) data-autosubmit class="size-4 rounded border-line text-primary">
                            Sadece stoktakiler
                        </label>
                        <label for="f_sirala" class="text-sm text-muted">Sırala</label>
                        <select id="f_sirala" name="sirala" data-autosubmit class="field-input w-auto py-1.5 pr-8">
                            @foreach (Catalog::SORTS as $key => $label)
                                <option value="{{ $key }}" @selected(($filters['sirala'] ?? 'onerilen') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <div class="flex overflow-hidden rounded-lg border border-line" role="group" aria-label="Görünüm">
                            @foreach (['liste' => ['list', 'Liste görünümü'], 'izgara' => ['grid', 'Izgara görünümü']] as $mode => [$icon, $label])
                                <a href="{{ $urlWith(array_merge(request()->except('page'), ['gorunum' => $mode])) }}" title="{{ $label }}" aria-label="{{ $label }}" @if ($view === $mode) aria-current="true" @endif
                                    @class(['flex size-9 items-center justify-center transition', 'bg-navy text-white' => $view === $mode, 'text-muted hover:bg-slate-50 hover:text-ink' => $view !== $mode])>
                                    <x-icon :name="$icon" class="size-4" />
                                </a>
                            @endforeach
                        </div>
                    </form>

                    @if ($hasFilters)
                        <div class="flex w-full flex-wrap items-center gap-2 border-t border-line pt-3">
                            @if ($onlyNew)<a href="{{ $without('yeni') }}" class="chip">Son {{ $newDays }} günde eklenenler <x-icon name="x" class="size-3" /></a>@endif
                            @if (filled($term))<a href="{{ $without('q') }}" class="chip">“{{ $term }}” <x-icon name="x" class="size-3" /></a>@endif
                            @foreach ($selectedCategories as $id)
                                <a href="{{ $without('kategori', $id) }}" class="chip">{{ $categoryNames[$id] ?? 'Kategori' }} <x-icon name="x" class="size-3" /></a>
                            @endforeach
                            @foreach ($selectedBrands as $id)
                                <a href="{{ $without('marka', $id) }}" class="chip">{{ $brandNames[$id] ?? 'Marka' }} <x-icon name="x" class="size-3" /></a>
                            @endforeach
                            @if (filled($filters['min'] ?? null))<a href="{{ $without('min') }}" class="chip">En az {{ $filters['min'] }} ₺ <x-icon name="x" class="size-3" /></a>@endif
                            @if (filled($filters['max'] ?? null))<a href="{{ $without('max') }}" class="chip">En çok {{ $filters['max'] }} ₺ <x-icon name="x" class="size-3" /></a>@endif
                            @if ($isOn('stokta'))<a href="{{ $without('stokta') }}" class="chip">Stoktakiler <x-icon name="x" class="size-3" /></a>@endif
                            @if ($isOn('indirim'))<a href="{{ $without('indirim') }}" class="chip">İndirimdekiler <x-icon name="x" class="size-3" /></a>@endif
                        </div>
                    @endif
                </div>

                @if ($products->isEmpty() && $latest->isNotEmpty())
                    {{-- Yeni ürün yok: boş sayfa yerine son eklenenler --}}
                    <div class="mt-5 flex items-start gap-3 rounded-2xl border border-blue-200 bg-info-soft px-5 py-4 text-sky-900">
                        <x-icon name="alert" class="mt-0.5 size-5 shrink-0" />
                        <div>
                            <p class="font-semibold">Son {{ $newDays }} günde yeni ürün eklenmedi.</p>
                            <p class="mt-0.5 text-sm">En son eklenen ürünlerimize göz atabilirsiniz.</p>
                        </div>
                    </div>
                    <h2 class="mt-8 text-lg font-bold tracking-tight text-ink">En son eklenen ürünler</h2>
                    <div class="mt-4 grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 xl:grid-cols-4">
                        @foreach ($latest as $product)
                            @include('store.front._card', ['product' => $product])
                        @endforeach
                    </div>
                @elseif ($products->isEmpty())
                    <div class="mt-5 rounded-2xl border border-dashed border-line px-6 py-16 text-center">
                        <x-icon name="search" class="mx-auto size-8 text-slate-300" />
                        <p class="mt-3 font-medium">{{ isset($searchTerm) && $searchTerm === '' ? 'Aramak istediğiniz ürünü yazın.' : 'Aradığınız kriterlere uygun ürün bulunamadı.' }}</p>
                        @if ($hasFilters)<p class="mt-1 text-sm text-muted">Filtreleri azaltmayı deneyin.</p>@endif
                    </div>
                @elseif ($view === 'liste')
                    <div class="mt-5 divide-y divide-line overflow-hidden rounded-2xl border border-line bg-white shadow-xs">
                        @foreach ($products as $product)
                            @include('store.front._row', ['product' => $product])
                        @endforeach
                    </div>
                @else
                    <div class="mt-5 grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 xl:grid-cols-4">
                        @foreach ($products as $product)
                            @include('store.front._card', ['product' => $product])
                        @endforeach
                    </div>
                @endif

                @if ($products->hasPages())<div class="mt-8">{{ $products->links() }}</div>@endif
            </section>
        </div>
    </div>
</x-layouts.store>
