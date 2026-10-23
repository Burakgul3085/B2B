{{-- Blogdan son 3 yazı; yayında yazı yoksa görünmez. --}}
@if ($posts->isNotEmpty())
    <section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8" aria-label="Blogdan son yazılar">
        @include('store.front.home._heading', ['block' => $block, 'eyebrow' => 'Blog', 'color' => 'yellow', 'link' => [route('store.blog'), 'Tüm yazılar']])
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($posts as $post)
                @include('store.front.blog._card', ['post' => $post])
            @endforeach
        </div>
    </section>
@endif
