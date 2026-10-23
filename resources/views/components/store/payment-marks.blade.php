{{--
    Alt bilgideki ödeme işaretleri, iyzico'nun belgelediği kapsamla birebir (docs.iyzico.com):
    - kart kuruluşları (cardAssociation): Visa, Mastercard, American Express, Troy — kredi, banka ve ön ödemeli kartlar;
    - taksitli ödeme yapılabilen kart aileleri (cardFamily): Bonus, World, Maximum, Axess, Paraf, CardFinans, Advantage;
    - iyzico ile Öde (iyzico hesabıyla ödeme).
    Havale / EFT ve kapıda ödeme yalnızca ayarlarda açıksa gösterilir. Logolar marka renkleriyle çizilmiş temsili işaretlerdir.
--}}
@props(['transfer' => false, 'cod' => false])
@php
    $chip = 'flex h-10 items-center justify-center rounded-lg bg-[#fff] px-3 shadow-sm ring-1 ring-black/5';
    $font = 'Arial, Helvetica, sans-serif';
    // Taksit kart aileleri: [ad, yazım, renk]
    $families = [
        ['Bonus', 'bonus', '#00a651'],
        ['World', 'world', '#5b2c86'],
        ['Maximum', 'maximum', '#e4007c'],
        ['Axess', 'axess', '#d0021b'],
        ['Paraf', 'paraf', '#0094d9'],
        ['CardFinans', 'CardFinans', '#4b2a7b'],
        ['Advantage', 'advantage', '#e30613'],
    ];
@endphp
<div {{ $attributes->merge(['class' => 'flex flex-col items-center gap-3 lg:items-end']) }}>
    <ul class="flex flex-wrap items-center justify-center gap-2 lg:justify-end" aria-label="Ödeme yöntemleri">
        <li class="{{ $chip }}" title="iyzico ile Öde">
            <svg viewBox="0 0 110 28" class="h-6 w-auto" role="img" aria-label="iyzico ile öde" font-family="{{ $font }}">
                <text x="0" y="20" font-size="21" font-weight="700" fill="#1e64ff" letter-spacing="-0.5">iyzico</text>
                <text x="67" y="19" font-size="11" font-weight="700" fill="#0f2a5f">ile Öde</text>
            </svg>
        </li>
        <li class="{{ $chip }}" title="Visa">
            <svg viewBox="0 0 54 20" class="h-5 w-auto" role="img" aria-label="Visa" font-family="{{ $font }}">
                <text x="1" y="17" font-size="20" font-weight="900" font-style="italic" fill="#1a1f71" letter-spacing="0.5">VISA</text>
            </svg>
        </li>
        <li class="{{ $chip }}" title="Mastercard">
            <svg viewBox="0 0 40 26" class="h-6 w-auto" role="img" aria-label="Mastercard">
                <circle cx="14" cy="13" r="11" fill="#eb001b" />
                <circle cx="26" cy="13" r="11" fill="#f79e1b" />
                <path d="M20 3.8a11 11 0 0 1 0 18.4 11 11 0 0 1 0-18.4z" fill="#ff5f00" />
            </svg>
        </li>
        <li class="flex h-10 items-center justify-center rounded-lg bg-[#016fd0] px-3 shadow-sm" title="American Express">
            <svg viewBox="0 0 64 22" class="h-5 w-auto" role="img" aria-label="American Express" font-family="{{ $font }}">
                <text x="32" y="10" text-anchor="middle" font-size="9.5" font-weight="900" fill="#fff" letter-spacing="0.6">AMERICAN</text>
                <text x="32" y="21" text-anchor="middle" font-size="9.5" font-weight="900" fill="#fff" letter-spacing="0.6">EXPRESS</text>
            </svg>
        </li>
        <li class="{{ $chip }}" title="Troy">
            <svg viewBox="0 0 50 22" class="h-5 w-auto" role="img" aria-label="Troy" font-family="{{ $font }}">
                <text x="0" y="17" font-size="21" font-weight="800" fill="#1d2b4f" letter-spacing="-0.5">tro<tspan fill="#00a99d">y</tspan></text>
            </svg>
        </li>
        @if ($transfer)
            <li class="flex h-10 items-center gap-1.5 rounded-lg bg-white/10 px-3 text-xs font-semibold text-white ring-1 ring-white/15"><x-icon name="building" class="size-4" /> Havale / EFT</li>
        @endif
        @if ($cod)
            <li class="flex h-10 items-center gap-1.5 rounded-lg bg-white/10 px-3 text-xs font-semibold text-white ring-1 ring-white/15"><x-icon name="truck" class="size-4" /> Kapıda ödeme</li>
        @endif
    </ul>
    <div class="flex flex-wrap items-center justify-center gap-2 lg:justify-end">
        <span class="text-xs font-semibold tracking-wide text-white/60">Taksit seçenekleri</span>
        <ul class="flex flex-wrap items-center justify-center gap-1.5" aria-label="Taksit yapılabilen kartlar">
            @foreach ($families as [$name, $word, $color])
                <li class="flex h-8 items-center rounded-md bg-[#fff] px-2.5 shadow-sm ring-1 ring-black/5" title="{{ $name }}">
                    <svg viewBox="0 0 {{ (int) ceil(mb_strlen($word) * 9.4 + 4) }} 18" class="h-4 w-auto" role="img" aria-label="{{ $name }}" font-family="{{ $font }}">
                        <text x="0" y="14" font-size="15" font-weight="800" font-style="italic" fill="{{ $color }}" letter-spacing="-0.3">{{ $word }}</text>
                    </svg>
                </li>
            @endforeach
        </ul>
    </div>
</div>
