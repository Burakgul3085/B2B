{{-- Hakkımızda özeti: yayındaki Hakkımızda sayfasının başlığı, sloganı, rakamları ve kartları. --}}
@if ($about)
    <section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8" aria-label="Hakkımızda">
        <div class="relative grid overflow-hidden rounded-3xl bg-navy text-white lg:grid-cols-2">
            <div class="relative p-8 sm:p-12">
                <p class="text-xs font-bold tracking-[0.25em] text-puzzle-sun uppercase">{{ config('store.name') }}</p>
                <h2 class="mt-3 font-display text-3xl leading-tight font-extrabold sm:text-4xl">{{ $about['headline'] }}</h2>
                @if ($about['tagline'])<p class="mt-4 max-w-md text-lg text-white/75">{{ $about['tagline'] }}</p>@endif
                @if ($about['stats'])
                    <dl class="mt-8 grid grid-cols-3 gap-4">
                        @foreach (array_slice($about['stats'], 0, 3) as $stat)
                            <div><dt class="sr-only">{{ $stat['label'] }}</dt><dd class="font-display text-3xl font-extrabold text-puzzle-sun">{{ $stat['value'] }}</dd><p class="text-sm text-white/65">{{ $stat['label'] }}</p></div>
                        @endforeach
                    </dl>
                @endif
                <a href="{{ $about['url'] }}" class="mt-8 inline-flex items-center gap-2 rounded-xl bg-white px-5 py-3 text-sm font-bold text-navy hover:bg-puzzle-sun">Hikâyemizi okuyun <x-icon name="arrow-right" class="size-4" /></a>
            </div>
            @if ($about['features'])
                <ul class="grid gap-3 bg-navy-2 p-8 sm:grid-cols-2 sm:p-12">
                    @foreach (array_slice($about['features'], 0, 4) as $i => $feature)
                        @php $c = \App\Domain\Store\Storefront\Palette::classes(\App\Domain\Store\Storefront\Palette::CYCLE[$i % 4]); @endphp
                        <li class="rounded-2xl bg-white/5 p-5 ring-1 ring-white/10">
                            <span class="flex size-11 items-center justify-center rounded-xl text-white {{ $c['solid'] }}"><x-icon :name="$feature['icon']" class="size-5" /></span>
                            <p class="mt-4 font-display font-bold">{{ $feature['title'] }}</p>
                            @if ($feature['text'])<p class="mt-1 text-sm text-white/65">{{ $feature['text'] }}</p>@endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>
@endif
