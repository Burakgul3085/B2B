{{-- Yardım sayfalarının ortak çerçevesi: lacivert başlık alanı, solda yardım menüsü, sağda içerik ($slot). --}}
@props(['key', 'c', 'icon' => 'message-circle', 'jsonLd' => []])
@php
    use App\Domain\Store\Content\HelpPages;
    use App\Domain\Store\Content\SitePages;

    $nav = collect(HelpPages::PAGES)->filter(fn ($p, $k) => HelpPages::isPublished($k))
        ->map(fn ($p, $k) => ['key' => $k, 'title' => HelpPages::get($k)['content']['title'], 'url' => route('store.help.'.$k)]);
    $settings = \App\Models\Store\StoreSetting::values();
    $whatsapp = \App\Domain\Store\Storefront\ProductPage::whatsapp($settings['whatsapp']);
    $crumbs = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Ana sayfa', 'item' => route('store.home')],
        ['@type' => 'ListItem', 'position' => 2, 'name' => $c['title'], 'item' => route('store.help.'.$key)],
    ]];
@endphp
<x-layouts.store :title="$c['title']" :description="$c['intro']" :canonical="route('store.help.'.$key)" :json-ld="array_merge([$crumbs], $jsonLd)">
    <section class="relative overflow-hidden bg-navy text-white">
        <div class="pointer-events-none absolute -right-8 -bottom-10 hidden rotate-12 grid-cols-2 gap-3 opacity-90 md:grid" aria-hidden="true">
            <span class="size-20 rounded-3xl bg-puzzle-orange"></span><span class="size-20 rounded-3xl bg-puzzle-green"></span>
            <span class="size-20 rounded-3xl bg-puzzle-blue"></span><span class="size-20 rounded-3xl bg-puzzle-yellow"></span>
        </div>
        <div class="relative mx-auto max-w-7xl px-4 py-10 sm:px-6 sm:py-14 lg:px-8">
            <nav class="text-sm text-white/60" aria-label="Konum">
                <a href="{{ route('store.home') }}" class="hover:text-white">Ana sayfa</a> <span class="mx-1">›</span>
                @if ($key !== 'help' && HelpPages::isPublished('help'))<a href="{{ route('store.help.help') }}" class="hover:text-white">{{ HelpPages::get('help')['content']['title'] }}</a> <span class="mx-1">›</span>@endif
                {{ $c['title'] }}
            </nav>
            <div class="mt-6 flex items-center gap-4">
                <span class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-white/10 text-puzzle-yellow ring-1 ring-white/15"><x-icon :name="$icon" class="size-7" /></span>
                <h1 class="font-display text-3xl font-extrabold tracking-tight sm:text-4xl">{{ $c['title'] }}</h1>
            </div>
            @if ($c['intro'])<p class="mt-4 max-w-2xl text-white/75 sm:text-lg">{{ $c['intro'] }}</p>@endif
        </div>
    </section>

    <div class="mx-auto grid max-w-7xl gap-10 px-4 py-10 sm:px-6 lg:grid-cols-[16rem_1fr] lg:px-8">
        <aside class="order-last space-y-6 lg:order-first">
            @if ($nav->count() > 1)
                <nav class="rounded-2xl border border-line p-2" aria-label="Yardım konuları">
                    @foreach ($nav as $item)
                        <a href="{{ $item['url'] }}" @class(['flex items-center justify-between rounded-xl px-3 py-2.5 text-sm font-semibold transition',
                            'bg-navy text-white' => $item['key'] === $key, 'text-ink/75 hover:bg-surface hover:text-ink' => $item['key'] !== $key])>
                            {{ $item['title'] }} <x-icon name="arrow-right" class="size-4 opacity-60" />
                        </a>
                    @endforeach
                </nav>
            @endif
            <div class="rounded-2xl bg-surface p-5">
                <p class="font-display font-extrabold">Yardıma mı ihtiyacınız var?</p>
                <p class="mt-1 text-sm text-muted">Bize ulaşın, hemen yardımcı olalım.</p>
                <ul class="mt-4 space-y-2.5 text-sm font-semibold">
                    @php $phones = \App\Domain\Store\Storefront\StorePhones::list($settings); @endphp
                    @foreach ($phones as $phone)<li><a href="{{ $phone['tel'] }}" class="flex items-center gap-2 hover:text-navy-ink"><x-icon name="phone" class="size-4 text-puzzle-orange" /> {{ $phone['number'] }}@if (count($phones) > 1) <span class="font-normal text-muted">· {{ $phone['label'] }}</span>@endif</a></li>@endforeach
                    @if ($whatsapp)<li><a href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener" class="flex items-center gap-2 hover:text-navy-ink"><x-icon name="message-circle" class="size-4 text-puzzle-green" /> WhatsApp</a></li>@endif
                    @if ($settings['email'])<li><a href="mailto:{{ $settings['email'] }}" class="flex items-center gap-2 break-all hover:text-navy-ink"><x-icon name="mail" class="size-4 text-puzzle-blue" /> {{ $settings['email'] }}</a></li>@endif
                </ul>
            </div>
        </aside>

        <div class="min-w-0">
            {{ $slot }}
            @if ($c['body'])
                <div class="mt-8 max-w-3xl space-y-4 text-[16px] leading-relaxed text-slate-700 [&_a]:font-semibold [&_a]:text-navy-ink [&_a]:underline [&_h2]:mt-8 [&_h2]:font-display [&_h2]:text-2xl [&_h2]:font-extrabold [&_h2]:text-ink [&_h3]:mt-6 [&_h3]:font-bold [&_h3]:text-ink [&_li]:ml-5 [&_ol]:list-decimal [&_strong]:text-ink [&_ul]:list-disc">
                    {{ SitePages::markdown($c['body']) }}
                </div>
            @endif
        </div>
    </div>
</x-layouts.store>
