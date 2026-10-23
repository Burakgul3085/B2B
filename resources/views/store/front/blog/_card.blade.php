{{-- Blog yazı kartı: $post (category yüklü), isteğe bağlı $large (ilk yazı, geniş) --}}
@php
    $url = route('store.blog.post', $post->slug);
    $large ??= false;
@endphp
<article @class(['group flex flex-col overflow-hidden rounded-3xl border border-line bg-white transition hover:-translate-y-0.5 hover:shadow-xl hover:shadow-navy/10', 'lg:col-span-2 lg:flex-row' => $large])>
    <a href="{{ $url }}" @class(['relative block shrink-0 overflow-hidden bg-navy', 'aspect-[16/10]', 'lg:aspect-auto lg:w-1/2' => $large]) tabindex="-1" aria-hidden="true">
        @if ($post->cover_path)
            <img src="{{ route('store.media.blog', ['storeBlogPost' => $post->id, 'w' => $large ? 1200 : 720]) }}?v={{ $post->updated_at?->timestamp }}" alt="" loading="lazy"
                class="size-full object-cover transition duration-500 group-hover:scale-105">
        @else
            <span class="absolute inset-0 flex items-center justify-center" aria-hidden="true">
                <span class="grid grid-cols-2 gap-1.5"><span class="size-8 rounded-lg bg-puzzle-orange"></span><span class="size-8 rounded-lg bg-puzzle-green"></span><span class="size-8 rounded-lg bg-puzzle-blue"></span><span class="size-8 rounded-lg bg-puzzle-yellow"></span></span>
            </span>
        @endif
    </a>
    <div @class(['flex flex-1 flex-col p-5 sm:p-6', 'lg:justify-center lg:p-10' => $large])>
        <p class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted">
            @if ($post->category)<a href="{{ route('store.blog.category', $post->category->slug) }}" class="font-bold tracking-[0.12em] text-puzzle-green uppercase hover:underline">{{ $post->category->name }}</a>@endif
            <time datetime="{{ $post->published_at->toDateString() }}">{{ $post->published_at->timezone('Europe/Istanbul')->translatedFormat('j F Y') }}</time>
            <span>· {{ $post->readingMinutes() }} dk okuma</span>
        </p>
        <h3 @class(['mt-2 font-display font-extrabold tracking-tight', 'text-lg leading-snug' => ! $large, 'text-2xl leading-tight sm:text-3xl' => $large])>
            <a href="{{ $url }}" class="hover:text-navy-ink">{{ $post->title }}</a>
        </h3>
        <p @class(['mt-2 text-sm leading-relaxed text-muted', 'line-clamp-3' => ! $large, 'sm:text-base' => $large])>{{ \App\Domain\Store\Content\Blog::summary($post) }}</p>
        <a href="{{ $url }}" class="mt-auto inline-flex items-center gap-1.5 pt-4 text-sm font-semibold text-navy-ink">Devamını oku <x-icon name="arrow-right" class="size-4 transition group-hover:translate-x-1" /></a>
    </div>
</article>
