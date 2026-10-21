@php
    use App\Domain\Store\Catalog\ProductService;
    use App\Domain\Support\Money;

    $hasOptions = $product->hasOptions();
    $newBag = $errors->getBag('newVariant');
@endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" :title="$product->name">
    <x-page-header :title="$product->name" :subtitle="$product->category?->fullName() ?? 'Kategorisiz'">
        <x-slot:breadcrumb><a href="{{ route('store_admin.products.index') }}" class="hover:underline">Ürünler</a></x-slot:breadcrumb>
        @if ($product->is_published)<x-badge tone="success">Yayında</x-badge>@else<x-badge tone="warning">Taslak</x-badge>@endif
        @if ($product->is_featured)<x-badge tone="primary">Öne çıkan</x-badge>@endif
    </x-page-header>

    @error('product')<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            {{-- Ürün bilgileri --}}
            <x-card title="Ürün bilgileri">
                <form method="POST" action="{{ route('store_admin.products.update', $product) }}" x-data="{ option: @js(old('variant_name', $product->variant_name ?? '')) }">
                    @csrf @method('PUT')
                    @include('store.admin.catalog.products._fields', ['product' => $product])
                    @unless ($hasOptions)
                        {{-- Tek seçenekli ürüne seçenek adı verilirken mevcut ürünün değeri de istenir --}}
                        <div x-show="option.trim() !== ''" x-cloak class="mt-5 max-w-xs">
                            <x-field name="first_option_value" label="Bu ürünün seçenek değeri" placeholder="ör. Mavi" maxlength="80"
                                hint="Mevcut stok kodu bu seçeneğe ait olur; diğer seçenekleri sonra eklersiniz." />
                        </div>
                    @endunless
                    <div class="mt-6"><x-button>Kaydet</x-button></div>
                </form>
            </x-card>

            {{-- Fiyat ve seçenekler --}}
            @if (! $hasOptions)
                @php $variant = $product->variants->first(); $bag = 'variant'.$variant->id; @endphp
                <x-card title="Fiyat ve stok kodu" description="Fiyatlar KDV dahildir. Renk, boyut gibi seçenekler eklemek için yukarıda bir seçenek adı verin.">
                    <form method="POST" action="{{ route('store_admin.variants.update', $variant) }}" class="space-y-5">
                        @csrf @method('PUT')
                        <input type="hidden" name="is_active" value="1">
                        @include('store.admin.catalog.products._variant-fields', ['prefix' => null, 'bag' => $bag, 'variant' => $variant, 'optionLabel' => null, 'useOld' => $errors->getBag($bag)->any()])
                        <x-button>Fiyatı kaydet</x-button>
                    </form>
                </x-card>
            @else
                <x-card :title="$product->variant_name.' seçenekleri'" :description="$product->variants->count().' seçenek · fiyatlar KDV dahil'" :padding="false">
                    <ul class="divide-y divide-line">
                        @foreach ($product->variants as $variant)
                            @php $bag = 'variant'.$variant->id; $failed = $errors->getBag($bag)->any(); @endphp
                            <li x-data="{ open: @js($failed) }">
                                <div class="flex flex-wrap items-center gap-3 px-5 py-3">
                                    <button type="button" @click="open = ! open" class="flex min-w-0 flex-1 items-center gap-3 text-left" :aria-expanded="open">
                                        <x-icon name="chevron-down" class="size-4 shrink-0 text-muted transition" ::class="open && 'rotate-180'" />
                                        <span class="min-w-0">
                                            <span class="font-medium text-ink">{{ $variant->option_value }}</span>
                                            <span class="block text-xs text-muted">{{ $variant->sku }}@if ($variant->barcode) · {{ $variant->barcode }}@endif</span>
                                        </span>
                                    </button>
                                    <span class="text-right tabular-nums">
                                        <span class="font-semibold">{{ Money::format($variant->price) }}</span>
                                        @if ($variant->hasDiscount())<span class="ml-1 text-xs text-muted line-through">{{ Money::format($variant->compare_at_price) }}</span>@endif
                                    </span>
                                    @unless ($variant->is_active)<x-badge>Pasif</x-badge>@endunless
                                </div>
                                <div x-show="open" x-cloak class="border-t border-line bg-slate-50/60 px-5 py-4">
                                    <form method="POST" action="{{ route('store_admin.variants.update', $variant) }}" class="space-y-4">
                                        @csrf @method('PUT')
                                        @include('store.admin.catalog.products._variant-fields', ['prefix' => null, 'bag' => $bag, 'variant' => $variant, 'optionLabel' => $product->variant_name, 'useOld' => $failed])
                                        <div class="flex flex-wrap items-center justify-between gap-3">
                                            <x-toggle name="is_active" label="Satışta" :checked="$variant->is_active" hint="Kapalıysa bu seçenek mağazada seçilemez." />
                                            <x-button size="sm">Kaydet</x-button>
                                        </div>
                                    </form>
                                    @if ($product->variants->count() > 1)
                                        <form method="POST" action="{{ route('store_admin.variants.destroy', $variant) }}" class="mt-3 text-right" data-confirm="&quot;{{ $variant->option_value }}&quot; seçeneği silinsin mi?">
                                            @csrf @method('DELETE')
                                            <button class="text-sm text-danger hover:underline">Seçeneği sil</button>
                                        </form>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    <div class="border-t border-line px-5 py-4" x-data="{ open: @js($newBag->any()) }">
                        <button type="button" x-show="! open" @click="open = true" class="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline">
                            <x-icon name="plus" class="size-4" /> Yeni {{ mb_strtolower($product->variant_name) }} ekle
                        </button>
                        <form x-show="open" x-cloak method="POST" action="{{ route('store_admin.variants.store', $product) }}" class="space-y-4">
                            @csrf
                            <p class="text-sm font-medium text-ink">Yeni {{ mb_strtolower($product->variant_name) }}</p>
                            @include('store.admin.catalog.products._variant-fields', ['prefix' => null, 'bag' => 'newVariant', 'variant' => null, 'optionLabel' => $product->variant_name, 'useOld' => $newBag->any()])
                            <div class="flex gap-2">
                                <x-button size="sm" icon="plus">Ekle</x-button>
                                <x-button type="button" variant="ghost" size="sm" @click="open = false">Vazgeç</x-button>
                            </div>
                        </form>
                    </div>
                </x-card>
            @endif

            {{-- Stok --}}
            <x-card title="Stok" description="Mağaza stoğu. Satılabilir = stok − ödeme aşamasında ayrılan." :padding="false">
                <x-slot:actions><a href="{{ route('store_admin.stock.movements', ['q' => $product->name]) }}" class="text-sm font-medium text-primary hover:underline">Hareketler</a></x-slot:actions>
                <ul class="divide-y divide-line">
                    @foreach ($product->variants as $variant)
                        @php $reserved = $held[$variant->id] ?? 0; @endphp
                        <li x-data="{ open: false }">
                            <div class="flex flex-wrap items-center gap-3 px-5 py-3">
                                <span class="min-w-0 flex-1">
                                    <span class="font-medium text-ink">{{ $variant->option_value ?? $product->name }}</span>
                                    <span class="block text-xs text-muted">{{ $variant->sku }}@if ($variant->critical_stock !== null) · kritik seviye {{ $variant->critical_stock }}@endif</span>
                                </span>
                                <span class="text-right text-sm tabular-nums">
                                    <span @class(['text-lg font-semibold', 'text-danger' => $variant->stock_quantity === 0, 'text-warning' => $variant->stock_quantity > 0 && $variant->isCritical()])>{{ number_format($variant->stock_quantity, 0, ',', '.') }}</span>
                                    <span class="text-muted">adet</span>
                                    @if ($reserved)<span class="block text-xs text-muted">{{ $reserved }} ayrılmış</span>@endif
                                </span>
                                <button type="button" @click="open = ! open" class="text-sm font-medium text-primary hover:underline" :aria-expanded="open">Stok işlemi</button>
                            </div>
                            <div x-show="open" x-cloak class="border-t border-line bg-slate-50/60 px-5 py-4">
                                @include('store.admin.stock._actions', ['variant' => $variant])
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        </div>

        <div class="space-y-6">
            {{-- Görseller --}}
            <x-card title="Görseller" :description="'İlk görsel ana görseldir. En fazla '.ProductService::MAX_IMAGES.'; JPG, PNG veya WEBP, 5 MB. Beyaz kenarlar otomatik kırpılır.'">
                @error('images')<p class="mb-3 text-sm text-danger">{{ $message }}</p>@enderror
                @error('images.*')<p class="mb-3 text-sm text-danger">{{ $message }}</p>@enderror
                @if ($product->images->isNotEmpty())
                    <div class="mb-4 grid grid-cols-3 gap-2">
                        @foreach ($product->images as $image)
                            <div class="group relative overflow-hidden rounded-lg border border-line bg-white">
                                <span class="flex aspect-square items-center justify-center p-1.5">
                                    <img src="{{ route('store_admin.images.show', $image) }}" alt="" class="max-h-full max-w-full object-contain" loading="lazy">
                                </span>
                                @if ($loop->first)<span class="absolute top-1 left-1 rounded bg-primary px-1.5 py-0.5 text-[10px] font-semibold text-white">Ana</span>@endif
                                <div class="absolute inset-x-0 bottom-0 flex justify-between bg-white/95 px-1.5 py-1 text-[11px] opacity-0 transition group-hover:opacity-100 group-focus-within:opacity-100">
                                    @unless ($loop->first)
                                        <form method="POST" action="{{ route('store_admin.images.primary', $image) }}">@csrf @method('PUT')<button class="font-medium text-primary hover:underline">Ana yap</button></form>
                                    @else<span></span>@endunless
                                    <form method="POST" action="{{ route('store_admin.images.destroy', $image) }}" data-confirm="Görsel kaldırılsın mı?">@csrf @method('DELETE')<button class="text-danger hover:underline">Kaldır</button></form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
                @if ($product->images->count() < ProductService::MAX_IMAGES)
                    <form method="POST" action="{{ route('store_admin.images.store', $product) }}" enctype="multipart/form-data" class="space-y-3">
                        @csrf
                        <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple required aria-label="Görsel dosyaları"
                            class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium hover:file:bg-slate-200">
                        <x-button variant="secondary" icon="upload" size="sm">Yükle</x-button>
                    </form>
                @endif
            </x-card>

            <x-card title="Mağaza adresi">
                <p class="text-sm break-all text-muted">{{ config('store.domain') }}/urun/<span class="font-medium text-ink">{{ $product->slug }}</span></p>
                <p class="mt-2 text-xs text-muted">Mağaza vitrini yayına çıktığında ürün bu adreste görünür.</p>
            </x-card>

            <x-card title="Ürünü sil">
                <p class="mb-4 text-sm text-muted">Ürün, seçenekleri ve görselleri kalıcı olarak silinir. Mağazadan yalnızca kaldırmak için "Mağazada yayında" seçeneğini kapatın.</p>
                <form method="POST" action="{{ route('store_admin.products.destroy', $product) }}" data-confirm="&quot;{{ $product->name }}&quot; kalıcı olarak silinsin mi?">
                    @csrf @method('DELETE')
                    <x-button variant="danger" icon="trash" size="sm">Ürünü sil</x-button>
                </form>
            </x-card>
        </div>
    </div>
</x-layouts.panel>
