{{-- Seçki sayfası: renkli başlık alanı + ürünler (seçki sırasıyla). --}}
<x-layouts.store :title="$collection->title" :description="$collection->subtitle" :canonical="route('store.collection', $collection->slug)"
    :ogImage="$collection->image_path ? route('store.media.collection', ['storeCollection' => $collection->id, 'w' => 1200]) : null">
    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        @include('store.front._breadcrumbs', ['items' => [['Seçkiler', null], [$collection->title, null]]])
        <section class="relative mt-4 overflow-hidden rounded-3xl p-8 text-white sm:p-12 {{ $palette['solid'] }}">
            @if ($collection->image_path)
                <x-store.fit-image :src="route('store.media.collection', ['storeCollection' => $collection->id, 'w' => 1600])" fill loading="eager" />
                <span class="absolute inset-0 bg-linear-to-r from-navy/90 via-navy/50 to-transparent"></span>
            @endif
            <div class="relative max-w-xl">
                <p class="text-xs font-bold tracking-[0.25em] text-white/80 uppercase">Seçki · {{ $products->count() }} ürün</p>
                <h1 class="mt-3 font-display text-3xl leading-tight font-extrabold sm:text-5xl">{{ $collection->title }}</h1>
                @if ($collection->subtitle)<p class="mt-3 text-lg text-white/85">{{ $collection->subtitle }}</p>@endif
            </div>
        </section>

        @if ($products->isEmpty())
            <x-empty-state icon="package" title="Bu seçkide şu an ürün yok" text="Diğer ürünlerimize göz atabilirsiniz." class="mt-10" />
        @else
            <div class="mt-10 grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($products as $product)
                    @include('store.front._card', ['product' => $product])
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.store>
