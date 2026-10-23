@php
    $title = $category ? $category->name.' · Blog' : 'Blog';
    $paged = $posts->currentPage() > 1;
@endphp
<x-layouts.store :title="$title" description="Okul, ofis ve sanat malzemeleri üzerine öneriler, rehberler ve mağazamızdan haberler."
    :canonical="$category ? route('store.blog.category', $category->slug) : route('store.blog')" :noindex="$paged">
    {{-- Başlık alanı: lacivert zemin, yapboz parçaları --}}
    <section class="relative overflow-hidden bg-navy text-white">
        <div class="pointer-events-none absolute -top-10 -right-10 hidden rotate-12 grid-cols-2 gap-3 opacity-90 md:grid" aria-hidden="true">
            <span class="size-24 rounded-3xl bg-puzzle-orange"></span><span class="size-24 rounded-3xl bg-puzzle-green"></span>
            <span class="size-24 rounded-3xl bg-puzzle-blue"></span><span class="size-24 rounded-3xl bg-puzzle-yellow"></span>
        </div>
        <div class="relative mx-auto max-w-7xl px-4 py-12 sm:px-6 sm:py-16 lg:px-8">
            <nav class="text-sm text-white/60" aria-label="Konum"><a href="{{ route('store.home') }}" class="hover:text-white">Ana sayfa</a> <span class="mx-1">›</span> @if ($category)<a href="{{ route('store.blog') }}" class="hover:text-white">Blog</a> <span class="mx-1">›</span> {{ $category->name }}@else Blog @endif</nav>
            <p class="mt-6 text-xs font-bold tracking-[0.22em] text-puzzle-yellow uppercase">{{ config('store.name') }} Blog</p>
            <h1 class="mt-2 max-w-2xl font-display text-4xl font-extrabold tracking-tight sm:text-5xl">{{ $category?->name ?? 'Her parça bir fikir' }}</h1>
            <p class="mt-3 max-w-xl text-white/75">Okul, ofis ve sanat malzemeleri üzerine öneriler, rehberler ve mağazamızdan haberler.</p>
        </div>
    </section>

    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        @if ($categories->isNotEmpty())
            <nav class="mb-8 flex gap-2 overflow-x-auto pb-1" aria-label="Blog kategorileri">
                <a href="{{ route('store.blog') }}" @class(['shrink-0 rounded-full px-4 py-2 text-sm font-semibold transition', 'bg-navy text-white' => ! $category, 'bg-surface text-ink/75 hover:text-ink' => $category])>Tüm yazılar</a>
                @foreach ($categories as $item)
                    <a href="{{ route('store.blog.category', $item->slug) }}" @class(['shrink-0 rounded-full px-4 py-2 text-sm font-semibold transition', 'bg-navy text-white' => $category?->id === $item->id, 'bg-surface text-ink/75 hover:text-ink' => $category?->id !== $item->id])>{{ $item->name }}</a>
                @endforeach
            </nav>
        @endif

        @if ($posts->isEmpty())
            <div class="rounded-3xl border border-dashed border-line px-6 py-16 text-center">
                <x-icon name="book-open" class="mx-auto size-10 text-puzzle-blue" />
                <p class="mt-3 font-semibold">Henüz yazı yok</p>
                <p class="mt-1 text-sm text-muted">Yakında burada öneriler ve rehberler paylaşacağız.</p>
            </div>
        @else
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($posts as $post)
                    @include('store.front.blog._card', ['post' => $post, 'large' => $loop->first && ! $paged])
                @endforeach
            </div>
            <div class="mt-10">{{ $posts->links() }}</div>
        @endif
    </div>
</x-layouts.store>
