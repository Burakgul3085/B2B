@php use App\Domain\Support\Money; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" title="Ürünler">
    <x-page-header title="Ürünler" subtitle="Mağazada satılan ürünler. Fiyatlar KDV dahildir.">
        <x-button variant="secondary" icon="upload" :href="route('store_admin.products.import')">Excel ile aktar</x-button>
        <x-button icon="plus" :href="route('store_admin.products.create')">Yeni ürün</x-button>
    </x-page-header>

    @if (! $hasAnyProduct)
        <x-card>
            <x-empty-state icon="package" title="Henüz ürün yok" text="İlk ürününüzü ekleyin veya Excel şablonuyla ürünlerinizi toplu olarak aktarın.">
                <div class="flex flex-wrap justify-center gap-2">
                    <x-button icon="plus" :href="route('store_admin.products.create')">Ürün ekle</x-button>
                    <x-button variant="secondary" icon="upload" :href="route('store_admin.products.import')">Excel ile aktar</x-button>
                </div>
            </x-empty-state>
        </x-card>
    @else
        <x-card :padding="false" x-data="productList()" @keydown.escape.window="close()">
            <form method="GET" class="grid gap-3 border-b border-line p-4 sm:grid-cols-2 lg:grid-cols-6 lg:items-end">
                <x-field name="q" label="Ara" :value="$filters['q'] ?? ''" placeholder="Ad, stok kodu veya barkod" class="lg:col-span-2" />
                <x-field name="category" label="Kategori" :options="['' => 'Tümü'] + $categories" :value="$filters['category'] ?? ''" />
                <x-field name="brand" label="Marka" :options="['' => 'Tümü'] + $brands" :value="$filters['brand'] ?? ''" />
                <x-field name="status" label="Durum" :options="['' => 'Tümü', 'published' => 'Yayında', 'draft' => 'Taslak', 'featured' => 'Öne çıkan', 'no_image' => 'Görselsiz']" :value="$filters['status'] ?? ''" />
                <div class="flex gap-2">
                    <x-field name="sort" label="Sırala" :options="['' => 'Son güncellenen', 'name' => 'Ada göre', 'price' => 'Fiyata göre']" :value="$filters['sort'] ?? ''" class="flex-1" />
                    <x-button variant="secondary" class="self-end" aria-label="Filtrele"><x-icon name="search" class="size-4" /></x-button>
                </div>
            </form>

            @if ($products->isEmpty())
                <x-empty-state icon="search" title="Filtrelere uyan ürün yok">
                    <x-button variant="secondary" :href="route('store_admin.products.index')">Filtreleri temizle</x-button>
                </x-empty-state>
            @else
                <form method="POST" action="{{ route('store_admin.products.bulk') }}">
                    @csrf
                    <div x-cloak x-show="selected.length" class="flex flex-wrap items-center gap-3 border-b border-line bg-primary-soft/50 px-4 py-2.5 text-sm">
                        <span><b x-text="selected.length"></b> ürün seçildi</span>
                        <button name="action" value="publish" class="font-medium text-primary hover:underline">Yayına al</button>
                        <button name="action" value="unpublish" class="font-medium text-primary hover:underline">Yayından kaldır</button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table-base">
                            <thead>
                                <tr>
                                    <th class="w-10"><input type="checkbox" class="size-4 rounded" aria-label="Tümünü seç" x-model="all" @change="selected = all ? @alpine($products->pluck('id')) : []"></th>
                                    <th>Ürün</th>
                                    <th class="hidden md:table-cell">Kategori / Marka</th>
                                    <th class="text-right">Fiyat</th>
                                    <th class="text-right">Stok</th>
                                    <th>Durum</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($products as $product)
                                    <tr>
                                        <td><input type="checkbox" name="ids[]" value="{{ $product->id }}" x-model.number="selected" class="size-4 rounded" aria-label="{{ $product->name }} seç"></td>
                                        <td>
                                            <div class="flex items-center gap-3">
                                                @if ($product->primaryImage)
                                                    @php $thumb = ['src' => route('store_admin.images.show', $product->primaryImage), 'name' => $product->name, 'url' => route('store_admin.products.edit', $product)]; @endphp
                                                    {{-- Üzerine gelince yanda büyük önizleme, tıklayınca tam ekran --}}
                                                    <button type="button" class="group/thumb relative flex size-11 shrink-0 cursor-zoom-in items-center justify-center overflow-hidden rounded-lg border border-line bg-white p-0.5 transition hover:border-primary hover:ring-2 hover:ring-primary/20"
                                                        @mouseenter="show($event, @alpine($thumb))" @mouseleave="hide()" @focus="show($event, @alpine($thumb))" @blur="hide()" @click="open(@alpine($thumb))"
                                                        aria-label="{{ $product->name }} görselini büyüt">
                                                        <img src="{{ $thumb['src'] }}" alt="" class="max-h-full max-w-full object-contain" loading="lazy">
                                                    </button>
                                                @else
                                                    <span class="flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-line bg-white p-0.5"><x-icon name="image" class="size-4 text-slate-300" /></span>
                                                @endif
                                                <div class="min-w-0">
                                                    <a href="{{ route('store_admin.products.edit', $product) }}" class="font-medium text-ink hover:text-primary">{{ $product->name }}</a>
                                                    <p class="text-xs text-muted">{{ $product->variants_count > 1 ? $product->variants_count.' '.mb_strtolower($product->variant_name ?? 'seçenek') : 'Tek seçenek' }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="hidden text-muted md:table-cell">
                                            {{ $product->category?->fullName() ?? '—' }}<br><span class="text-xs">{{ $product->brand?->name }}</span>
                                        </td>
                                        <td class="text-right whitespace-nowrap tabular-nums">
                                            {{ Money::format($product->min_price) }}@if (bccomp((string) $product->max_price, (string) $product->min_price, 2) > 0)<span class="text-muted"> – {{ Money::format($product->max_price) }}</span>@endif
                                        </td>
                                        <td @class(['text-right tabular-nums', 'font-semibold text-danger' => (int) $product->total_stock === 0])>{{ number_format((int) $product->total_stock, 0, ',', '.') }}</td>
                                        <td>
                                            @if ($product->is_published)<x-badge tone="success">Yayında</x-badge>@else<x-badge tone="warning">Taslak</x-badge>@endif
                                            @if ($product->is_featured)<x-badge tone="primary" class="mt-1">Öne çıkan</x-badge>@endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </form>
                {{-- Üzerine gelince açılan önizleme (tabloyu kesmesin diye sayfaya taşınır) --}}
                <template x-teleport="body">
                    <div x-cloak x-show="hover" x-transition.opacity.duration.150ms class="pointer-events-none fixed z-50 overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-black/10" :style="hoverStyle" aria-hidden="true">
                        <div class="flex aspect-square items-center justify-center bg-white p-3"><img :src="hover && hover.src" alt="" class="max-h-full max-w-full object-contain"></div>
                        <p class="truncate border-t border-line px-4 py-2.5 text-sm font-medium text-ink" x-text="hover && hover.name"></p>
                    </div>
                </template>
                {{-- Tıklayınca tam ekran önizleme --}}
                <template x-teleport="body">
                    <div x-cloak x-show="preview" role="dialog" aria-modal="true" :aria-label="preview && preview.name"
                        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/75 p-4 backdrop-blur-sm" @click.self="close()">
                        <figure class="relative flex max-h-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
                            <button type="button" class="absolute top-3 right-3 z-10 flex size-9 items-center justify-center rounded-full bg-white/90 text-ink shadow hover:bg-white" @click="close()" aria-label="Kapat">
                                <x-icon name="x" class="size-5" />
                            </button>
                            <img :src="preview && preview.src" :alt="preview && preview.name" class="max-h-[75vh] w-auto max-w-full object-contain">
                            <figcaption class="flex items-center justify-between gap-4 border-t border-line px-5 py-3">
                                <p class="min-w-0 truncate font-semibold text-ink" x-text="preview && preview.name"></p>
                                <a :href="preview && preview.url" class="shrink-0 text-sm font-medium text-primary hover:underline">Ürünü düzenle →</a>
                            </figcaption>
                        </figure>
                    </div>
                </template>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line p-4 text-sm text-muted">
                    <span>Toplam {{ number_format($products->total(), 0, ',', '.') }} ürün</span>
                    {{ $products->links() }}
                </div>
            @endif
        </x-card>
    @endif
</x-layouts.panel>
