@php
    // Logosu olmayan markalar için addan türetilen sabit renkli monogram.
    $tones = ['bg-sky-100 text-sky-700', 'bg-amber-100 text-amber-700', 'bg-emerald-100 text-emerald-700', 'bg-rose-100 text-rose-700', 'bg-violet-100 text-violet-700', 'bg-orange-100 text-orange-700', 'bg-teal-100 text-teal-700', 'bg-indigo-100 text-indigo-700'];
    $tone = fn ($brand) => $tones[crc32($brand->name) % count($tones)];
    $url = fn ($brand) => route('dealer.catalog', ['brand' => [$brand->id]]);
    $search = fn ($brand) => mb_strtolower(strtr($brand->name, ['I' => 'ı', 'İ' => 'i']), 'UTF-8');
@endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Dealer" title="Markalar" wide>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="flex items-center gap-3 text-3xl font-bold tracking-tight text-ink">
                Markalar
                <span class="rounded-full bg-primary-soft px-3 py-1 text-sm font-semibold text-primary tabular-nums">{{ $brands->count() }}</span>
            </h1>
            <p class="mt-1.5 text-muted">Kataloğumuzdaki markalar. Bir markaya tıklayın, o markanın bütün ürünlerini görün.</p>
        </div>
        <a href="{{ route('dealer.catalog') }}" class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-semibold text-primary hover:bg-primary-soft">
            <x-icon name="arrow-right" class="size-4 rotate-180" /> Tüm ürünlere dön
        </a>
    </div>

    @if ($brands->isEmpty())
        <x-card class="mt-8"><x-empty-state icon="layers" title="Henüz marka yok" text="Toptancınız ürünlerini markalarla eklediğinde burada görünür." /></x-card>
    @else
        {{-- ============ Öne çıkan markalar ============ --}}
        @if ($featured->isNotEmpty())
            <section class="mt-8">
                <h2 class="text-sm font-semibold tracking-[0.18em] text-muted uppercase">Öne çıkan markalar</h2>
                <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4 2xl:grid-cols-8">
                    @foreach ($featured as $brand)
                        <a href="{{ $url($brand) }}" class="group flex flex-col items-center gap-3 rounded-2xl border border-line bg-white p-5 text-center shadow-xs transition duration-200 hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-lg">
                            <span class="flex h-20 w-full items-center justify-center">
                                @if ($brand->logo_path)
                                    <img src="{{ route('dealer.brands.logo', $brand) }}?v={{ $brand->updated_at?->timestamp }}" alt="{{ $brand->name }}" class="max-h-full max-w-full object-contain transition duration-300 group-hover:scale-105" loading="lazy">
                                @else
                                    <span class="flex size-16 items-center justify-center rounded-2xl text-2xl font-bold {{ $tone($brand) }}">{{ mb_substr($brand->name, 0, 1) }}</span>
                                @endif
                            </span>
                            <span>
                                <span class="block font-semibold text-ink group-hover:text-primary">{{ $brand->name }}</span>
                                <span class="mt-0.5 block text-xs text-muted">{{ $brand->product_count }} ürün</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- ============ Tüm markalar (A–Z) ============ --}}
        <section class="mt-10" x-data="textFilter">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold tracking-tight text-ink">Tüm markalar (A–Z)</h2>
                    <p class="mt-1 text-sm text-muted">Kataloğumuzdaki markaların tam listesi.</p>
                </div>
                <div class="relative w-full max-w-sm">
                    <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-slate-400" />
                    <input type="search" x-model="term" placeholder="Marka ara…" aria-label="Marka ara" class="field-input py-2.5 pl-10">
                </div>
            </div>

            {{-- Harf çubuğu: kaydırınca üstte kalır --}}
            <nav class="sticky top-0 z-20 -mx-4 mt-5 border-y border-line bg-surface/90 px-4 py-2.5 backdrop-blur sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8" aria-label="Harfe göre git">
                <div class="flex flex-wrap gap-1">
                    @foreach (['#', ...$letters] as $letter)
                        @if ($groups->has($letter))
                            <a href="#harf-{{ $letter === '#' ? 'diger' : $letter }}" class="flex size-8 items-center justify-center rounded-lg text-sm font-semibold text-ink transition hover:bg-primary hover:text-white">{{ $letter }}</a>
                        @elseif ($letter !== '#')
                            <span class="flex size-8 items-center justify-center rounded-lg text-sm text-slate-300" aria-hidden="true">{{ $letter }}</span>
                        @endif
                    @endforeach
                </div>
            </nav>

            <div class="mt-6 space-y-8">
                @foreach (['#', ...$letters] as $letter)
                    @continue(! $groups->has($letter))
                    @php $items = $groups[$letter]; @endphp
                    <div id="harf-{{ $letter === '#' ? 'diger' : $letter }}" class="scroll-mt-20" x-show="matchAny(@alpine($items->map($search)->values()))">
                        <div class="flex items-center gap-3">
                            <span class="text-2xl font-bold text-primary">{{ $letter }}</span>
                            <span class="h-px flex-1 bg-line"></span>
                        </div>
                        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6 2xl:grid-cols-7">
                            @foreach ($items as $brand)
                                <a href="{{ $url($brand) }}" x-show="match(@alpine($search($brand)))"
                                    class="group flex items-center gap-3 rounded-xl border border-line bg-white px-4 py-3 shadow-xs transition hover:border-primary/40 hover:shadow-md">
                                    <span class="min-w-0 flex-1 truncate font-semibold text-ink group-hover:text-primary" title="{{ $brand->name }}">{{ $brand->name }}</span>
                                    <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-muted tabular-nums group-hover:bg-primary-soft group-hover:text-primary">{{ $brand->product_count }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                <p x-cloak x-show="term && ! matchAny(@alpine($brands->map($search)->values()))" class="rounded-xl border border-dashed border-line bg-white px-4 py-8 text-center text-sm text-muted">
                    “<span x-text="term"></span>” ile eşleşen marka bulunamadı.
                </p>
            </div>
        </section>
    @endif
</x-layouts.panel>
