{{-- Markalar: logolu markalar önce; logosu yoksa adı. --}}
@if ($brands->isNotEmpty())
    <section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8" aria-labelledby="markalar">
        @include('store.front.home._heading', ['block' => $block, 'eyebrow' => 'Markalar', 'color' => 'navy', 'link' => [route('store.brands'), 'Tüm markalar']])
        <div class="grid grid-cols-3 gap-3 sm:grid-cols-4 lg:grid-cols-6">
            @foreach ($brands as $brand)
                <a href="{{ route('store.brand', $brand->slug) }}" class="group flex aspect-[3/2] items-center justify-center rounded-2xl border border-line bg-[#fff] p-4 transition hover:-translate-y-0.5 hover:border-navy-ink/30 hover:shadow-lg hover:shadow-navy/5">
                    @if ($brand->logo_path)
                        <img src="{{ route('store.media.brand', $brand) }}?v={{ $brand->updated_at?->timestamp }}" alt="{{ $brand->name }}" loading="lazy" class="max-h-12 max-w-full object-contain opacity-80 grayscale transition group-hover:opacity-100 group-hover:grayscale-0">
                    @else
                        <span class="text-center font-display text-sm font-extrabold text-slate-700 group-hover:text-navy-ink">{{ $brand->name }}</span>
                    @endif
                </a>
            @endforeach
        </div>
    </section>
@endif
