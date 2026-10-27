{{--
    Bayilik çağrısı: solda mesaj, avantajlar ve başvuru adımları; sağda birbirine oturan iki yapboz parçası
    (turuncu: bayilik başvurusu, mavi: bayi girişi). Bağlantılar B2B alanına gider; mağaza B2B verisi kullanmaz.
    Ekrana girince parçalar iki yandan gelip "tık" diye birleşir (dealerJoin, stiller app.css → .dealer-*).
    JavaScript yoksa ya da "hareketi azalt" açıksa alan sabit ve tam görünür.
--}}
@php
    $b2b = rtrim(config('app.url'), '/');
    $title = e($block['title']);
    // Varsayılan başlıktaki "eksik parça" altı çizilerek vurgulanır (başlık değiştirilirse vurgu kendiliğinden kalkar).
    $title = str_replace('eksik parça', '<span class="dealer-mark">eksik parça<svg viewBox="0 0 200 14" preserveAspectRatio="none" aria-hidden="true"><path d="M3 10 C 40 3, 80 3, 120 7 S 180 12, 197 5" /></svg></span>', $title);
    $perks = [
        ['percent', 'Bayiye özel toptan fiyatlar'],
        ['cart', '7/24 çevrimiçi sipariş paneli'],
        ['layers', 'Cari hesap ve ekstre takibi'],
        ['message-circle', 'Size özel duyuru ve kampanyalar'],
    ];
    $steps = ['Başvuru formunu doldurun', 'Başvurunuz incelensin', 'Onaydan sonra panelinize girin'];
