@props(['animated' => true, 'mono' => false])
{{--
    Yapboz Kırtasiye logosu: gerçek yapboz kesimiyle birbirine geçen dört parça.
    Parçalar karton dokusu, kabartma ışığı ve gölgeyle çizilir.
    animated: parçalar sürekli ayrılıp dönerek yeniden yerine oturur (hareket azaltma tercihine uyar).
    mono: tek renk, efektsiz (arka plan süsü için, currentColor).
--}}
@php
    $id = 'pz'.substr(md5(uniqid('', true)), 0, 8);
    $cell = 100;

    // Klasik yapboz kulakçığı: kenar boyunca (u) ve dışa doğru (v) birim koordinatlar.
    $knob = [
        [[0.40, 0.00], [0.43, 0.03], [0.40, 0.09]],
        [[0.36, 0.19], [0.43, 0.27], [0.50, 0.27]],
        [[0.57, 0.27], [0.64, 0.19], [0.60, 0.09]],
        [[0.57, 0.03], [0.60, 0.00], [0.65, 0.00]],
    ];

    // Saat yönünde bir kenar: $type 1 = dışa kulakçık, -1 = içe yuva, 0 = düz.
    $edge = function (array $from, array $to, int $type) use ($knob): string {
        [$x1, $y1] = $from;
        [$x2, $y2] = $to;
        $len = hypot($x2 - $x1, $y2 - $y1);
        [$dx, $dy] = [($x2 - $x1) / $len, ($y2 - $y1) / $len];
        [$nx, $ny] = [$dy, -$dx];
        $pt = fn ($u, $v) => round($x1 + $u * $len * $dx + $v * $len * $type * $nx, 2).' '.round($y1 + $u * $len * $dy + $v * $len * $type * $ny, 2);

        if ($type === 0) {
            return "L{$x2} {$y2}";
        }

        $d = 'L'.$pt(0.35, 0);
        foreach ($knob as $c) {
            $d .= ' C'.implode(' ', array_map(fn ($p) => $pt($p[0], $p[1]), $c));
        }

        return $d." L{$x2} {$y2}";
    };

    // [anahtar, sol üst köşe, kenar tipleri üst/sağ/alt/sol, renk açık/koyu, dağılma yönü]
    $pieces = [
        ['a', [0, 0], [0, 1, -1, 0], ['#fdba74', '#ea580c'], [-1, -1]],
        ['b', [$cell, 0], [0, 0, 1, -1], ['#7dd3fc', '#0284c7'], [1, -1]],
        ['c', [0, $cell], [1, -1, 0, 0], ['#86efac', '#16a34a'], [-1, 1]],
        ['d', [$cell, $cell], [-1, 0, 0, 1], ['#fde68a', '#eab308'], [1, 1]],
    ];

    $paths = [];
    foreach ($pieces as [$key, [$x, $y], [$top, $right, $bottom, $left]]) {
        $paths[$key] = "M{$x} {$y} "
            .$edge([$x, $y], [$x + $cell, $y], $top).' '
            .$edge([$x + $cell, $y], [$x + $cell, $y + $cell], $right).' '
            .$edge([$x + $cell, $y + $cell], [$x, $y + $cell], $bottom).' '
            .$edge([$x, $y + $cell], [$x, $y], $left).' Z';
    }
@endphp
<svg {{ $attributes->merge(['class' => 'puzzle-logo'.($animated ? ' is-animated' : '')]) }} viewBox="-40 -40 280 280" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    @unless ($mono)
        <defs>
            @foreach ($pieces as [$key, , , [$light, $dark]])
                <linearGradient id="{{ $id }}-{{ $key }}" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0" stop-color="{{ $light }}" />
                    <stop offset="1" stop-color="{{ $dark }}" />
                </linearGradient>
            @endforeach
            {{-- Karton dokusu + kabartma ışığı + kesim gölgesi + zemin gölgesi --}}
            <filter id="{{ $id }}-f" x="-30%" y="-30%" width="160%" height="160%" color-interpolation-filters="sRGB">
                <feGaussianBlur in="SourceAlpha" stdDeviation="5" result="shadowBlur" />
                <feOffset in="shadowBlur" dx="3" dy="7" result="shadowOff" />
                <feComponentTransfer in="shadowOff" result="shadow"><feFuncA type="linear" slope="0.45" /></feComponentTransfer>

                <feGaussianBlur in="SourceAlpha" stdDeviation="2.4" result="bump" />
                <feSpecularLighting in="bump" surfaceScale="5" specularConstant="0.85" specularExponent="22" lighting-color="#ffffff" result="spec">
                    <feDistantLight azimuth="225" elevation="42" />
                </feSpecularLighting>
                <feComposite in="spec" in2="SourceAlpha" operator="in" result="specIn" />

                <feDiffuseLighting in="bump" surfaceScale="4" diffuseConstant="1.2" lighting-color="#ffffff" result="diffuse">
                    <feDistantLight azimuth="225" elevation="55" />
                </feDiffuseLighting>
                <feComposite in="SourceGraphic" in2="diffuse" operator="arithmetic" k1="1" k2="0" k3="0" k4="0" result="lit" />
                <feComposite in="lit" in2="SourceAlpha" operator="in" result="litIn" />

                <feTurbulence type="fractalNoise" baseFrequency="0.85" numOctaves="2" seed="7" result="noise" />
                <feColorMatrix in="noise" type="matrix" values="0 0 0 0 0  0 0 0 0 0  0 0 0 0 0  0.45 0 0 0 -0.2" result="grain" />
                <feComposite in="grain" in2="SourceAlpha" operator="in" result="grainIn" />

                <feMerge>
                    <feMergeNode in="shadow" />
                    <feMergeNode in="litIn" />
                    <feMergeNode in="grainIn" />
                    <feMergeNode in="specIn" />
                </feMerge>
            </filter>
        </defs>
    @endunless

    <g class="pz-board">
        @foreach ($pieces as [$key, , , , [$sx, $sy]])
            <path class="pz pz-{{ $key }}" style="--pz-x: {{ $sx * 14 }}px; --pz-y: {{ $sy * 14 }}px; --pz-r: {{ ($sx * $sy) * 9 }}deg"
                d="{{ $paths[$key] }}"
                @if ($mono) fill="currentColor" @else fill="url(#{{ $id }}-{{ $key }})" filter="url(#{{ $id }}-f)" stroke="#0f172a" stroke-opacity="0.18" stroke-width="1.2" stroke-linejoin="round" @endif />
        @endforeach
    </g>
</svg>
