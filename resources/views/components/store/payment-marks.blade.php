{{--
    Alt bilgideki ödeme işaretleri, iyzico'nun belgelediği kapsamla birebir (docs.iyzico.com):
    - kart kuruluşları (cardAssociation): Visa, Mastercard, American Express, Troy — kredi, banka ve ön ödemeli kartlar;
    - taksitli ödeme yapılabilen kart aileleri (cardFamily): Bonus, World, Maximum, Axess, Paraf, CardFinans, Advantage;
    - iyzico ile Öde (iyzico hesabıyla ödeme).
    Havale / EFT ve kapıda ödeme yalnızca ayarlarda açıksa gösterilir. Logolar marka renkleriyle çizilmiş temsili işaretlerdir.
    Tüm kutular eşit boyuttadır ve iki ızgara aynı genişliği doldurur (kenarlar hizalı).
--}}
@props(['transfer' => false, 'cod' => false])
@php
    $font = 'Arial, Helvetica, sans-serif';
    $label = 'mb-2.5 text-[11px] font-bold tracking-[0.18em] text-white/45 uppercase';
    $card = 'flex h-12 items-center justify-center rounded-xl bg-[#fff] px-2 shadow-sm ring-1 ring-black/5';
    $families = [
        ['Bonus', 'bonus', '#00a651'],
        ['World', 'world', '#5b2c86'],
        ['Maximum', 'maximum', '#e4007c'],
        ['Axess', 'axess', '#d0021b'],
        ['Paraf', 'paraf', '#0094d9'],
        ['CardFinans', 'CardFinans', '#4b2a7b'],
        ['Advantage', 'advantage', '#e30613'],
    ];
    $others = array_filter(['building' => $transfer ? 'Havale / EFT' : null, 'truck' => $cod ? 'Kapıda ödeme' : null]);
@endphp
<div {{ $attributes->merge(['class' => 'w-full space-y-5']) }}>
    <div>
        <p class="{{ $label }}">Kabul edilen kartlar</p>
        <ul class="grid grid-cols-3 gap-2 sm:grid-cols-6" aria-label="Ödeme yöntemleri">
            <li class="{{ $card }} col-span-3 sm:col-span-2" title="iyzico ile Öde">
                <svg viewBox="0 0 110 28" class="h-6 w-auto" role="img" aria-label="iyzico ile öde" font-family="{{ $font }}">
                    <text x="0" y="20" font-size="21" font-weight="700" fill="#1e64ff" letter-spacing="-0.5">iyzico</text>
                    <text x="67" y="19" font-size="11" font-weight="700" fill="#0f2a5f">ile Öde</text>
                </svg>
            </li>
            <li class="{{ $card }}" title="Visa">
                <svg viewBox="0 0 54 20" class="h-5 w-auto" role="img" aria-label="Visa" font-family="{{ $font }}">
                    <text x="1" y="17" font-size="20" font-weight="900" font-style="italic" fill="#1a1f71" letter-spacing="0.5">VISA</text>
                </svg>
            </li>
            <li class="{{ $card }}" title="Mastercard">
                <svg viewBox="0 0 40 26" class="h-7 w-auto" role="img" aria-label="Mastercard">
                    <circle cx="14" cy="13" r="11" fill="#eb001b" />
                    <circle cx="26" cy="13" r="11" fill="#f79e1b" />
                    <path d="M20 3.8a11 11 0 0 1 0 18.4 11 11 0 0 1 0-18.4z" fill="#ff5f00" />
                </svg>
            </li>
            <li class="{{ $card }}" title="American Express">
                <svg viewBox="0 0 64 30" class="h-8 w-auto" role="img" aria-label="American Express" font-family="{{ $font }}">
                    <rect width="64" height="30" rx="4" fill="#016fd0" />
                    <text x="32" y="13.5" text-anchor="middle" font-size="9" font-weight="900" fill="#fff" letter-spacing="0.5">AMERICAN</text>
                    <text x="32" y="24" text-anchor="middle" font-size="9" font-weight="900" fill="#fff" letter-spacing="0.5">EXPRESS</text>
                </svg>
            </li>
            <li class="{{ $card }}" title="Troy">
                <svg viewBox="0 0 50 22" class="h-5 w-auto" role="img" aria-label="Troy" font-family="{{ $font }}">
                    <text x="0" y="17" font-size="21" font-weight="800" fill="#1d2b4f" letter-spacing="-0.5">tro<tspan fill="#00a99d">y</tspan></text>
                </svg>
            </li>
        </ul>
    </div>

    <div>
        <p class="{{ $label }}">Taksit seçenekleri</p>
        <ul class="grid grid-cols-4 gap-2 sm:grid-cols-7" aria-label="Taksit yapılabilen kartlar">
            @foreach ($families as [$name, $word, $color])
                <li class="flex h-10 items-center justify-center rounded-lg bg-[#fff] px-1.5 shadow-sm ring-1 ring-black/5" title="{{ $name }}">
                    <svg viewBox="0 0 {{ (int) ceil(mb_strlen($word) * 9.4 + 4) }} 18" class="h-4 w-auto max-w-full" role="img" aria-label="{{ $name }}" font-family="{{ $font }}">
                        <text x="0" y="14" font-size="15" font-weight="800" font-style="italic" fill="{{ $color }}" letter-spacing="-0.3">{{ $word }}</text>
                    </svg>
                </li>
            @endforeach
        </ul>
    </div>

    @if ($others)
        <div>
            <p class="{{ $label }}">Diğer ödeme yöntemleri</p>
            <ul class="flex flex-wrap gap-2">
                @foreach ($others as $icon => $text)
                    <li class="flex h-10 items-center gap-2 rounded-lg bg-white/10 px-4 text-sm font-semibold text-white ring-1 ring-white/15"><x-icon :name="$icon" class="size-4" /> {{ $text }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
