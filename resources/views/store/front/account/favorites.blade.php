<x-layouts.store title="Favorilerim" :noindex="true">
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <a href="{{ route('store.account') }}" class="text-sm text-muted hover:text-primary">← Hesabım</a>
        <h1 class="mt-3 font-display text-2xl font-extrabold tracking-tight sm:text-3xl">Favorilerim</h1>
        <p class="mt-1 text-sm text-muted">{{ $products->total() }} ürün</p>

        @if ($products->isEmpty())
            <div class="mt-8 rounded-2xl border border-dashed border-line px-6 py-14 text-center">
                <x-icon name="heart" class="mx-auto size-10 text-puzzle-orange" />
                <p class="mt-3 font-semibold">Favori listeniz boş</p>
                <p class="mt-1 text-sm text-muted">Beğendiğiniz ürünlerdeki kalbe dokunun, burada toplansın.</p>
                <x-button :href="route('store.products')" class="mt-5">Ürünlere göz atın</x-button>
            </div>
        @else
            <div class="mt-6 grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($products as $product)
                    @include('store.front._card', ['product' => $product])
                @endforeach
            </div>
            <div class="mt-10">{{ $products->links() }}</div>
        @endif
    </div>
</x-layouts.store>
