@php
    use App\Domain\Support\Money;

    $variants = $product->activeVariants->sortBy(fn ($v) => [$v->sort, $v->id])->values();
    $variantData = $variants->map(fn ($v) => [
        'id' => $v->id,
        'option' => $v->option_value,
        'sku' => $v->sku,
        'price' => Money::format($v->price),
        'compare' => $v->hasDiscount() ? Money::format($v->compare_at_price) : null,
        'discount' => $v->discountPercent(),
        'available' => $available[$v->id] ?? 0,
    ])->all();
    $initial = $variants->search(fn ($v) => ($available[$v->id] ?? 0) > 0);
    $initial = $initial === false ? 0 : $initial;
    $imageUrl = fn ($image, $w = null) => route('store.media.product', array_filter(['storeImage' => $image, 'w' => $w]));
    $productUrl = route('store.product', $product->slug);

    // Yapılandırılmış veri: Google ürün sonuçları
    $offers = $variants->map(fn ($v) => [
        '@type' => 'Offer',
        'sku' => $v->sku,
        'price' => number_format((float) $v->price, 2, '.', ''),
        'priceCurrency' => 'TRY',
        'availability' => ($available[$v->id] ?? 0) > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
        'url' => $productUrl,
        'itemCondition' => 'https://schema.org/NewCondition',
    ])->all();
    $productJson = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $product->name,
        'description' => $product->seo_description ?: \Illuminate\Support\Str::limit((string) $product->description, 500),
        'image' => $product->images->map(fn ($i) => $imageUrl($i))->all() ?: null,
        'sku' => $variants->count() === 1 ? $variants->first()->sku : null,
        'gtin13' => $variants->count() === 1 && preg_match('/^\d{13}$/', (string) $variants->first()->barcode) ? $variants->first()->barcode : null,
        'brand' => $product->brand ? ['@type' => 'Brand', 'name' => $product->brand->name] : null,
        'offers' => count($offers) === 1 ? $offers[0] : [
            '@type' => 'AggregateOffer', 'priceCurrency' => 'TRY', 'offerCount' => count($offers),
            'lowPrice' => number_format((float) $variants->min('price'), 2, '.', ''), 'highPrice' => number_format((float) $variants->max('price'), 2, '.', ''),
            'offers' => $offers,
        ],
    ]);
    $crumbJson = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => collect([['Ana sayfa', route('store.home')], ...$breadcrumbs])
        ->values()->map(fn ($c, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $c[0], 'item' => $c[1] ?? $productUrl])->all()];
