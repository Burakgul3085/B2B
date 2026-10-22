{{--
    Slayt gösterici. $slides: HomeBanner koleksiyonu (url eklenmiş), $variant: 'hero' (büyük) | 'strip' (ince ara bant).
    Medya kırpılmaz (x-banner-media); metin varsa okunurluk için yumuşak bir gölge katmanı eklenir.
--}}
@php
    $hero = $variant === 'hero';
    $positions = [
        'left' => ['justify-start text-left', 'items-start', 'bg-linear-to-r from-slate-950/80 via-slate-950/30 to-transparent'],
        'center' => ['justify-center text-center', 'items-center', 'bg-slate-950/45'],
        'right' => ['justify-end text-right', 'items-end', 'bg-linear-to-l from-slate-950/80 via-slate-950/30 to-transparent'],
    ];
    $aspect = $hero ? 'aspect-[4/3] sm:aspect-[2/1] lg:aspect-[16/5]' : 'aspect-[2/1] sm:aspect-[4/1] lg:aspect-[6/1]';
@endphp
<section x-data="heroSlider({{ $slides->count() }})" @mouseenter="pause()" @mouseleave="resume()" @focusin="pause()" @focusout="resume()"
    @touchstart.passive="touchStart($event)" @touchend="touchEnd($event)" @slide-video-ended="videoEnded($event.detail)"
    class="group/slider relative overflow-hidden bg-slate-900 shadow-xl ring-1 shadow-slate-900/10 ring-black/5 {{ $hero ? 'rounded-3xl' : 'rounded-2xl' }} {{ $class ?? '' }}"
    aria-roledescription="carousel" aria-label="{{ $hero ? 'Tanıtımlar' : 'Kampanyalar' }}">
    <div class="relative {{ $aspect }}">
        @foreach ($slides as $n => $banner)
            @php [$justify, $align, $scrim] = $positions[$banner->text_position] ?? $positions['left']; $hasText = $banner->title || $banner->subtitle; @endphp
            <div class="absolute inset-0 transition-opacity duration-700 ease-out" :class="i === {{ $n }} ? 'z-10 opacity-100' : 'pointer-events-none z-0 opacity-0'"
                role="group" aria-roledescription="slayt" aria-label="{{ $n + 1 }} / {{ $slides->count() }}" :aria-hidden="i !== {{ $n }}">
                <x-banner-media :banner="$banner" route="dealer.storefront.media" :index="$n" :eager="$hero && $n === 0" :loop="$slides->count() === 1" />

                @if ($hasText)
                    <div class="absolute inset-0 {{ $scrim }}"></div>
                    <div @class(['absolute inset-0 flex', $justify, 'px-6 sm:px-24' => $hero, 'px-5 sm:px-20' => ! $hero])>
                        <div @class(['flex flex-col justify-center text-white', $align, 'max-w-2xl gap-3 py-8 sm:gap-5' => $hero, 'max-w-3xl gap-1.5 py-4 sm:gap-2' => ! $hero])>
                            @if ($banner->title)
                                <h2 @class([
                                    'leading-[1.08] font-bold tracking-tight drop-shadow-sm',
                                    'text-3xl sm:text-5xl lg:text-6xl' => $hero,
                                    'text-xl sm:text-3xl lg:text-4xl' => ! $hero,
                                ])>{{ $banner->title }}</h2>
                            @endif
                            @if ($banner->subtitle)
                                <p @class([
                                    'hidden leading-relaxed text-white/85 sm:block',
                                    'max-w-xl text-base lg:text-xl' => $hero,
                                    'max-w-2xl text-sm lg:text-base' => ! $hero,
                                ])>{{ $banner->subtitle }}</p>
                            @endif
                            @if ($banner->url && $banner->button_label)
                                <a href="{{ $banner->url }}" @class([
                                    'inline-flex items-center gap-2 rounded-full bg-amber-400 font-semibold text-slate-900 shadow-lg shadow-black/20 transition hover:-translate-y-0.5 hover:bg-amber-300',
                                    'mt-2 px-6 py-3 text-sm sm:px-7 sm:py-3.5 sm:text-base' => $hero,
                                    'mt-1 px-5 py-2.5 text-sm' => ! $hero,
                                ])>{{ $banner->button_label }} <x-icon name="arrow-right" class="size-4" /></a>
                            @endif
                        </div>
                    </div>
                @endif
                @if ($banner->url && ! ($hasText && $banner->button_label))
                    <a href="{{ $banner->url }}" class="absolute inset-0" aria-label="{{ $banner->title ?: 'Kampanyayı görüntüle' }}"></a>
                @endif
            </div>
        @endforeach
    </div>

    @if ($slides->count() > 1)
        @foreach (['prev' => ['left-3 sm:left-6', 'Önceki', 'rotate-180'], 'next' => ['right-3 sm:right-6', 'Sonraki', '']] as $action => [$pos, $label, $rotate])
            <button type="button" @click="{{ $action }}()" aria-label="{{ $label }} slayt"
                @class([
                    'absolute top-1/2 z-20 hidden -translate-y-1/2 items-center justify-center rounded-full bg-white/15 text-white ring-1 ring-white/40 backdrop-blur-md transition hover:bg-white/30 sm:flex',
                    $pos,
                    'size-12' => $hero, 'size-10' => ! $hero,
                ])>
                <x-icon name="arrow-right" class="size-5 {{ $rotate }}" />
            </button>
        @endforeach

        <div @class(['absolute inset-x-0 z-20 flex justify-center gap-2', 'bottom-5' => $hero, 'bottom-3' => ! $hero]) role="tablist">
            @foreach ($slides as $n => $banner)
                <button type="button" @click="go({{ $n }})" role="tab" :aria-selected="i === {{ $n }}" aria-label="{{ $n + 1 }}. slayt"
                    class="relative h-2 overflow-hidden rounded-full bg-white/40 transition-all duration-300" :class="i === {{ $n }} ? 'w-10' : 'w-2 hover:bg-white/70'">
                    <span x-show="i === {{ $n }}" class="slider-progress absolute inset-y-0 left-0 rounded-full bg-amber-400"
                        :style="progressStyle"></span>
                </button>
            @endforeach
        </div>
    @endif
</section>
