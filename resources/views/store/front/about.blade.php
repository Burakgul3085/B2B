@php
    use App\Domain\Store\Content\SitePages;

    $c = $content;
    $cols = [1 => 'lg:grid-cols-1', 2 => 'lg:grid-cols-2', 3 => 'lg:grid-cols-3', 4 => 'lg:grid-cols-4'];
    $image = fn (string $path, ?int $w = null) => route($imageRoute, array_filter(['file' => basename($path), 'w' => $w]));
@endphp
<x-layouts.store :title="$page->seo_title ?: $c['headline']" :description="$page->seo_description ?: ($c['tagline'] ?: $c['story'])"
    :canonical="route('store.about')" :noindex="$adminPreview" :ogImage="$c['cover'] ? $image($c['cover'], 1200) : null">
    @if ($adminPreview)
        <div class="bg-amber-400 px-4 py-2 text-center text-sm font-medium text-slate-900">Önizleme: kaydedilmiş içerik{{ $page->is_published ? '' : ' — sayfa henüz yayında değil' }}.</div>
    @endif

    {{-- Üst bölüm --}}
    <section @class(['relative overflow-hidden', 'bg-surface' => ! $c['cover']])>
        @if ($c['cover'])
            <img src="{{ $image($c['cover'], 2400) }}" alt="" class="absolute inset-0 size-full object-cover">
            <div class="absolute inset-0 bg-slate-950/55"></div>
        @endif
        <div @class(['relative mx-auto max-w-5xl px-4 sm:px-6 lg:px-8', 'py-20 sm:py-28 text-white' => $c['cover'], 'py-12 sm:py-16' => ! $c['cover']])>
            @unless ($c['cover'])@include('store.front._breadcrumbs', ['items' => [['Hakkımızda', null]]])@endunless
            <h1 class="mt-3 text-3xl font-bold tracking-tight sm:text-5xl">{{ $c['headline'] }}</h1>
            @if ($c['tagline'])<p @class(['mt-4 max-w-2xl text-lg', 'text-white/90' => $c['cover'], 'text-muted' => ! $c['cover']])>{{ $c['tagline'] }}</p>@endif
        </div>
    </section>

    <div class="mx-auto max-w-5xl space-y-16 px-4 py-12 sm:px-6 lg:px-8">
        @if ($c['story'])
            <section class="mx-auto max-w-3xl space-y-4 text-[16px] leading-relaxed text-slate-700 [&_a]:font-medium [&_a]:text-primary [&_a]:underline [&_h2]:mt-8 [&_h2]:text-2xl [&_h2]:font-semibold [&_h2]:text-ink [&_h3]:mt-6 [&_h3]:font-semibold [&_h3]:text-ink [&_li]:ml-5 [&_ol]:list-decimal [&_strong]:text-ink [&_ul]:list-disc">
                {{ SitePages::markdown($c['story']) }}
            </section>
        @endif

        @if ($c['stats'])
            <section class="grid grid-cols-2 gap-4 {{ $cols[count($c['stats'])] }}">
                @foreach ($c['stats'] as $stat)
                    <div class="rounded-2xl bg-surface p-6 text-center">
                        <p class="text-3xl font-bold tracking-tight text-primary sm:text-4xl">{{ $stat['value'] }}</p>
                        <p class="mt-1 text-sm text-muted">{{ $stat['label'] }}</p>
                    </div>
                @endforeach
            </section>
        @endif

        @if ($c['features'])
            <section>
                <h2 class="text-center text-2xl font-bold tracking-tight">Neden {{ config('store.name') }}?</h2>
                <div class="mt-8 grid gap-6 sm:grid-cols-2 {{ $cols[count($c['features'])] }}">
                    @foreach ($c['features'] as $feature)
                        <div class="rounded-2xl border border-line p-6">
                            <span class="flex size-11 items-center justify-center rounded-xl bg-primary-soft text-primary"><x-icon :name="$feature['icon']" class="size-5" /></span>
                            <h3 class="mt-4 font-semibold">{{ $feature['title'] }}</h3>
                            @if ($feature['text'])<p class="mt-1.5 text-sm text-muted">{{ $feature['text'] }}</p>@endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($c['gallery'])
            <section class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                @foreach ($c['gallery'] as $path)
                    <img src="{{ $image($path, 900) }}" alt="{{ config('store.name') }}" loading="lazy" class="aspect-[4/3] w-full rounded-2xl object-cover">
                @endforeach
            </section>
        @endif

        @if ($c['show_cta'])
            <section class="rounded-3xl bg-primary px-6 py-10 text-center text-white">
                <h2 class="text-2xl font-bold tracking-tight">Aradığınız kırtasiye ürünleri burada</h2>
                <a href="{{ route('store.products') }}" class="mt-5 inline-flex items-center gap-2 rounded-xl bg-white px-6 py-3 font-semibold text-primary hover:bg-slate-100">Ürünlere göz atın <x-icon name="arrow-right" class="size-4" /></a>
            </section>
        @endif
    </div>
</x-layouts.store>
