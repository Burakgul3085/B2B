@php
    // Logosu olmayan markalar için addan türetilen sabit renkli monogram.
    $tones = ['bg-sky-100 text-sky-700', 'bg-amber-100 text-amber-700', 'bg-emerald-100 text-emerald-700', 'bg-rose-100 text-rose-700', 'bg-violet-100 text-violet-700', 'bg-orange-100 text-orange-700', 'bg-teal-100 text-teal-700', 'bg-indigo-100 text-indigo-700'];
    $tone = fn ($brand) => $tones[crc32($brand->name) % count($tones)];
    $url = fn ($brand) => route('store.products', ['marka' => [$brand->id]]);
    $logo = fn ($brand) => route('store.media.brand', $brand).'?v='.$brand->updated_at?->timestamp;
    $search = fn ($brand) => mb_strtolower(strtr($brand->name, ['I' => 'ı', 'İ' => 'i']), 'UTF-8');
@endphp
<x-layouts.store title="Markalar" description="Mağazamızdaki tüm markalar. Bir markaya tıklayın, o markanın bütün ürünlerini görün." :canonical="route('store.brands')">
    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        @include('store.front._breadcrumbs', ['items' => [['Markalar', null]]])

        <div class="mt-4 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="flex items-center gap-3 font-display text-2xl font-extrabold tracking-tight sm:text-3xl">
                    Markalar
                    <span class="rounded-full bg-primary-soft px-3 py-1 font-sans text-sm font-semibold text-primary tabular-nums">{{ $brands->count() }}</span>
                </h1>
                <p class="mt-1.5 text-muted">Mağazamızdaki markalar. Bir markaya tıklayın, o markanın bütün ürünlerini görün.</p>
            </div>
            <a href="{{ route('store.products') }}" class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-semibold text-primary hover:bg-primary-soft">
                <x-icon name="arrow-right" class="size-4 rotate-180" /> Tüm ürünlere dön
            </a>
        </div>

        @if ($brands->isEmpty())
            <div class="mt-8 rounded-2xl border border-dashed border-line px-6 py-16 text-center">
                <x-icon name="layers" class="mx-auto size-8 text-slate-300" />
                <p class="mt-3 font-medium">Henüz marka yok.</p>
                <p class="mt-1 text-sm text-muted">Ürünler markalarıyla eklendiğinde burada görünür.</p>
            </div>
        @else
            {{-- ============ Öne çıkan markalar ============ --}}
            @if ($featured->isNotEmpty())
                <section class="mt-8">
                    <h2 class="text-sm font-semibold tracking-[0.18em] text-muted uppercase">Öne çıkan markalar</h2>
                    <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4 xl:grid-cols-8">
                        @foreach ($featured as $brand)
                            <a href="{{ $url($brand) }}" class="group flex flex-col items-center gap-3 rounded-2xl border border-line bg-white p-5 text-center shadow-xs transition duration-200 hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-lg">
                                <span @class(['flex h-20 w-full items-center justify-center', 'rounded-lg bg-[#fff] p-1.5' => $brand->logo_path])>
                                    @if ($brand->logo_path)
                                        <img src="{{ $logo($brand) }}" alt="{{ $brand->name }} logosu" class="max-h-full max-w-full object-contain transition duration-300 group-hover:scale-105" loading="lazy">
                                    @else
                                        <span class="flex size-16 items-center justify-center rounded-2xl text-2xl font-bold {{ $tone($brand) }}">{{ mb_substr($brand->name, 0, 1) }}</span>
                                    @endif
                                </span>
                                <span>
                                    <span class="block font-semibold text-ink group-hover:text-primary">{{ $brand->name }}</span>
                                    <span class="mt-0.5 block text-xs text-muted">{{ $brand->products_count }} ürün</span>
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
                        <p class="mt-1 text-sm text-muted">Mağazamızdaki markaların tam listesi.</p>
                    </div>
                    <div class="relative w-full max-w-sm">
                        <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-slate-400" />
                        <input type="search" x-model="term" placeholder="Marka ara…" aria-label="Marka ara" class="field-input py-2.5 pl-10">
                    </div>
                </div>

                {{-- Harf çubuğu: geniş ekranda kaydırınca üst bilginin altında kalır --}}
                <nav class="z-20 -mx-4 mt-5 border-y border-line bg-surface/90 px-4 py-2.5 backdrop-blur sm:-mx-6 sm:px-6 lg:sticky lg:top-[8.1rem] lg:-mx-8 lg:px-8" aria-label="Harfe göre git">
                    <div class="flex flex-wrap gap-1">
                        @foreach (['#', ...$letters] as $letter)
                            @if ($groups->has($letter))
                                <a href="#harf-{{ $letter === '#' ? 'diger' : $letter }}" class="flex size-8 items-center justify-center rounded-lg text-sm font-semibold text-ink transition hover:bg-navy hover:text-white">{{ $letter }}</a>
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
                        <div id="harf-{{ $letter === '#' ? 'diger' : $letter }}" class="scroll-mt-52" x-show="matchAny(@alpine($items->map($search)->values()))">
                            <div class="flex items-center gap-3">
                                <span class="font-display text-2xl font-extrabold text-primary">{{ $letter }}</span>
                                <span class="h-px flex-1 bg-line"></span>
                            </div>
                            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6">
                                @foreach ($items as $brand)
                                    <a href="{{ $url($brand) }}" x-show="match(@alpine($search($brand)))"
                                        class="group flex items-center gap-3 rounded-xl border border-line bg-white px-4 py-3 shadow-xs transition hover:border-primary/40 hover:shadow-md">
                                        <span class="min-w-0 flex-1 truncate font-semibold text-ink group-hover:text-primary" title="{{ $brand->name }}">{{ $brand->name }}</span>
                                        <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-muted tabular-nums group-hover:bg-primary-soft group-hover:text-primary">{{ $brand->products_count }}</span>
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
    </div>
</x-layouts.store>
