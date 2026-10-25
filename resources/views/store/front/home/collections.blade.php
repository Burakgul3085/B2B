{{-- Seçkiler: yönetimin hazırladığı temalı ürün listeleri (en fazla 4). --}}
@if ($collections->isNotEmpty())
    <section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8" aria-labelledby="seckiler">
        @include('store.front.home._heading', ['block' => $block, 'eyebrow' => 'Seçkiler', 'color' => 'yellow'])
        <div class="grid gap-4 sm:grid-cols-2 {{ [1 => 'lg:grid-cols-1', 2 => 'lg:grid-cols-2', 3 => 'lg:grid-cols-3', 4 => 'lg:grid-cols-4'][min(4, $collections->count())] }}">
            @foreach ($collections as $collection)
                @php $c = \App\Domain\Store\Storefront\Palette::classes($collection->color ?: \App\Domain\Store\Storefront\Palette::CYCLE[$loop->index % 4]); @endphp
                <a href="{{ route('store.collection', $collection->slug) }}" class="group relative flex aspect-[4/5] flex-col justify-end overflow-hidden rounded-3xl p-6 text-white {{ $c['solid'] }}">
                    @if ($collection->image_path)
                        <x-store.fit-image :src="route('store.media.collection', ['storeCollection' => $collection->id, 'w' => 720])" fill />
                        <span class="absolute inset-0 bg-linear-to-t from-navy/90 via-navy/30 to-transparent"></span>
                    @else
                        <span class="pointer-events-none absolute -top-10 -right-10 grid size-48 rotate-12 grid-cols-2 gap-2 opacity-25" aria-hidden="true">
                            <span class="rounded-2xl bg-white"></span><span class="rounded-2xl bg-white"></span><span class="rounded-2xl bg-white"></span><span class="rounded-2xl bg-white"></span>
                        </span>
                    @endif
                    <span class="relative text-xs font-bold tracking-[0.2em] text-white/80 uppercase">{{ $collection->products_count }} ürün</span>
                    <span class="relative mt-2 font-display text-2xl leading-tight font-extrabold">{{ $collection->title }}</span>
                    @if ($collection->subtitle)<span class="relative mt-1 text-sm text-white/80">{{ $collection->subtitle }}</span>@endif
                    <span class="relative mt-5 inline-flex w-fit items-center gap-2 rounded-xl bg-white px-4 py-2 text-sm font-bold text-navy transition group-hover:bg-puzzle-sun">Keşfet <x-icon name="arrow-right" class="size-4" /></span>
                </a>
            @endforeach
        </div>
    </section>
@endif
