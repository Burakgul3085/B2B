{{--
    Müşteri hesabı sayfalarının (giriş, üyelik, parola) ortak çerçevesi: solda lacivert marka paneli ve faydalar,
    sağda form ($slot). Telefonda marka paneli kısa bir başlığa dönüşür.
--}}
@props(['title', 'heading', 'subtitle' => null, 'panelTitle' => null, 'panelText' => null, 'benefits' => null, 'wide' => false])
@php
    $storeName = config('store.name');
    $benefits ??= [
        ['package', 'Siparişlerinizi takip edin', 'Kargo durumu ve faturalarınız tek ekranda.'],
        ['heart', 'Favorilerinizi saklayın', 'Beğendiğiniz ürünler her cihazda yanınızda.'],
        ['map-pin', 'Adreslerinizi kaydedin', 'Sonraki alışverişleriniz birkaç tıkla tamamlansın.'],
        ['gift', 'Fırsatlardan ilk siz haberdar olun', 'Size özel kampanya ve indirimler.'],
    ];
    $tones = ['bg-puzzle-orange', 'bg-puzzle-green', 'bg-puzzle-blue', 'bg-puzzle-yellow'];
@endphp
<x-layouts.store :title="$title" :noindex="true">
    <div class="auth-form relative overflow-hidden">
        <div class="pointer-events-none absolute inset-x-0 top-0 -z-10 h-80 bg-gradient-to-b from-surface to-transparent" aria-hidden="true"></div>
        <div @class(['mx-auto px-4 py-8 sm:px-6 sm:py-12 lg:px-8', 'max-w-6xl' => $wide, 'max-w-5xl' => ! $wide])>
            <div class="grid overflow-hidden rounded-[2rem] border border-line bg-white shadow-2xl shadow-navy/10 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
                {{-- Marka paneli --}}
                <aside class="relative hidden overflow-hidden bg-[#0b1631] p-10 text-white lg:flex lg:flex-col">
                    <div class="pointer-events-none absolute -right-16 -bottom-16 grid rotate-12 grid-cols-2 gap-3 opacity-90" aria-hidden="true">
                        <span class="size-28 rounded-[1.75rem] bg-puzzle-orange"></span><span class="size-28 rounded-[1.75rem] bg-puzzle-green"></span>
                        <span class="size-28 rounded-[1.75rem] bg-puzzle-blue"></span><span class="size-28 rounded-[1.75rem] bg-puzzle-yellow"></span>
                    </div>
                    <div class="pointer-events-none absolute -top-24 -left-24 size-72 rounded-full bg-white/5 blur-2xl" aria-hidden="true"></div>

                    <a href="{{ route('store.home') }}" class="relative inline-flex items-center gap-3">
                        <x-puzzle-logo :animated="false" class="size-11" />
                        <span class="font-display text-xl font-extrabold tracking-tight">{{ $storeName }}</span>
                    </a>
                    <div class="relative mt-12">
                        <p class="text-xs font-bold tracking-[0.22em] text-amber-400 uppercase">Hesabım</p>
                        <p class="mt-3 font-display text-3xl leading-tight font-extrabold">{{ $panelTitle ?? 'Her parça, sizin için.' }}</p>
                        <p class="mt-3 max-w-sm leading-relaxed text-white/65">{{ $panelText ?? 'Okul, ofis ve sanat ihtiyaçlarınız için alışverişinizi kolaylaştırın.' }}</p>
                    </div>
                    <ul class="relative mt-10 space-y-5">
                        @foreach ($benefits as $i => [$icon, $label, $text])
                            <li class="flex items-start gap-4">
                                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl text-white shadow-lg shadow-black/20 {{ $tones[$i % 4] }}"><x-icon :name="$icon" class="size-5" /></span>
                                <span><span class="block font-semibold">{{ $label }}</span><span class="block text-sm text-white/60">{{ $text }}</span></span>
                            </li>
                        @endforeach
                    </ul>
                    <p class="relative mt-auto flex items-center gap-2 pt-12 text-xs text-white/50"><x-icon name="lock" class="size-3.5 text-[#34d77b]" /> Bilgileriniz şifreli bağlantıyla korunur.</p>
                </aside>

                {{-- Form --}}
                <section class="p-6 sm:p-10 lg:p-12">
                    <div class="mb-6 flex items-center gap-2 lg:hidden" aria-hidden="true">
                        <span class="h-1.5 w-8 rounded-full bg-puzzle-orange"></span><span class="h-1.5 w-8 rounded-full bg-puzzle-green"></span>
                        <span class="h-1.5 w-8 rounded-full bg-puzzle-blue"></span><span class="h-1.5 w-8 rounded-full bg-puzzle-yellow"></span>
                    </div>
                    <h1 class="font-display text-3xl font-extrabold tracking-tight">{{ $heading }}</h1>
                    @if ($subtitle)<p class="mt-2 text-muted">{{ $subtitle }}</p>@endif
                    <div class="mt-8">{{ $slot }}</div>
                </section>
            </div>
        </div>
    </div>
</x-layouts.store>
