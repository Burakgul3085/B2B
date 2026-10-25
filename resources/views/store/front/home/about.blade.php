{{--
    Hakkımızda özeti (açık zemin): solda mağaza fotoğrafı (kırpılmadan) ve rozet; sağda başlık, slogan, hikâyeden
    kısa bölüm, rakam kartları ve düğmeler. İçerik yayındaki Hakkımızda sayfasından gelir.
--}}
@if ($about)
    @php
        $tone = fn (int $i) => \App\Domain\Store\Storefront\Palette::classes(\App\Domain\Store\Storefront\Palette::CYCLE[$i % 4]);
        $stats = array_slice($about['stats'], 0, 4);
        $photo = $about['photo'];
        $statCols = [1 => 'grid-cols-1', 2 => 'grid-cols-2', 3 => 'grid-cols-3', 4 => 'grid-cols-2 sm:grid-cols-4'][max(1, count($stats))];
    @endphp
    <section class="relative isolate overflow-hidden bg-linear-to-b from-white via-surface/60 to-surface pt-20 pb-16 sm:pt-24 sm:pb-20" aria-labelledby="ana-hakkimizda">
        <div class="pointer-events-none absolute -top-24 -right-24 -z-10 size-96 rounded-full bg-puzzle-sun/10 blur-3xl" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -bottom-24 -left-24 -z-10 size-96 rounded-full bg-sky-400/10 blur-3xl" aria-hidden="true"></div>
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div @class(['grid items-center gap-14', 'lg:grid-cols-[0.95fr_1.05fr] lg:gap-20' => $photo])>
                @if ($photo)
                    <div class="relative mx-auto w-full max-w-xl pb-8 lg:max-w-none">
                        <div class="pointer-events-none absolute -top-5 -left-5 grid -rotate-12 grid-cols-2 gap-1.5" aria-hidden="true">
                            <span class="size-8 rounded-lg bg-puzzle-orange"></span><span class="size-8 rounded-lg bg-puzzle-green"></span><span class="size-8 rounded-lg bg-puzzle-blue"></span><span class="size-8 rounded-lg bg-puzzle-yellow"></span>
                        </div>
                        <x-store.fit-image :src="$photo['src']" :alt="config('store.name')" :ratio="$photo['ratio']" :frame="$photo['ratio'] ? max(0.7, min(1.6, $photo['ratio'])) : 4 / 3" :maxHeight="34"
                            class="mx-auto rounded-[2rem] shadow-2xl shadow-navy/15 ring-8 ring-white lg:ml-0" />
                        @if ($about['badge_value'])
                            <div class="absolute right-0 bottom-0 rounded-3xl bg-white px-6 py-5 shadow-xl shadow-navy/10 ring-1 ring-line sm:-right-2">
                                <p class="font-display text-4xl leading-none font-extrabold text-puzzle-orange">{{ $about['badge_value'] }}</p>
                                @if ($about['badge_label'])<p class="mt-1.5 max-w-[10rem] text-sm leading-snug font-medium text-muted">{{ $about['badge_label'] }}</p>@endif
                            </div>
                        @endif
                    </div>
                @endif

                <div @class(['mx-auto max-w-3xl text-center' => ! $photo])>
                    <p @class(['flex items-center gap-2 text-xs font-bold tracking-[0.25em] text-puzzle-orange uppercase', 'justify-center' => ! $photo])>
                        <span class="inline-flex gap-0.5" aria-hidden="true"><span class="size-2 rounded-[3px] bg-puzzle-orange"></span><span class="size-2 rounded-[3px] bg-puzzle-green"></span><span class="size-2 rounded-[3px] bg-puzzle-blue"></span><span class="size-2 rounded-[3px] bg-puzzle-yellow"></span></span>
                        Hakkımızda
                    </p>
                    <h2 id="ana-hakkimizda" class="mt-4 font-display text-3xl leading-[1.1] font-extrabold tracking-tight text-ink sm:text-5xl">{{ $about['headline'] }}</h2>
                    @if ($about['tagline'])<p class="mt-5 text-lg leading-relaxed text-slate-700 sm:text-xl">{{ $about['tagline'] }}</p>@endif
                    @if ($about['excerpt'])<p class="mt-4 leading-relaxed text-muted">{{ $about['excerpt'] }}</p>@endif

                    @if ($stats)
                        <dl class="mt-10 grid gap-3 {{ $statCols }}">
                            @foreach ($stats as $i => $stat)
                                <div class="relative flex flex-col overflow-hidden rounded-2xl bg-white px-5 pt-6 pb-5 text-left shadow-sm ring-1 ring-line">
                                    <span class="absolute inset-x-0 top-0 h-1 {{ $tone($i)['solid'] }}"></span>
                                    <dt class="order-2 mt-1 text-sm text-muted">{{ $stat['label'] }}</dt>
                                    <dd class="order-1 font-display text-3xl font-extrabold tracking-tight text-navy-ink tabular-nums" x-data="countUp(@alpine($stat['value']))" x-text="shown">{{ $stat['value'] }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif

                    <div @class(['mt-10 flex flex-wrap gap-3', 'justify-center' => ! $photo])>
                        <a href="{{ $about['url'] }}" class="inline-flex h-12 items-center gap-2 rounded-xl bg-navy px-6 font-semibold text-white shadow-md shadow-navy/20 transition hover:bg-puzzle-orange">Hikâyemizi okuyun <x-icon name="arrow-right" class="size-4" /></a>
                        <a href="{{ route('store.products') }}" class="inline-flex h-12 items-center gap-2 rounded-xl border border-line bg-white px-6 font-semibold text-ink transition hover:border-navy hover:text-navy-ink">Ürünleri keşfet</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endif
