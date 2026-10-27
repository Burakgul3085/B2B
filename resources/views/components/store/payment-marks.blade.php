{{--
    Ödeme işaretleri: iyzico'nun resmî alt bilgi logo bandı (iyzico ile Öde, Mastercard, Visa, American Express, Troy),
    değiştirilmeden, kendi oranında. Dosyalar ve kaynak: public/images/payments/KAYNAKLAR.md.
    $dark: koyu zemin (alt bilgi) için beyaz bant, açık zeminde renkli bant. $src: mağaza yönetiminden yüklenen bant
    (iyzico yeni bir bant verirse) yerleşik bandın yerine geçer.
    Kılavuz: logonun çevresinde ve iş birliği işaretleriyle arasında en az iki "o" genişliği boşluk bırakılır (gap-x-8).
    Havale / EFT ve kapıda ödeme yalnızca ayarlarda açıksa, logo değil metin olarak gösterilir.
--}}
@props(['transfer' => false, 'cod' => false, 'dark' => false, 'src' => null])
@php
    [$band, $width, $max] = $dark ? ['iyzico-logo-band-white.svg', 456, 'max-w-[456px]'] : ['iyzico-logo-band-colored.svg', 429, 'max-w-[429px]'];
    $muted = $dark ? 'text-white/70' : 'text-slate-500';
@endphp
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center justify-center gap-x-8 gap-y-4']) }}>
    <img src="{{ $src ?? asset('images/payments/'.$band) }}" width="{{ $width }}" height="32" class="h-auto w-full {{ $max }}"
        alt="iyzico ile Öde — Mastercard, Visa, American Express, Troy" loading="lazy" decoding="async">
    <ul class="flex flex-wrap items-center justify-center gap-x-5 gap-y-2 text-xs font-semibold {{ $muted }}" aria-label="Diğer ödeme yöntemleri ve güvenlik">
        @if ($transfer)
            <li class="flex items-center gap-1.5"><x-icon name="building" class="size-4" /> Havale / EFT</li>
        @endif
        @if ($cod)
            <li class="flex items-center gap-1.5"><x-icon name="truck" class="size-4" /> Kapıda ödeme</li>
        @endif
        <li class="flex items-center gap-1.5"><x-icon name="lock" class="size-4" /> 256-bit SSL · 3D Secure</li>
    </ul>
</div>
