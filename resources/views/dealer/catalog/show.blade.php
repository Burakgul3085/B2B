@php use App\Domain\Catalog\StockLevel; use App\Domain\Inventory\Quantity; use App\Domain\Support\Money; use App\Models\Order; @endphp
@php $level = StockLevel::for($available, (string) $product->critical_stock_level, $settings->stock_display, $product->unit->name); @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Dealer" :title="$product->name">
    <x-page-header :title="$product->name" :subtitle="$product->sku.($product->brand ? ' · '.$product->brand->name : '')">
        <x-slot:breadcrumb><a href="{{ route('dealer.catalog') }}" class="hover:underline">Katalog</a>@if ($product->category) › {{ $product->category->fullName() }}@endif</x-slot:breadcrumb>
    </x-page-header>

    @error('cart')<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror
    @error('request')<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror

    <div class="grid gap-6 lg:grid-cols-5">
        <div class="lg:col-span-3" x-data="{ active: 0 }">
            {{-- Görsel beyaz bir sahnede, kırpılmadan ortalanır; beyaz fonu sahneyle birleşir (bkz. x-product-image). --}}
            @if ($product->images->isNotEmpty())
                <div class="isolate flex h-[min(70vh,36rem)] items-center justify-center rounded-2xl border border-line bg-white p-6 shadow-xs sm:p-10">
                    @foreach ($product->images as $i => $image)
                        <img x-show="active === {{ $i }}" @if ($i) x-cloak @endif src="{{ route('dealer.images.show', $image) }}" alt="{{ $product->name }}"
                            class="max-h-full max-w-full object-contain mix-blend-multiply">
                    @endforeach
                </div>
            @else
                <div class="flex aspect-[4/3] items-center justify-center rounded-2xl border border-line bg-white">
                    <x-icon name="image" class="size-12 text-slate-300" />
                </div>
            @endif
            @if ($product->images->count() > 1)
                <div class="mt-3 flex gap-2">
                    @foreach ($product->images as $i => $image)
                        <button type="button" @click="active = {{ $i }}" class="flex size-16 items-center justify-center overflow-hidden rounded-lg border-2 bg-white p-1" :class="active === {{ $i }} ? 'border-primary' : 'border-line'" aria-label="Görsel {{ $i + 1 }}">
                            <img src="{{ route('dealer.images.show', $image) }}" alt="" class="max-h-full max-w-full object-contain mix-blend-multiply">
                        </button>
                    @endforeach
                </div>
            @endif
            @if ($product->description)
                <x-card title="Açıklama" class="mt-6"><p class="text-sm whitespace-pre-line text-slate-700">{{ $product->description }}</p></x-card>
            @endif
        </div>

        <div class="space-y-6 lg:col-span-2">
            <x-card>
                <x-price-tag :price="$price" :display="$settings->price_display" size="lg" />
                @if ($price->discountName)<p class="mt-1 text-xs text-success">{{ $price->discountName }}</p>@endif
                <div class="mt-3 flex flex-wrap gap-1.5"><x-badge :tone="$level['tone']">{{ $level['label'] }}</x-badge>@if (\App\Domain\Catalog\NewProducts::isNew($product, $settings))<span class="rounded-md bg-violet-600 px-1.5 py-0.5 text-[11px] font-bold tracking-wide text-white uppercase">Yeni</span>@endif</div>
                <dl class="mt-4 grid grid-cols-2 gap-3 border-t border-line pt-4 text-sm">
                    <div><dt class="text-muted">Birim</dt><dd class="font-medium">{{ $product->unit->name }}</dd></div>
                    <div><dt class="text-muted">Koli / paket içi</dt><dd class="font-medium">{{ Quantity::format($product->pack_quantity) }}</dd></div>
                    <div><dt class="text-muted">En az sipariş</dt><dd class="font-medium">{{ Quantity::format($product->min_order_qty) }}</dd></div>
                    <div><dt class="text-muted">Artış</dt><dd class="font-medium">{{ Quantity::format($product->order_step) }}</dd></div>
                    @if ($product->barcode)<div class="col-span-2"><dt class="text-muted">Barkod</dt><dd class="font-mono text-xs">{{ $product->barcode }}</dd></div>@endif
                </dl>
                <div class="mt-5">
                    @include('dealer.catalog._add-form', ['product' => $product, 'level' => $level, 'hasOpenRequest' => $openRequest !== null])
                </div>
                <p class="mt-3 text-xs text-muted">Sepete eklemek stok ayırmaz; stok sipariş onayında kesinleşir.</p>
            </x-card>

            @if ($siblings->isNotEmpty())
                <x-card title="Diğer seçenekler">
                    <div class="flex flex-wrap gap-2">
                        <span class="rounded-full bg-primary px-3 py-1 text-sm text-white">{{ $product->variantLabel() }}</span>
                        @foreach ($siblings as $s)
                            <a href="{{ route('dealer.catalog.show', $s) }}" class="rounded-full px-3 py-1 text-sm ring-1 ring-line hover:ring-primary">{{ $s->variantLabel() ?? $s->name }}</a>
                        @endforeach
                    </div>
                </x-card>
            @endif

            <x-card title="Bu üründen geçmiş alımlarınız" :padding="false">
                @if ($history->isEmpty())
                    <p class="p-5 text-sm text-muted">Bu ürünü daha önce sipariş etmediniz.</p>
                @else
                    <ul class="divide-y divide-line text-sm">
                        @foreach ($history as $h)
                            <li class="flex items-center justify-between px-5 py-3">
                                <a href="{{ route('dealer.orders.show', $h->order) }}" class="text-primary hover:underline">{{ $h->order->order_no }}</a>
                                <span class="text-muted">{{ $h->order->created_at->format('d.m.Y') }}</span>
                                <span class="tabular-nums">{{ Quantity::format($h->effectiveQuantity()) }} × {{ Money::formatUnit($h->unit_price) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>
    </div>
</x-layouts.panel>
