{{-- Yardım Merkezi: yönetimden düzenlenen kutular --}}
@php
    use App\Domain\Store\Content\FooterLinks;
    use App\Domain\Store\Storefront\Palette;

    $tiles = collect($c['tiles'])->map(fn ($t) => $t + ['url' => $t['link'] ? (FooterLinks::isUrl($t['link']) ? $t['link'] : FooterLinks::resolve($t['link'])) : null])
        ->filter(fn ($t) => ! $t['link'] || $t['url'])->values();
@endphp
<x-store.help-layout :key="$key" :c="$c" icon="book-open">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($tiles as $tile)
            @php $tone = Palette::classes(Palette::CYCLE[$loop->index % 4]); @endphp
            <a @if ($tile['url']) href="{{ $tile['url'] }}" @endif class="group flex flex-col rounded-3xl border border-line bg-white p-6 transition hover:-translate-y-0.5 hover:border-transparent hover:shadow-xl hover:shadow-navy/10">
                <span class="flex size-12 items-center justify-center rounded-2xl {{ $tone['soft'] }} {{ $tone['text'] }}"><x-icon :name="$tile['icon']" class="size-6" /></span>
                <span class="mt-4 font-display text-lg font-extrabold">{{ $tile['title'] }}</span>
                @if ($tile['text'])<span class="mt-1 text-sm leading-relaxed text-muted">{{ $tile['text'] }}</span>@endif
                @if ($tile['url'])<span class="mt-auto inline-flex items-center gap-1.5 pt-4 text-sm font-semibold {{ $tone['text'] }}">Göz at <x-icon name="arrow-right" class="size-4 transition group-hover:translate-x-1" /></span>@endif
            </a>
        @endforeach
    </div>
</x-store.help-layout>
