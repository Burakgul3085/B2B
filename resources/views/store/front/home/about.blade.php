{{--
    Hakkımızda özeti (tam genişlik lacivert bant): başlık, slogan, hikâyeden kısa bölüm, rakamlar ve mağaza fotoğrafı
    (kırpılmadan); altında "neden biz" kartları. İçerik yayındaki Hakkımızda sayfasından gelir.
--}}
@if ($about)
    @php
        $tone = fn (int $i) => \App\Domain\Store\Storefront\Palette::classes(\App\Domain\Store\Storefront\Palette::CYCLE[$i % 4]);
        $stats = array_slice($about['stats'], 0, 4);
        $features = array_slice($about['features'], 0, 4);
        $photo = $about['photo'];
        $statCols = [1 => 'grid-cols-1', 2 => 'grid-cols-2', 3 => 'grid-cols-3', 4 => 'grid-cols-2 sm:grid-cols-4'][max(1, count($stats))];
        $featureCols = [1 => 'lg:grid-cols-1', 2 => 'lg:grid-cols-2', 3 => 'lg:grid-cols-3', 4 => 'lg:grid-cols-4'][max(1, count($features))];
    @endphp
    <section class="relative isolate overflow-hidden bg-navy py-20 text-white sm:py-24" aria-labelledby="ana-hakkimizda">
        <div class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(ellipse_at_top_left,rgba(250,204,21,0.10),transparent_50%),radial-gradient(ellipse_at_bottom_right,rgba(14,165,233,0.12),transparent_55%)]" aria-hidden="true"></div>
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div @class(['grid items-center gap-14', 'lg:grid-cols-2 lg:gap-20' => $photo])>
                <div @class(['max-w-3xl' => ! $photo])>
                    <p class="flex items-center gap-2 text-xs font-bold tracking-[0.25em] text-puzzle-sun uppercase">
                        <span class="inline-flex gap-0.5" aria-hidden="true"><span class="size-2 rounded-[3px] bg-puzzle-orange"></span><span class="size-2 rounded-[3px] bg-puzzle-green"></span><span class="size-2 rounded-[3px] bg-puzzle-blue"></span><span class="size-2 rounded-[3px] bg-puzzle-yellow"></span></span>
                        Hakkımızda
                    </p>
                    <h2 id="ana-hakkimizda" class="mt-4 font-display text-3xl leading-[1.1] font-extrabold tracking-tight sm:text-5xl">{{ $about['headline'] }}</h2>
                    @if ($about['tagline'])<p class="mt-5 text-lg leading-relaxed text-white/80 sm:text-xl">{{ $about['tagline'] }}</p>@endif
                    @if ($about['excerpt'])<p class="mt-4 leading-relaxed text-white/60">{{ $about['excerpt'] }}</p>@endif

                    @if ($stats)
                        <dl class="mt-10 grid gap-px overflow-hidden rounded-2xl bg-white/10 ring-1 ring-white/10 {{ $statCols }}">
                            @foreach ($stats as $i => $stat)
                                <div class="flex flex-col bg-navy px-5 py-5">
                                    <dt class="order-2 mt-1 text-sm text-white/60">{{ $stat['label'] }}</dt>
                                    <dd class="order-1 font-display text-3xl font-extrabold tracking-tight text-puzzle-sun tabular-nums sm:text-4xl" x-data="countUp(@alpine($stat['value']))" x-text="shown">{{ $stat['value'] }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif

                    <div class="mt-10 flex flex-wrap gap-3">
                        <a href="{{ $about['url'] }}" class="inline-flex h-12 items-center gap-2 rounded-xl bg-white px-6 font-semibold text-navy transition hover:bg-puzzle-sun">Hikâyemizi okuyun <x-icon name="arrow-right" class="size-4" /></a>
                        <a href="{{ route('store.products') }}" class="inline-flex h-12 items-center gap-2 rounded-xl px-6 font-semibold text-white ring-1 ring-white/30 transition hover:bg-white/10">Ürünleri keşfet</a>
                    </div>
                </div>

                @if ($photo)
                    <div class="relative mx-auto w-full max-w-xl pb-6 lg:max-w-none">
                        <div class="pointer-events-none absolute -top-6 -right-4 grid rotate-12 grid-cols-2 gap-1.5" aria-hidden="true">
                            <span class="size-8 rounded-lg bg-puzzle-orange"></span><span class="size-8 rounded-lg bg-puzzle-green"></span><span class="size-8 rounded-lg bg-puzzle-blue"></span><span class="size-8 rounded-lg bg-puzzle-yellow"></span>
                        </div>
                        <x-store.fit-image :src="$photo['src']" :alt="config('store.name')" :ratio="$photo['ratio']" :frame="$photo['ratio'] ? max(0.7, min(1.6, $photo['ratio'])) : 4 / 3" :maxHeight="34"
                            class="mx-auto rounded-[2rem] shadow-2xl shadow-black/40 ring-1 ring-white/10 lg:mr-0" />
                        @if ($about['badge_value'])
                            <div class="absolute bottom-0 left-0 rounded-3xl bg-white px-6 py-5 text-navy shadow-2xl shadow-black/30 sm:-left-4">
                                <p class="font-display text-4xl leading-none font-extrabold text-puzzle-orange">{{ $about['badge_value'] }}</p>
                                @if ($about['badge_label'])<p class="mt-1.5 max-w-[10rem] text-sm leading-snug font-medium text-slate-600">{{ $about['badge_label'] }}</p>@endif
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            @if ($features)
                <ul class="mt-16 grid gap-4 border-t border-white/10 pt-12 sm:grid-cols-2 {{ $featureCols }}">
                    @foreach ($features as $i => $feature)
                        <li class="rounded-2xl bg-white/[0.04] p-6 ring-1 ring-white/10 transition hover:bg-white/[0.07]">
                            <span class="flex size-12 items-center justify-center rounded-2xl text-white shadow-lg {{ $tone($i)['solid'] }}"><x-icon :name="$feature['icon']" class="size-6" /></span>
                            <p class="mt-5 font-display text-lg font-bold">{{ $feature['title'] }}</p>
                            @if ($feature['text'])<p class="mt-1.5 text-sm leading-relaxed text-white/60">{{ $feature['text'] }}</p>@endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>
@endif
