{{--
    Ödeme işaretleri, iyzico'nun belgelediği kapsamla birebir (docs.iyzico.com): iyzico ile Öde; kart kuruluşları
    Visa, Mastercard, American Express, Troy; taksit kart aileleri Bonus, Maximum, World, Axess, Paraf, CardFinans,
    Advantage; güvenlik işareti (256-bit SSL, 3D Secure). Kutusuz, tek sıra halinde dizilir.
    $dark: koyu zemin (alt bilgi) — logolar marka renklerinin açık, canlı tonlarıyla çizilir ki koyu zeminde okunsun.
    Havale / EFT ve kapıda ödeme yalnızca ayarlarda açıksa gösterilir. Logolar temsili işaretlerdir.
--}}
@props(['transfer' => false, 'cod' => false, 'dark' => false])
@php
    $font = 'Arial, Helvetica, sans-serif';
    // [açık zemin rengi, koyu zemin rengi]
    $c = fn (string $light, string $onDark) => $dark ? $onDark : $light;
    $families = [
        ['Bonus', 'bonus', $c('#00a651', '#34d77b')],
        ['Maximum', 'maximum', $c('#e4007c', '#ff4fb4')],
        ['World', 'world', $c('#5b2c86', '#c39bf0')],
        ['Axess', 'axess', $c('#d0021b', '#ff5468')],
        ['Paraf', 'paraf', $c('#0094d9', '#4cc6ff')],
        ['CardFinans', 'CardFinans', $c('#4b2a7b', '#b9a0ff')],
        ['Advantage', 'advantage', $c('#e30613', '#ff6b5e')],
    ];
    $item = 'flex h-8 items-center transition hover:scale-105';
    $muted = $dark ? 'text-white/80' : 'text-slate-500';
@endphp
<ul {{ $attributes->merge(['class' => 'flex flex-wrap items-center justify-center gap-x-5 gap-y-4 xl:gap-x-6']) }} aria-label="Ödeme yöntemleri">
    <li class="{{ $item }}" title="iyzico ile Öde">
        <svg viewBox="0 0 110 28" class="h-6 w-auto" role="img" aria-label="iyzico ile öde" font-family="{{ $font }}">
            <text x="0" y="20" font-size="21" font-weight="700" fill="{{ $c('#1e64ff', '#5b9bff') }}" letter-spacing="-0.5">iyzico</text>
            <text x="67" y="19" font-size="11" font-weight="700" fill="{{ $c('#0f2a5f', '#ffffff') }}">ile Öde</text>
        </svg>
    </li>
    <li class="{{ $item }}" title="Visa">
        <svg viewBox="0 0 60 20" class="h-[18px] w-auto" role="img" aria-label="Visa" font-family="{{ $font }}">
            <text x="1" y="17" font-size="20" font-weight="900" font-style="italic" fill="{{ $c('#1a1f71', '#ffffff') }}" letter-spacing="0.5">VISA</text>
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
            <text x="0" y="17" font-size="21" font-weight="800" fill="{{ $c('#1d2b4f', '#ffffff') }}" letter-spacing="-0.5">tro<tspan fill="{{ $c('#00a99d', '#2ee6c8') }}">y</tspan></text>
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
        <li class="flex h-8 items-center gap-1.5 text-xs font-bold tracking-wide uppercase {{ $muted }}"><x-icon name="building" class="size-4" /> Havale / EFT</li>
    @endif
    @if ($cod)
        <li class="flex h-8 items-center gap-1.5 text-xs font-bold tracking-wide uppercase {{ $muted }}"><x-icon name="truck" class="size-4" /> Kapıda ödeme</li>
    @endif
    <li class="flex h-8 items-center gap-2" title="Güvenli ödeme: 256-bit SSL ve 3D Secure">
        <span @class(['flex size-8 items-center justify-center rounded-full', 'bg-[#34d77b]/15 text-[#34d77b] ring-1 ring-[#34d77b]/40' => $dark, 'bg-slate-100 text-slate-600' => ! $dark])><x-icon name="lock" class="size-4" /></span>
        <span class="leading-none">
            <span @class(['block text-sm font-bold', 'text-white' => $dark, 'text-slate-600' => ! $dark])>secure</span>
            <span @class(['block text-[10px]', 'text-white/70' => $dark, 'text-slate-500' => ! $dark])>256-bit SSL · 3D Secure</span>
        </span>
    </li>
</ul>
