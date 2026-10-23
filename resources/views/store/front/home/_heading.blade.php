{{-- Bölüm başlığı: $block (title, subtitle), isteğe bağlı $link [adres, metin], $color (yapboz rengi) --}}
@php $c = \App\Domain\Store\Storefront\Palette::classes($color ?? 'orange'); @endphp
<div class="mb-8 flex flex-wrap items-end justify-between gap-4">
    <div>
        <p class="flex items-center gap-2 text-[11px] font-bold tracking-[0.22em] uppercase {{ $c['text'] }}">
            <span class="inline-flex gap-0.5" aria-hidden="true"><span class="size-2 rounded-[3px] bg-puzzle-orange"></span><span class="size-2 rounded-[3px] bg-puzzle-green"></span><span class="size-2 rounded-[3px] bg-puzzle-blue"></span><span class="size-2 rounded-[3px] bg-puzzle-yellow"></span></span>
            {{ $eyebrow ?? config('store.name') }}
        </p>
        <h2 class="mt-2 font-display text-2xl font-extrabold tracking-tight sm:text-3xl">{{ $block['title'] }}</h2>
        @if ($block['subtitle'])<p class="mt-1.5 text-muted">{{ $block['subtitle'] }}</p>@endif
    </div>
    @isset($link)
        <a href="{{ $link[0] }}" class="inline-flex items-center gap-1.5 rounded-xl border border-line px-4 py-2 text-sm font-semibold transition hover:border-navy-ink/40 hover:bg-surface">{{ $link[1] }} <x-icon name="arrow-right" class="size-4" /></a>
    @endisset
</div>
