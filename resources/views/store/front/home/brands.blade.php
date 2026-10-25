{{--
    Markalar: tam genişlikte, sağdan sola kayan logo bandı (üzerine gelince durur). Logosu olmayan markanın adı yazılır.
    Ekranı dolduracak kadar marka yoksa liste tekrarlanır; bant iki kez yazılır, yarısı kayınca başa döner.
--}}
@if ($brands->isNotEmpty())
    @php $reel = collect(range(0, $brands->count() * (int) ceil(10 / $brands->count()) - 1))->map(fn ($n) => $brands[$n % $brands->count()]); @endphp
    <section aria-labelledby="markalar">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @include('store.front.home._heading', ['block' => $block, 'eyebrow' => 'Markalar', 'color' => 'navy', 'link' => [route('store.brands'), 'Tüm markalar']])
        </div>
        <div class="marquee overflow-hidden py-2 [mask-image:linear-gradient(to_right,transparent,#000_6%,#000_94%,transparent)] motion-reduce:overflow-x-auto">
            <div class="marquee-track flex" style="--marquee-duration: {{ $reel->count() * 3 }}s">
                @foreach ([false, true] as $copy)
                    <div class="flex shrink-0 gap-4 pr-4" @if ($copy) aria-hidden="true" @endif>
                        @foreach ($reel as $i => $brand)
                            <a href="{{ route('store.brand', $brand->slug) }}" @if ($copy || $i >= $brands->count()) tabindex="-1" @endif
                                class="group flex h-24 w-44 shrink-0 items-center justify-center rounded-2xl border border-line bg-[#fff] px-6 transition hover:-translate-y-0.5 hover:border-navy-ink/30 hover:shadow-lg hover:shadow-navy/5 sm:h-28 sm:w-52">
                                @if ($brand->logo_path)
                                    <img src="{{ route('store.media.brand', $brand) }}?v={{ $brand->updated_at?->timestamp }}" alt="{{ $copy ? '' : $brand->name }}" loading="lazy" class="max-h-12 max-w-full object-contain opacity-80 grayscale transition group-hover:opacity-100 group-hover:grayscale-0">
                                @else
                                    <span class="text-center font-display text-base font-extrabold text-slate-700 group-hover:text-navy-ink">{{ $brand->name }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif
