@php
    use App\Domain\Store\Content\Blog;

    $url = route('store.blog.post', $post->slug);
    $cover = $post->cover_path ? route('store.media.blog', ['storeBlogPost' => $post->id, 'w' => 1600]).'?v='.$post->updated_at?->timestamp : null;
    $summary = Blog::summary($post, 160);
    $postJson = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'BlogPosting',
        'headline' => $post->title,
        'description' => $post->seo_description ?: $summary,
        'image' => $cover,
        'datePublished' => $post->published_at->toAtomString(),
        'dateModified' => $post->updated_at->toAtomString(),
        'mainEntityOfPage' => $url,
        'author' => ['@type' => 'Organization', 'name' => config('store.name')],
        'publisher' => ['@type' => 'Organization', 'name' => config('store.name')],
    ]);
    $share = 'https://wa.me/?text='.rawurlencode("{$post->title} {$url}");
@endphp
<x-layouts.store :title="$post->seo_title ?: $post->title" :description="$post->seo_description ?: $summary" :canonical="$url" :og-image="$cover" og-type="article" :json-ld="[$postJson]">
    <article>
        <header class="mx-auto max-w-3xl px-4 pt-8 sm:px-6 lg:px-8">
            <nav class="text-sm text-muted" aria-label="Konum">
                <a href="{{ route('store.home') }}" class="hover:text-ink">Ana sayfa</a> <span class="mx-1">›</span>
                <a href="{{ route('store.blog') }}" class="hover:text-ink">Blog</a>
                @if ($post->category)<span class="mx-1">›</span> <a href="{{ route('store.blog.category', $post->category->slug) }}" class="hover:text-ink">{{ $post->category->name }}</a>@endif
            </nav>
            @if ($post->category)<p class="mt-6 text-xs font-bold tracking-[0.16em] text-puzzle-green uppercase">{{ $post->category->name }}</p>@endif
            <h1 class="mt-2 font-display text-3xl leading-tight font-extrabold tracking-tight sm:text-5xl">{{ $post->title }}</h1>
            @if ($post->excerpt)<p class="mt-4 text-lg leading-relaxed text-ink/70">{{ $post->excerpt }}</p>@endif
            <div class="mt-6 flex flex-wrap items-center justify-between gap-3 border-y border-line py-3 text-sm text-muted">
                <span><time datetime="{{ $post->published_at->toDateString() }}">{{ $post->published_at->timezone('Europe/Istanbul')->translatedFormat('j F Y') }}</time> · {{ $post->readingMinutes() }} dk okuma</span>
                <span class="flex items-center gap-3">
                    Paylaş:
                    <a href="{{ $share }}" target="_blank" rel="noopener" class="hover:text-ink" aria-label="WhatsApp'ta paylaş"><x-icon name="message-circle" class="size-4" /></a>
                    <button type="button" x-data="copyCode(@alpine($url))" @click="copy()" class="inline-flex items-center gap-1 hover:text-ink" aria-label="Bağlantıyı kopyala">
                        <x-icon name="link" class="size-4" /> <span x-show="copied" x-cloak class="text-xs text-success">Kopyalandı</span>
                    </button>
                </span>
            </div>
        </header>

        @if ($cover)
            <div class="mx-auto mt-8 max-w-5xl px-4 sm:px-6 lg:px-8">
                <img src="{{ $cover }}" alt="{{ $post->title }}" class="aspect-[16/9] w-full rounded-3xl object-cover" fetchpriority="high">
            </div>
        @endif

        <div class="mx-auto mt-10 max-w-3xl space-y-5 px-4 text-[17px] leading-[1.8] text-slate-700 sm:px-6 lg:px-8
            [&_a]:font-semibold [&_a]:text-navy-ink [&_a]:underline [&_a]:underline-offset-4 [&_blockquote]:border-l-4 [&_blockquote]:border-puzzle-yellow [&_blockquote]:pl-4 [&_blockquote]:italic
            [&_h2]:mt-10 [&_h2]:font-display [&_h2]:text-2xl [&_h2]:font-extrabold [&_h2]:text-ink [&_h3]:mt-8 [&_h3]:text-xl [&_h3]:font-bold [&_h3]:text-ink
            [&_li]:ml-6 [&_li]:pl-1 [&_ol]:list-decimal [&_ol]:space-y-1.5 [&_strong]:text-ink [&_ul]:list-disc [&_ul]:space-y-1.5">
            {{ Blog::html($post->body) }}
        </div>
    </article>

    <div class="mx-auto max-w-7xl px-4 pb-6 sm:px-6 lg:px-8">
        @if ($products->isNotEmpty())
            <section class="mt-16" aria-labelledby="yazidaki-urunler">
                <p class="text-xs font-bold tracking-[0.14em] text-puzzle-orange uppercase">Mağazamızda</p>
                <h2 id="yazidaki-urunler" class="mt-1 font-display text-2xl font-extrabold tracking-tight">Yazıda geçen ürünler</h2>
                <div class="mt-6 grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach ($products as $product)
                        @include('store.front._card', ['product' => $product])
                    @endforeach
                </div>
            </section>
        @endif

        @if ($more->isNotEmpty())
            <section class="mt-16" aria-labelledby="diger-yazilar">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold tracking-[0.14em] text-puzzle-blue uppercase">Blog</p>
                        <h2 id="diger-yazilar" class="mt-1 font-display text-2xl font-extrabold tracking-tight">Diğer yazılar</h2>
                    </div>
                    <a href="{{ route('store.blog') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-navy-ink">Tüm yazılar <x-icon name="arrow-right" class="size-4" /></a>
                </div>
                <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($more as $item)
                        @include('store.front.blog._card', ['post' => $item])
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-layouts.store>
