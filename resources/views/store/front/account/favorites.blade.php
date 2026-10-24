<x-store.account-shell title="Favorilerim" active="favorites">
    <p class="text-xs font-bold tracking-[0.18em] text-puzzle-orange uppercase">Hesabım</p>
    <h1 class="mt-1 font-display text-3xl font-extrabold tracking-tight sm:text-4xl">Favorilerim</h1>
    <p class="mt-1 text-muted">{{ $products->total() }} ürün</p>

    @if ($products->isEmpty())
        <div class="mt-8 flex flex-col items-center rounded-3xl border border-line bg-white px-6 py-14 text-center">
            <span class="flex size-16 items-center justify-center rounded-2xl bg-puzzle-orange-soft text-puzzle-orange"><x-icon name="heart" class="size-8" /></span>
            <p class="mt-4 font-display text-lg font-extrabold">Favori listeniz boş</p>
            <p class="mt-1 text-sm text-muted">Beğendiğiniz ürünlerdeki kalbe dokunun, burada toplansın.</p>
            <a href="{{ route('store.products') }}" class="mt-6 inline-flex h-11 items-center rounded-xl bg-navy px-5 text-sm font-semibold text-white hover:bg-puzzle-orange">Ürünlere göz atın</a>
        </div>
    @else
        <div class="mt-8 grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3">
            @foreach ($products as $product)
                @include('store.front._card', ['product' => $product])
            @endforeach
        </div>
        <div class="mt-10">{{ $products->links() }}</div>
    @endif
</x-store.account-shell>
