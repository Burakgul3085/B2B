@props(['banner', 'route', 'index' => 0, 'eager' => false, 'loop' => false])
{{--
    Slayt medyası: hiçbir zaman kırpılmaz, taşmaz. Görsel/video kendi oranında ortalanır; kalan alan
    aynı görselin (videoda kapak görselinin) bulanık hâliyle dolar. Mobil görsel varsa dar ekranda o kullanılır.
    $route: medya adresini veren rota adı (bayi veya toptancı tarafı).
--}}
@php
    $src = route($route, $banner);
    $mobile = $banner->mobile_media_path ? route($route, [$banner, 'v' => 'mobile']) : null;
    $poster = $banner->poster_path ? route($route, [$banner, 'v' => 'poster']) : null;
    $loading = $eager ? 'eager' : 'lazy';
@endphp
<div {{ $attributes->merge(['class' => 'absolute inset-0 overflow-hidden bg-slate-900']) }}>
    @if ($banner->isVideo())
        @if ($poster)
            <img src="{{ $poster }}" alt="" aria-hidden="true" loading="{{ $loading }}" class="absolute inset-0 size-full scale-125 object-cover opacity-60 blur-2xl">
        @endif
        <video data-slide="{{ $index }}" @if ($poster) poster="{{ $poster }}" @endif muted playsinline @if ($loop) loop @endif
            preload="{{ $eager ? 'auto' : 'metadata' }}" x-on:ended="typeof videoEnded === 'function' && videoEnded({{ $index }})"
            class="relative size-full object-contain" aria-label="{{ $banner->title ?: 'Tanıtım videosu' }}">
            <source src="{{ $src }}" type="{{ $banner->media_mime }}">
        </video>
    @else
        <picture class="absolute inset-0" aria-hidden="true">
            @if ($mobile)<source media="(max-width: 767px)" srcset="{{ $mobile }}">@endif
            <img src="{{ $src }}" alt="" loading="{{ $loading }}" class="size-full scale-125 object-cover opacity-70 blur-2xl">
        </picture>
        <picture class="relative block size-full">
            @if ($mobile)<source media="(max-width: 767px)" srcset="{{ $mobile }}">@endif
            <img src="{{ $src }}" alt="{{ $banner->title ?: '' }}" loading="{{ $loading }}" @if ($eager) fetchpriority="high" @endif class="size-full object-contain">
        </picture>
    @endif
</div>
