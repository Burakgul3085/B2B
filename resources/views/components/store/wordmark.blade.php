@props(['name', 'tagline' => null, 'nameClass' => '', 'taglineClass' => '', 'taglineSize' => 10, 'nameSize' => 22])
{{--
    Mağaza adı ve altında slogan, iki satırın kenarları milimetrik hizalı: slogan tek satırda, adın genişliğine
    tam yayılır (SVG textLength harf aralığını ayarlar); tarayıcıda harflerin mürekkep kenarları ölçülerek milimetrik
    eşitlenir (wordmark bileşeni). Ad kısa/slogan uzun olsa da taşmaz, kırılmaz.
    Ekran okuyucu için slogan ayrıca düz metin olarak verilir.
--}}
@php
    $upper = $tagline ? mb_strtoupper(str_replace(['i', 'ı'], ['İ', 'I'], $tagline), 'UTF-8') : '';
    // Slogan doğal hâliyle adın genişliğine sığmıyorsa yazı boyutu küçültülür (harfler üst üste binmesin).
    // Ortalama harf genişliği: ad (kalın, sıkı) ≈ 0,56 em, büyük harf slogan ≈ 0,68 em.
    $nameWidth = mb_strlen($name) * 0.56 * $nameSize;
    $size = $upper ? round(min($taglineSize, $nameWidth * 0.94 / (mb_strlen($upper) * 0.68)), 2) : $taglineSize;
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex w-max flex-col leading-tight']) }} @if ($tagline) x-data="wordmark" @endif>
    <span class="block {{ $nameClass }}" x-ref="name">{{ $name }}</span>
    @if ($tagline)
        <svg class="mt-0.5 w-0 min-w-full overflow-visible {{ $taglineClass }}" height="{{ ceil($size) + 2 }}" aria-hidden="true" focusable="false">
            <text x-ref="text" x="0" y="{{ $size }}" textLength="100%" lengthAdjust="spacing" fill="currentColor" font-size="{{ $size }}" font-weight="700">{{ $upper }}</text>
        </svg>
        <span class="sr-only">{{ $tagline }}</span>
    @endif
</span>
