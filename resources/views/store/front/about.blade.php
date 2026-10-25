{{--
    Hakkımızda: tüm içerik Vitrin → Hakkımızda'dan. Boş veya kapatılmış bölüm görünmez.
    Bölümler: kapak · rakamlar · hikâye · misyon/vizyon/değerler · neden biz · yolculuğumuz · galeri · ekip · kapanış bandı.
--}}
@php
    use App\Domain\Store\Content\SitePages;
    use App\Domain\Store\Storefront\Palette;

    $c = $content;
    $name = config('store.name');
    $on = fn (string $section) => SitePages::sectionOn($c, $section);
    $image = fn (string $path, ?int $w = null) => route($imageRoute, array_filter(['file' => basename($path), 'w' => $w]));
    $tone = fn (int $i) => Palette::classes(Palette::CYCLE[$i % 4]);
    // Sıra koddaki tanımdan (veritabanı JSON anahtar sırasını korumaz)
    $pillars = collect(SitePages::PILLARS)->map(fn ($def, $key) => $c['pillars'][$key] ?? ['title' => $def[0], 'text' => null])->filter(fn ($p) => filled($p['text'] ?? null));
    $contactLive = \App\Models\Store\StorePage::where('key', 'contact')->where('is_published', true)->exists();
    $prose = 'space-y-4 text-[16px] leading-relaxed text-slate-700 [&_a]:font-semibold [&_a]:text-navy-ink [&_a]:underline [&_h2]:mt-8 [&_h2]:font-display [&_h2]:text-2xl [&_h2]:font-extrabold [&_h2]:text-ink [&_h3]:mt-6 [&_h3]:font-bold [&_h3]:text-ink [&_li]:ml-5 [&_ol]:list-decimal [&_strong]:text-ink [&_ul]:list-disc';
    $eyebrow = 'text-xs font-bold tracking-[0.2em] uppercase';
    $statCols = [1 => 'sm:grid-cols-1', 2 => 'sm:grid-cols-2', 3 => 'sm:grid-cols-3', 4 => 'sm:grid-cols-2 lg:grid-cols-4', 5 => 'sm:grid-cols-3 lg:grid-cols-5', 6 => 'sm:grid-cols-3 lg:grid-cols-6'];
    // Fotoğraflar kırpılmaz: çerçeve, fotoğrafın kendi en/boy oranından seçilir (aşırı uçlar sınırlanır, taşan
    // kısım bulanık zeminle tamamlanır — x-store.fit-image).
    $ratio = fn (?string $path) => \App\Domain\Store\Storefront\ImageVariants::ratio($path);
    $clamp = fn (?float $r, float $min, float $max) => $r ? max($min, min($max, $r)) : null;
    $gallery = collect($c['gallery'])->map(fn ($path) => ['src' => $image($path, 1800), 'thumb' => $image($path, 900), 'ratio' => $ratio($path), 'caption' => $c['gallery_captions'][basename($path)] ?? null])->values();
    $galleryCols = match (true) { $gallery->count() === 1 => 'mx-auto max-w-2xl columns-1', $gallery->count() === 2 => 'columns-2', default => 'columns-2 md:columns-3' };
    $coverRatio = $c['cover'] ? ($ratio($c['cover']) ?? 1.5) : null;
    $storyRatios = [$ratio($c['story_images'][0] ?? null), $ratio($c['story_images'][1] ?? null)];
    // Ekip kartları aynı çerçeveyi paylaşır: fotoğrafların ortanca oranı (yatay fotoğraflarda kart genişler).
    $teamRatios = collect($c['team'])->map(fn ($m) => $ratio($m['photo'] ?? null))->filter()->sort()->values();
    $teamFrame = $clamp($teamRatios->isNotEmpty() ? $teamRatios[intdiv($teamRatios->count(), 2)] : null, 0.75, 1.34) ?? 1.0;