@endphp
<x-layouts.store :title="$product->seo_title ?: $product->name" :description="$product->seo_description ?: $product->description" :canonical="$productUrl"
    :og-image="$product->images->first() ? $imageUrl($product->images->first(), 1000) : null" og-type="product" :json-ld="[$productJson, $crumbJson]">
    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        @include('store.front._breadcrumbs', ['items' => $breadcrumbs])

        <div class="mt-6 grid gap-10 lg:grid-cols-2" x-data="productVariants(@alpine($variantData), {{ $initial }})">
            {{-- Görseller: imleçle büyütme (dokunmatikte kapalı) --}}
            <div x-data="{ active: 0 }">
                @if ($product->images->isNotEmpty())
                    <div class="relative isolate aspect-square overflow-hidden rounded-2xl border border-line bg-[#fff]"
                        x-data="imageZoom" :class="zoom ? 'cursor-zoom-out' : 'cursor-zoom-in'"
                        @pointerenter="enter($event)" @pointerleave="zoom = false"
                        @pointermove="move($event)">
                        <div class="absolute inset-0 flex items-center justify-center p-6 transition-transform duration-300 ease-out sm:p-10" :style="zoomStyle">
                            @foreach ($product->images as $i => $image)
                                <img x-show="active === {{ $i }}" @if ($i) x-cloak @endif src="{{ $imageUrl($image, 1000) }}" alt="{{ $product->name }}{{ $i ? ' görsel '.($i + 1) : '' }}"
                                    class="max-h-full max-w-full object-contain mix-blend-multiply" @if ($i) loading="lazy" @else fetchpriority="high" @endif>
                            @endforeach
                        </div>
                    </div>
                    @if ($product->images->count() > 1)
                        <div class="mt-3 flex gap-2 overflow-x-auto pb-1">
                            @foreach ($product->images as $i => $image)
                                <button type="button" @click="active = {{ $i }}" class="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-xl border-2 bg-[#fff] p-1.5"
                                    :class="active === {{ $i }} ? 'border-primary' : 'border-line'" aria-label="Görsel {{ $i + 1 }}">
                                    <img src="{{ $imageUrl($image, 320) }}" alt="" class="max-h-full max-w-full object-contain mix-blend-multiply" loading="lazy">
                                </button>
                            @endforeach
                        </div>
                    @endif
                @else
                    <div class="flex aspect-square items-center justify-center rounded-2xl border border-line bg-surface"><x-icon name="image" class="size-14 text-slate-300" /></div>
                @endif
            </div>

            {{-- Bilgi ve seçim --}}
            <div>
                @if ($product->brand)
                    <a href="{{ route('store.brand', $product->brand->slug) }}" class="text-sm font-semibold tracking-wider text-muted uppercase hover:text-primary">{{ $product->brand->name }}</a>
                @endif
                <h1 class="mt-1 text-2xl leading-tight font-bold tracking-tight sm:text-3xl">{{ $product->name }}</h1>
                <p class="mt-2 text-sm text-muted">Ürün kodu: <span x-text="v.sku">{{ $variantData[$initial]['sku'] }}</span></p>

                <div class="mt-6 flex flex-wrap items-baseline gap-3">
                    <span class="text-3xl font-bold tabular-nums" x-text="v.price">{{ $variantData[$initial]['price'] }}</span>
                    <template x-if="v.compare"><span class="text-lg text-muted line-through tabular-nums" x-text="v.compare"></span></template>
                    <template x-if="v.discount"><span class="rounded-full bg-red-600 px-2.5 py-1 text-xs font-bold text-white" x-text="'%' + v.discount + ' indirim'"></span></template>
                </div>
                <p class="mt-1 text-xs text-muted">KDV dahil</p>

                @if ($product->hasOptions())
                    <div class="mt-8">
                        <p class="text-sm font-semibold">{{ $product->variant_name }}: <span class="font-normal" x-text="v.option">{{ $variantData[$initial]['option'] }}</span></p>
                        <div class="mt-3 flex flex-wrap gap-2" role="radiogroup" aria-label="{{ $product->variant_name }}">
                            @foreach ($variantData as $i => $data)
                                <button type="button" role="radio" @click="select({{ $i }})" :aria-checked="index === {{ $i }}"
                                    :class="index === {{ $i }} ? 'border-primary bg-primary-soft text-primary ring-1 ring-primary' : 'border-line hover:border-slate-400'"
                                    class="relative rounded-xl border px-4 py-2 text-sm font-medium {{ $data['available'] ? '' : 'text-muted line-through decoration-slate-400' }}">{{ $data['option'] }}</button>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="mt-6">
                    <p x-show="v.available > 5" class="flex items-center gap-2 text-sm font-medium text-success"><span class="size-2 rounded-full bg-success"></span> Stokta</p>
                    <p x-show="v.available > 0 && v.available <= 5" x-cloak class="flex items-center gap-2 text-sm font-medium text-warning"><span class="size-2 rounded-full bg-warning"></span> <span x-text="'Son ' + v.available + ' adet'"></span></p>
                    <p x-show="v.available === 0" x-cloak class="flex items-center gap-2 text-sm font-medium text-danger"><span class="size-2 rounded-full bg-danger"></span> Tükendi</p>
                </div>

                {{-- Sepete ekleme: adet ve stok sunucuda yeniden denetlenir --}}
                <form method="POST" action="{{ route('store.cart.add') }}" x-data="addToCart" @submit.prevent="submit" class="mt-6 flex flex-wrap items-stretch gap-3">
                    @csrf
                    <input type="hidden" name="variant_id" :value="v.id" value="{{ $variantData[$initial]['id'] }}">
                    <div class="flex items-center rounded-xl border border-line">
                        <button type="button" class="px-3.5 py-3 text-lg text-muted hover:text-ink" @click="decrease()" aria-label="Azalt">−</button>
                        <input type="number" name="quantity" x-model.number="qty" min="1" :max="v.available" value="1" class="w-12 border-0 text-center font-semibold focus:ring-0" aria-label="Adet">
                        <button type="button" class="px-3.5 py-3 text-lg text-muted hover:text-ink" @click="increase()" aria-label="Artır">+</button>
                    </div>
                    <button :disabled="v.available === 0" class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-primary px-6 py-3 font-semibold text-white transition hover:bg-primary-hover disabled:cursor-not-allowed disabled:opacity-50">
                        <x-icon name="cart" class="size-5" /> <span x-text="v.available === 0 ? 'Tükendi' : 'Sepete ekle'">Sepete ekle</span>
                    </button>
                </form>

                @if ($product->description)
                    <div class="mt-10 border-t border-line pt-6">
                        <h2 class="text-base font-semibold">Ürün açıklaması</h2>
                        <p class="mt-3 text-sm leading-relaxed whitespace-pre-line text-slate-700">{{ $product->description }}</p>
                    </div>
                @endif
            </div>
        </div>

        @if ($related->isNotEmpty())
            <section class="mt-16" aria-labelledby="benzer">
                <h2 id="benzer" class="text-xl font-bold tracking-tight">Benzer ürünler</h2>
                <div class="mt-6 grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach ($related as $item)
                        @include('store.front._card', ['product' => $item])
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-layouts.store>
