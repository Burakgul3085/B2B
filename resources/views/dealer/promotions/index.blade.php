<x-layouts.panel :area="\App\Domain\Auth\Area::Dealer" title="Kampanyalar" wide>
    @error('cart')<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror
    @error('request')<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror

    <x-page-header title="Kampanyalar" subtitle="Size açık kupon ve kampanyalar ile size özel indirimdeki ürünler. Kampanya koşulu tutan siparişe kendiliğinden uygulanır; kupon kodunu sepette girersiniz. Siparişte tek kupon veya kampanya geçerlidir.">
        <x-button variant="secondary" icon="arrow-right" :href="route('dealer.cart')">Sepete git</x-button>
    </x-page-header>

    {{-- ============ Kampanya vitrini ============ --}}
    @if ($rows->isEmpty())
        <x-card><x-empty-state icon="gift" title="Şu an size açık kampanya yok" text="Toptancınız yeni bir kupon veya kampanya başlattığında burada görünür." /></x-card>
    @else
        <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($rows as ['promotion' => $promotion, 'remaining' => $remaining])
                @include('dealer.promotions._card')
            @endforeach
        </div>
    @endif

    {{-- ============ İndirimdeki ürünler ============ --}}
    <section class="mt-14">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="inline-flex items-center gap-2 text-xs font-bold tracking-[0.2em] text-red-600 uppercase"><x-icon name="percent" class="size-3.5" />Size özel</p>
                <h2 class="mt-2 text-2xl font-bold tracking-tight text-ink">İndirimdeki ürünler</h2>
            </div>
            @if ($discountTotal > $products->count())
                <a href="{{ route('dealer.catalog', ['indirim' => 1]) }}" class="inline-flex items-center gap-1.5 rounded-full border border-line bg-white px-4 py-2.5 text-sm font-semibold text-ink shadow-xs transition hover:border-primary/40 hover:text-primary">
                    Tümünü gör ({{ number_format($discountTotal, 0, ',', '.') }}) <x-icon name="arrow-right" class="size-4" />
                </a>
            @endif
        </div>
        @if ($products->isEmpty())
            <x-card class="mt-5"><x-empty-state icon="percent" title="Şu an indirimde ürün yok" text="Size özel ürün veya kategori indirimi tanımlandığında burada listelenir." /></x-card>
        @else
            <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($products as $product)
                    @include('dealer.catalog._card', ['product' => $product])
                @endforeach
            </div>
        @endif
    </section>
</x-layouts.panel>
