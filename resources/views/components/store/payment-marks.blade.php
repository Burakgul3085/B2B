{{--
    Alt bilgideki ödeme yöntemi işaretleri: iyzico ve iyzico'nun kabul ettiği kart ağları (Visa, Mastercard, Maestro,
    American Express, Troy). Havale / EFT ve kapıda ödeme yalnızca ayarlarda açıksa gösterilir.
--}}
@props(['transfer' => false, 'cod' => false])
@php $chip = 'flex h-10 items-center justify-center rounded-lg bg-[#fff] px-3 shadow-sm ring-1 ring-black/5'; @endphp
<ul {{ $attributes->merge(['class' => 'flex flex-wrap items-center justify-center gap-2 lg:justify-end']) }} aria-label="Ödeme yöntemleri">
    <li class="{{ $chip }}" title="iyzico ile öde">
        <svg viewBox="0 0 110 28" class="h-6 w-auto" role="img" aria-label="iyzico ile öde" font-family="Arial, Helvetica, sans-serif">
            <text x="0" y="20" font-size="21" font-weight="700" fill="#1e64ff" letter-spacing="-0.5">iyzico</text>
            <text x="67" y="19" font-size="11" font-weight="700" fill="#0f2a5f">ile Öde</text>
        </svg>
    </li>
    <li class="{{ $chip }}" title="Visa">
        <svg viewBox="0 0 54 20" class="h-5 w-auto" role="img" aria-label="Visa" font-family="Arial, Helvetica, sans-serif">
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
    <li class="{{ $chip }}" title="Maestro">
        <svg viewBox="0 0 40 26" class="h-6 w-auto" role="img" aria-label="Maestro">
            <circle cx="14" cy="13" r="11" fill="#0099df" />
            <circle cx="26" cy="13" r="11" fill="#ed0006" />
            <path d="M20 3.8a11 11 0 0 1 0 18.4 11 11 0 0 1 0-18.4z" fill="#6c6bbd" />
        </svg>
    </li>
    <li class="flex h-10 items-center justify-center rounded-lg bg-[#016fd0] px-3 shadow-sm" title="American Express">
        <svg viewBox="0 0 64 22" class="h-5 w-auto" role="img" aria-label="American Express" font-family="Arial, Helvetica, sans-serif">
            <text x="32" y="10" text-anchor="middle" font-size="9.5" font-weight="900" fill="#fff" letter-spacing="0.6">AMERICAN</text>
            <text x="32" y="21" text-anchor="middle" font-size="9.5" font-weight="900" fill="#fff" letter-spacing="0.6">EXPRESS</text>
        </svg>
    </li>
    <li class="{{ $chip }}" title="Troy">
        <svg viewBox="0 0 50 22" class="h-5 w-auto" role="img" aria-label="Troy" font-family="Arial, Helvetica, sans-serif">
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