@endphp
<section @class(['dealer-join relative isolate overflow-hidden bg-surface pb-20 sm:pb-24', '-mt-16 pt-4 sm:-mt-20' => $joined ?? false, 'pt-16 sm:pt-20' => ! ($joined ?? false)]) x-data="dealerJoin" aria-labelledby="ana-bayilik">
    {{-- Süzülen küçük yapboz parçaları --}}
    <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
        <div class="absolute -top-10 right-1/4 size-80 rounded-full bg-puzzle-orange/10 blur-3xl"></div>
        <div class="absolute bottom-0 left-10 size-72 rounded-full bg-sky-400/10 blur-3xl"></div>
        @foreach (['text-puzzle-orange', 'text-puzzle-green', 'text-puzzle-blue', 'text-puzzle-yellow', 'text-puzzle-green', 'text-puzzle-orange'] as $color)
            <svg viewBox="0 0 24 24" class="dealer-drift {{ $color }}"><path fill="currentColor" d="M10 3a2 2 0 0 1 4 0v1h4a1 1 0 0 1 1 1v4h1a2 2 0 0 1 0 4h-1v4a1 1 0 0 1-1 1h-4v-1a2 2 0 0 0-4 0v1H6a1 1 0 0 1-1-1v-4h1a2 2 0 0 0 0-4H5V5a1 1 0 0 1 1-1h4z" /></svg>
        @endforeach
    </div>

    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="dealer-card rounded-[2.5rem] p-6 shadow-xl shadow-navy/5 ring-1 ring-line backdrop-blur-sm sm:p-10 xl:p-14">
            <div class="grid items-center gap-12 lg:grid-cols-[0.9fr_1.1fr] lg:gap-10 xl:gap-16">
                <div class="dealer-rise">
                    <p class="flex items-center gap-2 text-xs font-bold tracking-[0.25em] text-puzzle-orange uppercase">
                        <span class="inline-flex gap-0.5" aria-hidden="true"><span class="size-2 rounded-[3px] bg-puzzle-orange"></span><span class="size-2 rounded-[3px] bg-puzzle-green"></span><span class="size-2 rounded-[3px] bg-puzzle-blue"></span><span class="size-2 rounded-[3px] bg-puzzle-yellow"></span></span>
                        Bayi ağımıza katılın
                    </p>
                    <h2 id="ana-bayilik" class="mt-4 font-display text-3xl leading-[1.12] font-extrabold tracking-tight text-ink sm:text-5xl">{!! $title !!}</h2>
                    @if ($block['subtitle'])<p class="mt-5 text-lg leading-relaxed text-muted">{{ $block['subtitle'] }}</p>@endif

                    <ul class="mt-8 grid gap-3 sm:grid-cols-2">
                        @foreach ($perks as $i => [$icon, $text])
                            @php $c = \App\Domain\Store\Storefront\Palette::classes(\App\Domain\Store\Storefront\Palette::CYCLE[$i % 4]); @endphp
                            <li class="flex items-center gap-3 rounded-2xl bg-white px-4 py-3 text-sm font-semibold text-ink shadow-sm ring-1 ring-line">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-xl {{ $c['soft'] }} {{ $c['text'] }}"><x-icon :name="$icon" class="size-[18px]" /></span>
                                {{ $text }}
                            </li>
                        @endforeach
                    </ul>

                    <ol class="dealer-steps relative mt-10 grid grid-cols-3 gap-2 text-center" aria-label="Bayilik adımları">
                        <span class="dealer-steps-line" aria-hidden="true"></span>
                        @foreach ($steps as $i => $step)
                            <li class="relative flex flex-col items-center">
                                <span class="dealer-step-dot">{{ $i + 1 }}</span>
                                <span class="mt-3 text-xs leading-snug font-semibold text-muted sm:text-sm">{{ $step }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>

                <div class="dealer-board relative mx-auto grid w-full max-w-xl gap-1 xl:max-w-none xl:grid-cols-2">
                    <div class="dealer-piece dealer-piece-left relative z-10">
                        <a href="{{ $b2b }}/basvuru" class="dealer-tab group">
                            <span class="dealer-piece-art" aria-hidden="true"><svg viewBox="0 0 24 24"><path fill="currentColor" d="M10 3a2 2 0 0 1 4 0v1h4a1 1 0 0 1 1 1v4h1a2 2 0 0 1 0 4h-1v4a1 1 0 0 1-1 1h-4v-1a2 2 0 0 0-4 0v1H6a1 1 0 0 1-1-1v-4h1a2 2 0 0 0 0-4H5V5a1 1 0 0 1 1-1h4z" /></svg></span>
                            <span class="relative">
                                <span class="text-xs font-bold tracking-[0.2em] text-white/75 uppercase">Bayi değil misiniz?</span>
                                <span class="mt-3 block font-display text-2xl leading-tight font-extrabold sm:text-[1.7rem]">Bayimiz olmak ister misiniz?</span>
                                <span class="mt-3 block text-sm leading-relaxed text-white/85">Formu birkaç dakikada doldurun; başvurunuzu inceleyip size dönelim.</span>
                            </span>
                            <span class="relative mt-8 inline-flex min-h-12 items-center justify-between gap-3 rounded-xl bg-[#fff] px-5 py-2.5 text-left font-semibold text-[#c2410c] shadow-lg shadow-black/10">
                                Başvuru formunu doldurun <x-icon name="arrow-right" class="size-4 shrink-0 transition group-hover:translate-x-1" />
                            </span>
                        </a>
                    </div>
                    <div class="dealer-piece dealer-piece-right relative">
                        <a href="{{ $b2b }}/bayi/login" class="dealer-slot group">
                            <span class="dealer-piece-art" aria-hidden="true"><svg viewBox="0 0 24 24"><path fill="currentColor" d="M10 3a2 2 0 0 1 4 0v1h4a1 1 0 0 1 1 1v4h1a2 2 0 0 1 0 4h-1v4a1 1 0 0 1-1 1h-4v-1a2 2 0 0 0-4 0v1H6a1 1 0 0 1-1-1v-4h1a2 2 0 0 0 0-4H5V5a1 1 0 0 1 1-1h4z" /></svg></span>
                            <span class="relative">
                                <span class="text-xs font-bold tracking-[0.2em] text-white/75 uppercase">Zaten bayimiz misiniz?</span>
                                <span class="mt-3 block font-display text-2xl leading-tight font-extrabold sm:text-[1.7rem]">Bayi paneline giriş</span>
                                <span class="mt-3 block text-sm leading-relaxed text-white/85">Sipariş verin, cari hesabınızı ve siparişlerinizi takip edin.</span>
                            </span>
                            <span class="relative mt-8 inline-flex min-h-12 items-center justify-between gap-3 rounded-xl bg-[#fff] px-5 py-2.5 text-left font-semibold text-[#075985] shadow-lg shadow-black/10">
                                <span class="flex items-center gap-2"><x-icon name="key" class="size-4" /> Bayi girişi</span> <x-icon name="arrow-right" class="size-4 shrink-0 transition group-hover:translate-x-1" />
                            </span>
                        </a>
                    </div>
                    {{-- Birleşme anı: halka ve saçılan parçacıklar --}}
                    <span class="dealer-ring" aria-hidden="true"></span>
                    <span class="dealer-burst" aria-hidden="true">@for ($i = 0; $i < 10; $i++)<span></span>@endfor</span>
                </div>
            </div>
        </div>
    </div>
</section>
