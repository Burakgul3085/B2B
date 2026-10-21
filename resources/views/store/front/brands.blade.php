<x-layouts.store title="Markalar" description="Mağazamızdaki tüm markalar." :canonical="route('store.brands')">
    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        @include('store.front._breadcrumbs', ['items' => [['Markalar', null]]])
        <h1 class="mt-4 text-2xl font-bold tracking-tight sm:text-3xl">Markalar</h1>

        @if ($brands->isEmpty())
            <p class="mt-8 text-muted">Henüz marka yok.</p>
        @else
            <div class="mt-8 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                @foreach ($brands as $brand)
                    <a href="{{ route('store.brand', $brand->slug) }}" class="group flex flex-col items-center rounded-2xl border border-line bg-white p-5 text-center transition hover:border-primary/40 hover:shadow-md">
                        <span class="flex h-16 w-full items-center justify-center">
                            @if ($brand->logo_path)
                                <img src="{{ route('store.media.brand', $brand) }}?v={{ $brand->updated_at?->timestamp }}" alt="{{ $brand->name }} logosu" class="max-h-full max-w-full object-contain" loading="lazy">
                            @else
                                <span class="text-2xl font-bold text-primary">{{ $brand->name }}</span>
                            @endif
                        </span>
                        <span class="mt-4 text-sm font-semibold group-hover:text-primary">{{ $brand->name }}</span>
                        <span class="text-xs text-muted">{{ $brand->products_count }} ürün</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.store>
