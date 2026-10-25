{{-- Kategori kutuları: ana kategoriler kendi yapboz rengiyle; görsel varsa sağ altta. --}}
@if ($categories->isNotEmpty())
    <section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8" aria-labelledby="kategoriler">
        @include('store.front.home._heading', ['block' => $block, 'eyebrow' => 'Kategoriler', 'color' => 'blue', 'link' => [route('store.products'), 'Tüm ürünler']])
        <div class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 lg:grid-cols-4">
            @foreach ($categories as $category)
                @php $c = \App\Domain\Store\Storefront\Palette::classes($category->palette); @endphp
                <a href="{{ route('store.category', $category->slug) }}" class="group relative flex min-h-40 flex-col overflow-hidden rounded-3xl p-5 transition hover:-translate-y-1 hover:shadow-xl hover:shadow-navy/10 sm:min-h-48 {{ $c['soft'] }}">
                    <span class="relative z-10 font-display text-lg leading-tight font-extrabold sm:text-xl">{{ $category->name }}</span>
                    @if ($category->tagline)<span class="relative z-10 mt-1 line-clamp-2 max-w-[70%] text-xs text-ink/70 sm:text-sm">{{ $category->tagline }}</span>@endif
                    <span class="relative z-10 mt-auto inline-flex items-center gap-1.5 pt-4 text-sm font-semibold {{ $c['text'] }}">
                        {{ $category->product_count }} ürün <x-icon name="arrow-right" class="size-4 transition group-hover:translate-x-1" />
                    </span>
                    @if ($category->image_path)
                        <x-store.fit-image :src="route('store.media.category', ['storeCategory' => $category->id, 'w' => 480])"
                            class="!absolute -right-4 -bottom-4 size-32 rounded-2xl bg-white shadow-lg transition duration-500 group-hover:scale-105 group-hover:-rotate-3 sm:size-36" />
                    @else
                        <span class="absolute -right-5 -bottom-5 flex size-28 rotate-12 items-center justify-center rounded-3xl font-display text-5xl font-extrabold text-white/90 {{ $c['solid'] }}" aria-hidden="true">{{ mb_substr($category->name, 0, 1) }}</span>
                    @endif
                </a>
            @endforeach
        </div>
    </section>
@endif
