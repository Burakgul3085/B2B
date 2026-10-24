{{--
    Ödeme işaretleri, iyzico'nun belgelediği kapsamla birebir (docs.iyzico.com): iyzico ile Öde; kart kuruluşları
    Visa, Mastercard, American Express, Troy; taksit kart aileleri Bonus, World, Maximum, Axess, Paraf, CardFinans,
    Advantage; güvenlik işareti (256-bit SSL, 3D Secure). Kutusuz, tek sıra halinde açık zemine dizilir (alt bilgideki
    beyaz bant). Havale / EFT ve kapıda ödeme yalnızca ayarlarda açıksa gösterilir. Logolar temsili işaretlerdir.
--}}
@props(['transfer' => false, 'cod' => false])
@php
    $font = 'Arial, Helvetica, sans-serif';
    $families = [
        ['Bonus', 'bonus', '#00a651'],
        ['Maximum', 'maximum', '#e4007c'],
        ['World', 'world', '#5b2c86'],
        ['Axess', 'axess', '#d0021b'],
        ['Paraf', 'paraf', '#0094d9'],
        ['CardFinans', 'CardFinans', '#4b2a7b'],
        ['Advantage', 'advantage', '#e30613'],
    ];
    $item = 'flex h-8 items-center opacity-80 grayscale-[35%] transition hover:opacity-100 hover:grayscale-0';
@endphp
<ul {{ $attributes->merge(['class' => 'flex flex-wrap items-center justify-center gap-x-5 gap-y-4 xl:gap-x-6']) }} aria-label="Ödeme yöntemleri">
    <li class="{{ $item }}" title="iyzico ile Öde">
        <svg viewBox="0 0 110 28" class="h-6 w-auto" role="img" aria-label="iyzico ile öde" font-family="{{ $font }}">
            <text x="0" y="20" font-size="21" font-weight="700" fill="#1e64ff" letter-spacing="-0.5">iyzico</text>
            <text x="67" y="19" font-size="11" font-weight="700" fill="#0f2a5f">ile Öde</text>
        </svg>
    </li>
    <li class="{{ $item }}" title="Visa">
        <svg viewBox="0 0 60 20" class="h-[18px] w-auto" role="img" aria-label="Visa" font-family="{{ $font }}">
            <text x="1" y="17" font-size="20" font-weight="900" font-style="italic" fill="#1a1f71" letter-spacing="0.5">VISA</text>
        </svg>
    </li>
    <li class="{{ $item }}" title="Mastercard">
        <svg viewBox="0 0 40 26" class="h-7 w-auto" role="img" aria-label="Mastercard">
            <circle cx="14" cy="13" r="11" fill="#eb001b" />
            <circle cx="26" cy="13" r="11" fill="#f79e1b" />
            <path d="M20 3.8a11 11 0 0 1 0 18.4 11 11 0 0 1 0-18.4z" fill="#ff5f00" />
        </svg>
    </li>
    <li class="{{ $item }}" title="American Express">
        <svg viewBox="0 0 64 30" class="h-7 w-auto" role="img" aria-label="American Express" font-family="{{ $font }}">
            <rect width="64" height="30" rx="4" fill="#016fd0" />
            <text x="32" y="13.5" text-anchor="middle" font-size="9" font-weight="900" fill="#fff" letter-spacing="0.5">AMERICAN</text>
            <text x="32" y="24" text-anchor="middle" font-size="9" font-weight="900" fill="#fff" letter-spacing="0.5">EXPRESS</text>
        </svg>
    </li>
    <li class="{{ $item }}" title="Troy">
        <svg viewBox="0 0 50 22" class="h-5 w-auto" role="img" aria-label="Troy" font-family="{{ $font }}">
            <text x="0" y="17" font-size="21" font-weight="800" fill="#1d2b4f" letter-spacing="-0.5">tro<tspan fill="#00a99d">y</tspan></text>
        </svg>
    </li>
    @foreach ($families as [$name, $word, $color])
        <li class="{{ $item }}" title="{{ $name }} — taksit">
            <svg viewBox="0 0 {{ (int) ceil(mb_strlen($word) * 9.4 + 4) }} 18" class="h-[17px] w-auto" role="img" aria-label="{{ $name }}" font-family="{{ $font }}">
                <text x="0" y="14" font-size="15" font-weight="800" font-style="italic" fill="{{ $color }}" letter-spacing="-0.3">{{ $word }}</text>
            </svg>
        </li>
    @endforeach
    @if ($transfer)
        <li class="flex h-8 items-center gap-1.5 text-xs font-bold tracking-wide text-slate-500 uppercase"><x-icon name="building" class="size-4" /> Havale / EFT</li>
    @endif
    @if ($cod)
        <li class="flex h-8 items-center gap-1.5 text-xs font-bold tracking-wide text-slate-500 uppercase"><x-icon name="truck" class="size-4" /> Kapıda ödeme</li>
    @endif
    <li class="flex h-8 items-center gap-2 text-slate-500" title="Güvenli ödeme: 256-bit SSL ve 3D Secure">
        <span class="flex size-8 items-center justify-center rounded-full bg-slate-100 text-slate-600"><x-icon name="lock" class="size-4" /></span>
        <span class="leading-none"><span class="block text-sm font-bold text-slate-600">secure</span><span class="block text-[10px]">256-bit SSL · 3D Secure</span></span>
    </li>
</ul>