@endphp
<x-layouts.store :title="$page->seo_title ?: $c['headline']" :description="$page->seo_description ?: ($c['tagline'] ?: $c['story'])"
    :canonical="route('store.about')" :noindex="$adminPreview" :ogImage="$c['cover'] ? $image($c['cover'], 1200) : null">
    @if ($adminPreview)
        <div class="bg-amber-400 px-4 py-2 text-center text-sm font-medium text-slate-900">Önizleme: kaydedilmiş içerik{{ $page->is_published ? '' : ' — sayfa henüz yayında değil' }}.</div>
    @endif

    {{-- 1. Kapak --}}
    <section class="relative isolate overflow-hidden bg-[#0b1631] text-white">
        @if ($c['cover'])
            {{-- Kapak fotoğrafı kırpılmadan sağda bütün olarak durur; aynı fotoğrafın bulanık hâli zemine renk verir. --}}
            <img src="{{ $image($c['cover'], 1200) }}" alt="" aria-hidden="true" class="absolute inset-0 -z-20 size-full scale-110 object-cover opacity-30 blur-3xl">
            <div class="absolute inset-0 -z-10 bg-gradient-to-r from-[#0b1631] via-[#0b1631]/90 to-[#0b1631]/70"></div>
        @else
            <div class="pointer-events-none absolute -right-12 -bottom-16 -z-10 hidden rotate-12 grid-cols-2 gap-4 md:grid" aria-hidden="true">
                <span class="size-32 rounded-[2rem] bg-puzzle-orange"></span><span class="size-32 rounded-[2rem] bg-puzzle-green"></span>
                <span class="size-32 rounded-[2rem] bg-puzzle-blue"></span><span class="size-32 rounded-[2rem] bg-puzzle-yellow"></span>
            </div>
        @endif
        <div @class(['mx-auto max-w-7xl px-4 sm:px-6 lg:px-8', 'grid items-center gap-12 pt-12 pb-28 lg:grid-cols-2 lg:gap-16 lg:pt-16' => $c['cover'], 'pt-14 pb-24 sm:pt-20' => ! $c['cover']])>
            <div>
            <nav class="text-sm text-white/60" aria-label="Konum"><a href="{{ route('store.home') }}" class="hover:text-white">Ana sayfa</a> <span class="mx-1">›</span> Hakkımızda</nav>
            <p class="mt-8 flex items-center gap-2 {{ $eyebrow }} text-amber-400">
                <span class="inline-flex gap-0.5" aria-hidden="true"><span class="size-2 rounded-[3px] bg-puzzle-orange"></span><span class="size-2 rounded-[3px] bg-puzzle-green"></span><span class="size-2 rounded-[3px] bg-puzzle-blue"></span><span class="size-2 rounded-[3px] bg-puzzle-yellow"></span></span>
                Hakkımızda
            </p>
            <h1 class="mt-3 max-w-3xl font-display text-4xl leading-[1.08] font-extrabold tracking-tight sm:text-6xl">{{ $c['headline'] }}</h1>
            @if ($c['tagline'])<p class="mt-5 max-w-2xl text-lg leading-relaxed text-white/80 sm:text-xl">{{ $c['tagline'] }}</p>@endif
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('store.products') }}" class="inline-flex h-12 items-center gap-2 rounded-xl bg-puzzle-orange px-6 font-semibold text-white shadow-lg shadow-black/25 transition hover:brightness-110">Ürünleri keşfet <x-icon name="arrow-right" class="size-4" /></a>
                @if ($contactLive)<a href="{{ route('store.contact') }}" class="inline-flex h-12 items-center gap-2 rounded-xl px-6 font-semibold text-white ring-1 ring-white/40 transition hover:bg-white/10">Bize ulaşın</a>@endif
            </div>
            </div>
            @if ($c['cover'])
                <div class="relative mx-auto w-full max-w-xl lg:max-w-none">
                    <div class="pointer-events-none absolute -top-5 -right-3 grid rotate-12 grid-cols-2 gap-1.5" aria-hidden="true">
                        <span class="size-7 rounded-lg bg-puzzle-orange"></span><span class="size-7 rounded-lg bg-puzzle-green"></span><span class="size-7 rounded-lg bg-puzzle-blue"></span><span class="size-7 rounded-lg bg-puzzle-yellow"></span>
                    </div>
                    <x-store.fit-image :src="$image($c['cover'], 1600)" :alt="$name" :ratio="$coverRatio" :frame="$clamp($coverRatio, 0.66, 1.9)" loading="eager" :maxHeight="34"
                        class="mx-auto rounded-[2rem] shadow-2xl ring-1 ring-white/15" />
                </div>
            @endif
        </div>
    </section>

    {{-- 2. Rakamlar (kapağın üzerine biner) --}}
    @if ($c['stats'] && $on('stats'))
        <div class="relative z-10 mx-auto -mt-16 max-w-6xl px-4 sm:px-6 lg:px-8">
            <dl class="grid grid-cols-2 overflow-hidden rounded-3xl bg-white shadow-2xl shadow-navy/15 ring-1 ring-line {{ $statCols[count($c['stats'])] }}">
                @foreach ($c['stats'] as $i => $stat)
                    <div class="flex flex-col items-center px-4 py-7 text-center sm:py-8 [&:not(:first-child)]:border-l [&:not(:first-child)]:border-line">
                        <span class="flex size-11 items-center justify-center rounded-2xl {{ $tone($i)['soft'] }} {{ $tone($i)['text'] }}"><x-icon :name="$stat['icon'] ?? 'star'" class="size-5" /></span>
                        <dd class="mt-3 font-display text-3xl font-extrabold tracking-tight text-navy-ink tabular-nums sm:text-4xl" x-data="countUp(@alpine($stat['value']))" x-text="shown">{{ $stat['value'] }}</dd>
                        <dt class="mt-1 text-sm text-muted">{{ $stat['label'] }}</dt>
                    </div>
                @endforeach
            </dl>
        </div>
    @endif

    <div class="space-y-24 py-20 sm:space-y-28">
        {{-- 3. Hikâye --}}
        @if ($c['story'] && $on('story'))
            <section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div @class(['grid items-center gap-12', 'lg:grid-cols-2 lg:gap-16' => $c['story_images'] || $c['badge_value']])>
                    <div @class(['max-w-3xl', 'mx-auto' => ! $c['story_images'] && ! $c['badge_value']])>
                        <p class="{{ $eyebrow }} text-puzzle-orange">Biz kimiz</p>
                        <h2 class="mt-2 font-display text-3xl font-extrabold tracking-tight sm:text-4xl">{{ $c['story_title'] }}</h2>
                        <div class="mt-6 {{ $prose }}">{{ SitePages::markdown($c['story']) }}</div>
                    </div>
                    @if ($c['story_images'] || $c['badge_value'])
                        <div class="relative mx-auto w-full max-w-lg pb-10 lg:max-w-none">
                            @if ($c['story_images'][0] ?? null)
                                <x-store.fit-image :src="$image($c['story_images'][0], 1200)" :alt="$name" :ratio="$storyRatios[0]" :frame="$clamp($storyRatios[0], 0.7, 1.5)"
                                    class="w-full rounded-[2rem] shadow-2xl shadow-navy/20 sm:w-4/5 lg:ml-auto" />
                            @else
                                <div class="grid aspect-[4/5] w-full grid-cols-2 gap-4 rounded-[2rem] bg-surface p-10 sm:w-4/5 lg:ml-auto" aria-hidden="true">
                                    <span class="rounded-3xl bg-puzzle-orange"></span><span class="rounded-3xl bg-puzzle-green"></span><span class="rounded-3xl bg-puzzle-blue"></span><span class="rounded-3xl bg-puzzle-yellow"></span>
                                </div>
                            @endif
                            @if ($c['story_images'][1] ?? null)
                                <x-store.fit-image :src="$image($c['story_images'][1], 800)" :ratio="$storyRatios[1]" :frame="$clamp($storyRatios[1], 0.75, 1.34)"
                                    class="!absolute bottom-0 left-0 hidden w-2/5 rounded-3xl shadow-xl ring-8 ring-white sm:block" />
                            @endif
                            @if ($c['badge_value'])
                                <div class="absolute top-8 -left-2 rounded-3xl bg-[#0b1631] px-6 py-5 text-white shadow-2xl shadow-navy/30 sm:left-6">
                                    <p class="font-display text-4xl leading-none font-extrabold text-amber-400">{{ $c['badge_value'] }}</p>
                                    @if ($c['badge_label'])<p class="mt-1.5 max-w-[9rem] text-sm leading-snug text-white/80">{{ $c['badge_label'] }}</p>@endif
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </section>
        @endif

        {{-- 4. Misyon, vizyon, değerler --}}
        @if ($pillars->isNotEmpty() && $on('pillars'))
            <section class="bg-surface py-20">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="mx-auto max-w-2xl text-center">
                        <p class="{{ $eyebrow }} text-puzzle-blue">Yol haritamız</p>
                        <h2 class="mt-2 font-display text-3xl font-extrabold tracking-tight sm:text-4xl">Neye inanıyoruz?</h2>
                    </div>
                    <div @class(['mt-12 grid gap-6', 'md:grid-cols-2' => $pillars->count() === 2, 'md:grid-cols-3' => $pillars->count() === 3])>
                        @foreach ($pillars as $key => $pillar)
                            @php $t = $tone($loop->index); @endphp
                            <article class="relative overflow-hidden rounded-3xl bg-white p-8 shadow-sm ring-1 ring-line">
                                <span class="absolute inset-x-0 top-0 h-1.5 {{ $t['solid'] }}"></span>
                                <span class="flex size-12 items-center justify-center rounded-2xl {{ $t['soft'] }} {{ $t['text'] }}"><x-icon :name="SitePages::PILLARS[$key][1]" class="size-6" /></span>
                                <h3 class="mt-5 font-display text-xl font-extrabold">{{ $pillar['title'] }}</h3>
                                <p class="mt-3 leading-relaxed whitespace-pre-line text-slate-700">{{ $pillar['text'] }}</p>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        {{-- 5. Neden biz --}}
        @if ($c['features'] && $on('features'))
            <section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <p class="{{ $eyebrow }} text-puzzle-green">Farkımız</p>
                    <h2 class="mt-2 font-display text-3xl font-extrabold tracking-tight sm:text-4xl">{{ $c['features_title'] ?: 'Neden '.$name.'?' }}</h2>
                </div>
                <div @class(['mt-12 grid gap-5 sm:grid-cols-2', 'lg:grid-cols-3' => count($c['features']) !== 4, 'lg:grid-cols-4' => count($c['features']) === 4])>
                    @foreach ($c['features'] as $i => $feature)
                        <div class="group rounded-3xl border border-line bg-white p-7 transition hover:-translate-y-1 hover:border-transparent hover:shadow-xl hover:shadow-navy/10">
                            <span class="flex size-12 items-center justify-center rounded-2xl text-white shadow-lg transition group-hover:scale-110 {{ $tone($i)['solid'] }}"><x-icon :name="$feature['icon']" class="size-6" /></span>
                            <h3 class="mt-5 font-display text-lg font-extrabold">{{ $feature['title'] }}</h3>
                            @if ($feature['text'])<p class="mt-2 text-sm leading-relaxed text-muted">{{ $feature['text'] }}</p>@endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- 6. Yolculuğumuz --}}
        @if ($c['timeline'] && $on('timeline'))
            <section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <p class="{{ $eyebrow }} text-puzzle-yellow">Kilometre taşları</p>
                    <h2 class="mt-2 font-display text-3xl font-extrabold tracking-tight sm:text-4xl">{{ $c['timeline_title'] }}</h2>
                </div>
                <ol class="relative mt-14 space-y-8 border-l-2 border-line pl-8 lg:flex lg:space-y-0 lg:border-t-2 lg:border-l-0 lg:pt-10 lg:pl-0">
                    @foreach ($c['timeline'] as $i => $step)
                        <li class="relative lg:flex-1 lg:px-4">
                            <span class="absolute top-1 -left-[41px] flex size-5 items-center justify-center rounded-full ring-4 ring-white lg:-top-[51px] lg:left-4 {{ $tone($i)['solid'] }}"></span>
                            <p class="inline-flex rounded-lg px-2.5 py-1 font-display text-sm font-extrabold {{ $tone($i)['soft'] }} {{ $tone($i)['text'] }}">{{ $step['year'] }}</p>
                            <h3 class="mt-3 font-display text-lg leading-snug font-extrabold">{{ $step['title'] }}</h3>
                            @if ($step['text'])<p class="mt-1.5 text-sm leading-relaxed text-muted">{{ $step['text'] }}</p>@endif
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif

        {{-- 7. Galeri --}}
        @if ($gallery->isNotEmpty() && $on('gallery'))
            <section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8" x-data="photoGallery(@alpine($gallery->all()))" @keydown.window.escape="close()" @keydown.window.arrow-right="next()" @keydown.window.arrow-left="prev()">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p class="{{ $eyebrow }} text-puzzle-orange">Galeri</p>
                        <h2 class="mt-2 font-display text-3xl font-extrabold tracking-tight sm:text-4xl">{{ $c['gallery_title'] }}</h2>
                    </div>
                    <p class="text-sm text-muted">{{ $gallery->count() }} fotoğraf</p>
                </div>
                {{-- Taş duvar (masonry) dizilim: her fotoğraf kendi oranında, boşluksuz ve kırpılmadan --}}
                <div class="mt-10 gap-3 md:gap-4 {{ $galleryCols }}">
                    @foreach ($gallery as $i => $photo)
                        <button type="button" @click="open({{ $i }})" class="group relative mb-3 block w-full break-inside-avoid overflow-hidden rounded-2xl bg-surface focus:ring-4 focus:ring-amber-400/50 focus:outline-none md:mb-4"
                            aria-label="Fotoğrafı büyüt{{ $photo['caption'] ? ': '.$photo['caption'] : '' }}">
                            <x-store.fit-image :src="$photo['thumb']" :alt="$photo['caption'] ?? $name" :ratio="$photo['ratio']" :frame="$clamp($photo['ratio'], 0.5, 2.2)" />
                            <span class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 transition group-hover:opacity-100"></span>
                            @if ($photo['caption'])<span class="absolute inset-x-0 bottom-0 translate-y-2 p-4 text-left text-sm font-semibold text-white opacity-0 transition group-hover:translate-y-0 group-hover:opacity-100">{{ $photo['caption'] }}</span>@endif
                        </button>
                    @endforeach
                </div>

                {{-- Büyük görünüm --}}
                <div x-show="index !== null" x-cloak x-transition.opacity class="lightbox fixed inset-0 z-[70] flex items-center justify-center bg-[#0b1631]/95 p-4" @click.self="close()" role="dialog" aria-modal="true" aria-label="Fotoğraf">
                    <button type="button" @click="close()" class="absolute top-4 right-4 flex size-11 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20" aria-label="Kapat"><x-icon name="x" class="size-6" /></button>
                    <button type="button" @click="prev()" x-show="photos.length > 1" class="absolute left-3 flex size-12 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20 sm:left-6" aria-label="Önceki"><x-icon name="arrow-right" class="size-6 rotate-180" /></button>
                    <figure class="max-h-full max-w-5xl">
                        <img :src="current.src" :alt="current.caption || ''" class="max-h-[80vh] w-auto rounded-2xl object-contain shadow-2xl">
                        <figcaption class="mt-4 flex items-center justify-between gap-4 text-sm text-white/80">
                            <span x-text="current.caption || ''"></span>
                            <span class="tabular-nums text-white/50" x-text="counter"></span>
                        </figcaption>
                    </figure>
                    <button type="button" @click="next()" x-show="photos.length > 1" class="absolute right-3 flex size-12 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20 sm:right-6" aria-label="Sonraki"><x-icon name="arrow-right" class="size-6" /></button>
                </div>
            </section>
        @endif

        {{-- 8. Ekip --}}
        @if ($c['team'] && $on('team'))
            <section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <p class="{{ $eyebrow }} text-puzzle-blue">İnsanlarımız</p>
                    <h2 class="mt-2 font-display text-3xl font-extrabold tracking-tight sm:text-4xl">{{ $c['team_title'] }}</h2>
                </div>
                <div class="mt-12 flex flex-wrap justify-center gap-6">
                    @foreach ($c['team'] as $i => $member)
                        <article @class(['text-center', 'w-[calc(50%-0.75rem)] sm:w-56' => $teamFrame <= 1.05, 'w-full sm:w-72' => $teamFrame > 1.05])>
                            @if ($member['photo'])
                                <x-store.fit-image :src="$image($member['photo'], 800)" :alt="$member['name']" :ratio="$ratio($member['photo'])" :frame="$teamFrame" class="w-full rounded-3xl shadow-lg" />
                            @else
                                <span class="mx-auto flex w-full items-center justify-center rounded-3xl font-display text-5xl font-extrabold text-white {{ $tone($i)['solid'] }}" style="aspect-ratio: {{ $teamFrame }}">{{ mb_strtoupper(mb_substr($member['name'], 0, 1)) }}</span>
                            @endif
                            <h3 class="mt-4 font-display text-lg font-extrabold">{{ $member['name'] }}</h3>
                            @if ($member['role'])<p class="text-sm text-muted">{{ $member['role'] }}</p>@endif
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- 9. Kapanış bandı --}}
        @if ($c['show_cta'])
            <section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="relative overflow-hidden rounded-[2rem] bg-[#0b1631] px-6 py-14 text-center text-white sm:px-12">
                    <div class="pointer-events-none absolute -top-10 -left-10 grid -rotate-12 grid-cols-2 gap-2 opacity-90" aria-hidden="true">
                        <span class="size-16 rounded-2xl bg-puzzle-orange"></span><span class="size-16 rounded-2xl bg-puzzle-green"></span><span class="size-16 rounded-2xl bg-puzzle-blue"></span><span class="size-16 rounded-2xl bg-puzzle-yellow"></span>
                    </div>
                    <div class="pointer-events-none absolute -right-12 -bottom-12 grid rotate-12 grid-cols-2 gap-2 opacity-90" aria-hidden="true">
                        <span class="size-16 rounded-2xl bg-puzzle-yellow"></span><span class="size-16 rounded-2xl bg-puzzle-blue"></span><span class="size-16 rounded-2xl bg-puzzle-green"></span><span class="size-16 rounded-2xl bg-puzzle-orange"></span>
                    </div>
                    <h2 class="relative mx-auto max-w-2xl font-display text-3xl font-extrabold tracking-tight sm:text-4xl">{{ $c['cta_title'] }}</h2>
                    @if ($c['cta_text'])<p class="relative mx-auto mt-4 max-w-xl text-white/75">{{ $c['cta_text'] }}</p>@endif
                    <a href="{{ $c['cta_url'] ? (str_starts_with($c['cta_url'], '/') ? url($c['cta_url']) : $c['cta_url']) : route('store.products') }}"
                        class="relative mt-8 inline-flex h-12 items-center gap-2 rounded-xl bg-white px-7 font-semibold text-navy transition hover:bg-amber-400">{{ $c['cta_label'] }} <x-icon name="arrow-right" class="size-4" /></a>
                </div>
            </section>
        @endif
    </div>
</x-layouts.store>
