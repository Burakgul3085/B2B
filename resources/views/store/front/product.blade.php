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
    $ask = $info['whatsapp'] ? 'https://wa.me/'.$info['whatsapp'].'?text='.rawurlencode("Merhaba, bu ürün hakkında bilgi almak istiyorum: {$product->name} {$productUrl}") : null;
    $share = 'https://wa.me/?text='.rawurlencode("{$product->name} {$productUrl}");

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
                    <a href="{{ route('store.brand', $product->brand->slug) }}" class="text-xs font-bold tracking-[0.14em] text-muted uppercase hover:text-navy-ink">{{ $product->brand->name }}</a>
                @endif
                <h1 class="mt-1 font-display text-2xl leading-tight font-extrabold tracking-tight sm:text-3xl">{{ $product->name }}</h1>
                <p class="mt-2 text-sm text-muted">Ürün kodu: <span x-text="v.sku">{{ $variantData[$initial]['sku'] }}</span></p>

                <div class="mt-6 rounded-2xl bg-surface px-5 py-4">
                    <div class="flex flex-wrap items-baseline gap-3">
                        <span class="font-display text-3xl font-extrabold tabular-nums sm:text-4xl" :class="v.compare ? 'text-puzzle-orange' : ''" x-text="v.price">{{ $variantData[$initial]['price'] }}</span>
                        <template x-if="v.compare"><span class="text-lg text-muted line-through tabular-nums" x-text="v.compare"></span></template>
                        <template x-if="v.discount"><span class="rounded-lg bg-puzzle-orange px-2.5 py-1 text-xs font-extrabold text-white" x-text="'%' + v.discount + ' indirim'"></span></template>
                    </div>
                    <p class="mt-1 text-xs text-muted">KDV dahil</p>
                </div>

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
                    <button :disabled="v.available === 0" class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-navy px-6 py-3 font-semibold text-white shadow-lg shadow-navy/15 transition hover:bg-puzzle-orange disabled:cursor-not-allowed disabled:opacity-50">
                        <x-icon name="cart" class="size-5" /> <span x-text="v.available === 0 ? 'Tükendi' : 'Sepete ekle'">Sepete ekle</span>
                    </button>
                </form>

                <div class="mt-4 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm">
                    @if ($ask)
                        <a href="{{ $ask }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 font-semibold text-puzzle-green hover:underline"><x-icon name="message-circle" class="size-4" /> WhatsApp ile sor</a>
                    @endif
                    <span class="flex items-center gap-3 text-muted">
                        Paylaş:
                        <a href="{{ $share }}" target="_blank" rel="noopener" class="hover:text-ink" aria-label="WhatsApp'ta paylaş"><x-icon name="message-circle" class="size-4" /></a>
                        <button type="button" x-data="copyCode(@alpine($productUrl))" @click="copy()" class="inline-flex items-center gap-1 hover:text-ink" aria-label="Bağlantıyı kopyala">
                            <x-icon name="link" class="size-4" /> <span x-show="copied" x-cloak class="text-xs text-success">Kopyalandı</span>
                        </button>
                    </span>
                </div>

                {{-- Teslimat özeti: yalnızca ayarlanmış bilgiler --}}
                <ul class="mt-6 divide-y divide-line rounded-2xl border border-line text-sm">
                    @if ($info['cutoff'])
                        <li class="flex gap-3 px-4 py-3"><x-icon name="clock" class="size-5 shrink-0 text-puzzle-blue" /><span>Hafta içi saat <strong>{{ $info['cutoff'] }}</strong> öncesi verilen siparişler aynı gün kargoya verilir.</span></li>
                    @endif
                    <li class="flex gap-3 px-4 py-3"><x-icon name="truck" class="size-5 shrink-0 text-puzzle-orange" /><span>
                        @if ($info['free_threshold'])<strong>{{ Money::format($info['free_threshold']) }}</strong> ve üzeri siparişlerde kargo ücretsiz.@if ($info['cargo_fee']) Altında kargo ücreti {{ Money::format($info['cargo_fee']) }}.@endif
                        @elseif ($info['cargo_fee'])Kargo ücreti {{ Money::format($info['cargo_fee']) }}.
                        @else Türkiye geneline kargo ile gönderim.@endif
                    </span></li>
                    @if ($info['local'])
                        <li class="flex gap-3 px-4 py-3"><x-icon name="map-pin" class="size-5 shrink-0 text-puzzle-green" /><span>Eskişehir içinde kendi aracımızla teslimat.</span></li>
                    @endif
                    <li class="flex gap-3 px-4 py-3"><x-icon name="refresh" class="size-5 shrink-0 text-puzzle-yellow" /><span>Teslimattan sonra {{ $info['return_days'] }} gün içinde iade hakkı.</span></li>
                </ul>

                @if ($info['perks'])
                    <div class="mt-4 grid grid-cols-3 gap-2">
                        @foreach (array_slice($info['perks'], 0, 3) as $perk)
                            <div class="flex flex-col items-center gap-1.5 rounded-xl bg-surface px-2 py-3 text-center">
                                <x-icon :name="$perk['icon']" class="size-5 text-navy-ink" />
                                <span class="text-xs leading-tight font-semibold">{{ $perk['title'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Ayrıntılar: açıklama ve teslimat/iade sekmeleri --}}
        <section class="mt-14" x-data="tabs(@alpine($product->description ? 'aciklama' : 'teslimat'))">
            <div class="flex gap-6 border-b border-line" role="tablist">
                @if ($product->description)
                    <button type="button" role="tab" @click="select('aciklama')" :aria-selected="is('aciklama')" :class="is('aciklama') ? 'border-navy text-ink' : 'border-transparent text-muted hover:text-ink'" class="-mb-px border-b-2 pb-3 text-sm font-semibold sm:text-base">Ürün açıklaması</button>
                @endif
                <button type="button" role="tab" @click="select('teslimat')" :aria-selected="is('teslimat')" :class="is('teslimat') ? 'border-navy text-ink' : 'border-transparent text-muted hover:text-ink'" class="-mb-px border-b-2 pb-3 text-sm font-semibold sm:text-base">Teslimat ve iade</button>
            </div>
            @if ($product->description)
                <div x-show="is('aciklama')" role="tabpanel" class="max-w-3xl py-6">
                    <p class="text-sm leading-relaxed whitespace-pre-line text-slate-700 sm:text-base">{{ $product->description }}</p>
                </div>
            @endif
            <div x-show="is('teslimat')" @if ($product->description) x-cloak @endif role="tabpanel" class="max-w-3xl space-y-3 py-6 text-sm leading-relaxed text-slate-700 sm:text-base">
                <p>Siparişiniz ödeme onayından sonra hazırlanır ve kargoya verilir; kargo takip numarası e-posta ile gönderilir.@if ($info['local']) Eskişehir'deki adreslere kendi aracımızla da teslim ediyoruz.@endif</p>
                <p>Teslim aldığınız ürünü {{ $info['return_days'] }} gün içinde, kullanılmamış ve yeniden satılabilir durumda iade edebilirsiniz. İade talebini hesabınızdaki sipariş sayfasından açabilirsiniz.</p>
                <p><a href="{{ route('store.legal', 'iptal-iade') }}" class="font-semibold text-navy-ink underline-offset-4 hover:underline">İptal ve İade Koşulları</a></p>
            </div>
        </section>

        @if ($related->isNotEmpty())
            <section class="mt-12" aria-labelledby="benzer">
                <p class="text-xs font-bold tracking-[0.14em] text-puzzle-green uppercase">Aynı kategoriden</p>
                <h2 id="benzer" class="mt-1 font-display text-2xl font-extrabold tracking-tight">Benzer ürünler</h2>
                <div class="mt-6 grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach ($related as $item)
                        @include('store.front._card', ['product' => $item])
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-layouts.store>
