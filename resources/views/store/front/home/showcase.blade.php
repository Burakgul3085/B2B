{{-- Sekmeli ürün vitrini: öne çıkanlar, çok satanlar (son 90 gün), yeni gelenler, indirimdekiler. Boş sekme gösterilmez. --}}
@if ($showcase)
    @php
        $links = ['featured' => route('store.products'), 'best' => route('store.products'), 'new' => route('store.products', ['sirala' => 'yeni']), 'sale' => route('store.products', ['indirim' => 1])];
    @endphp
    <section class="bg-surface py-16 sm:py-20" aria-labelledby="vitrin" x-data="tabs(@alpine(array_key_first($showcase)))">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        @include('store.front.home._heading', ['block' => $block, 'eyebrow' => 'Vitrin', 'color' => 'green'])
        <div class="-mt-3 mb-6 flex gap-2 overflow-x-auto pb-1" role="tablist">
            @foreach ($showcase as $key => $tab)
                <button type="button" role="tab" @click="select(@alpine($key))" :aria-selected="is(@alpine($key))"
                    :class="is(@alpine($key)) ? 'bg-navy text-white shadow-sm' : 'bg-white text-ink/75 ring-1 ring-line hover:text-ink'"
                    class="shrink-0 rounded-full px-5 py-2.5 text-sm font-semibold transition">{{ $tab['label'] }}</button>
            @endforeach
        </div>
        @foreach ($showcase as $key => $tab)
            <div x-show="is(@alpine($key))" @if (! $loop->first) x-cloak @endif role="tabpanel">
                <div class="grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach ($tab['products'] as $product)
                        @include('store.front._card', ['product' => $product])
                    @endforeach
                </div>
                <div class="mt-8 text-center">
                    <a href="{{ $links[$key] }}" class="inline-flex items-center gap-2 rounded-xl border border-line bg-white px-6 py-3 text-sm font-semibold hover:border-navy hover:bg-navy hover:text-white">Tümünü gör <x-icon name="arrow-right" class="size-4" /></a>
                </div>
            </div>
        @endforeach
    </div>
    </section>
@endif
