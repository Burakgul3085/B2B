@props(['src', 'alt' => '', 'ratio' => null, 'frame' => null, 'fill' => false, 'loading' => 'lazy', 'imgClass' => '', 'maxHeight' => null, 'natural' => false])
{{--
    Fotoğrafı kırpmadan gösterir. Çerçeve oranı ($frame, genişlik/yükseklik) verilmezse fotoğrafın kendi oranı
    ($ratio) kullanılır: fotoğraf çerçeveyi tam doldurur. Oranlar farklıysa fotoğraf bütünüyle ortalanır, kalan
    boşluğu aynı fotoğrafın bulanık hâli doldurur (tek indirme; tarayıcı aynı adresi bir kez alır).
    fill: çerçeve oranı yerine üst öğenin alanını kaplar (üst öğe konumlu ve boyutlu olmalı).
    natural: çerçeve hep fotoğrafın kendi oranıdır; zemin, bulanık dolgu ve kırpma olmaz. Oran bilinmiyorsa fotoğraf
    kendi en/boyuyla çizilir. maxHeight (rem) büyüklüğü sınırlar; genişlik orandan hesaplandığı için oran bozulmaz.
--}}
@php
    if ($natural) {
        $frame = $ratio;
    }
    $frame = (float) ($frame ?: $ratio ?: 1);
    $fills = ! $fill && $ratio && abs($ratio - $frame) / $frame < 0.01;
    $unknown = $natural && ! $ratio && ! $fill;
    // Çağıran "hidden … sm:block" verirse temel "block" sınıfı onunla çakışmasın.
    $base = match (true) {
        (bool) $fill => 'absolute inset-0 overflow-hidden',
        $natural => (preg_match('/(^|\s)hidden(\s|$)/', (string) $attributes->get('class')) ? 'relative overflow-hidden' : 'relative block overflow-hidden'),
        (bool) preg_match('/(^|\s)hidden(\s|$)/', (string) $attributes->get('class')) => 'relative overflow-hidden bg-surface',
        default => 'relative block overflow-hidden bg-surface',
    };
    // En fazla yükseklik (rem) verilirse genişlik orandan hesaplanır; böylece çerçeve oranı bozulmaz.
    // natural: --r (oran) değişkeni çağıranın genişlik sınıflarında kullanılabilir (ör. w-[calc(14rem*var(--r))]).
    $style = $fill || $unknown ? null : 'aspect-ratio: '.round($frame, 4).($natural ? '; --r: '.round($frame, 4) : '').($maxHeight ? '; width: min(100%, '.round($maxHeight * $frame, 3).'rem)' : '');
@endphp
<span {{ $attributes->merge(['class' => $base]) }} @if ($style) style="{{ $style }}" @endif>
    @if ($unknown)
        <img src="{{ $src }}" alt="{{ $alt }}" loading="{{ $loading }}" @if ($loading === 'eager') fetchpriority="high" @endif class="block h-auto w-auto max-w-full {{ $imgClass }}" @if ($maxHeight) style="max-height: {{ $maxHeight }}rem" @endif>
    @else
        @unless ($fills || $natural)
            <img src="{{ $src }}" alt="" aria-hidden="true" loading="{{ $loading }}" class="absolute inset-0 size-full scale-110 object-cover opacity-60 blur-xl">
        @endunless
        <img src="{{ $src }}" alt="{{ $alt }}" loading="{{ $loading }}" @if ($loading === 'eager') fetchpriority="high" @endif class="absolute inset-0 size-full {{ $fills || $natural ? 'object-cover' : 'object-contain' }} {{ $imgClass }}">
    @endif
</span>
