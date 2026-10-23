{{-- Son bakılan ürünler: ziyaretçinin kendi oturumundan; hiç ürüne bakılmadıysa görünmez. --}}
@if ($recent->isNotEmpty())
    <section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8" aria-label="Son bakılan ürünler">
        @include('store.front.home._heading', ['block' => $block, 'eyebrow' => 'Geçmişiniz', 'color' => 'blue'])
        <div class="grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($recent as $product)
                @include('store.front._card', ['product' => $product])
            @endforeach
        </div>
    </section>
@endif
