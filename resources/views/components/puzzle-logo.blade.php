@props(['animated' => true, 'mono' => false])
{{--
    Yapboz Kırtasiye logosu: birbirine geçen dört yapboz parçası.
    animated: parçalar yerine oturur, son parça ara ara kalkıp yerine oturur (hareket azaltma tercihine uyar).
    mono: tek renk (arka plan süsü için, currentColor).
--}}
@php
    $pieces = [
        ['a', '#f97316', 'M0 0H46V15A9 9 0 1 1 46 31V46H31A9 9 0 1 1 15 46H0Z'],
        ['b', '#0ea5e9', 'M54 0H100V46H85A9 9 0 1 1 69 46H54V31A9 9 0 1 0 54 15Z'],
        ['c', '#22c55e', 'M0 54H15A9 9 0 1 0 31 54H46V69A9 9 0 1 1 46 85V100H0Z'],
        ['d', '#facc15', 'M54 54H69A9 9 0 1 0 85 54H100V100H54V85A9 9 0 1 0 54 69Z'],
    ];
@endphp
<svg {{ $attributes->merge(['class' => 'puzzle-logo'.($animated ? ' is-animated' : '')]) }} viewBox="-6 -6 112 112" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    @foreach ($pieces as [$key, $color, $path])
        <path class="pz pz-{{ $key }}" d="{{ $path }}" fill="{{ $mono ? 'currentColor' : $color }}" stroke-linejoin="round" />
    @endforeach
    @unless ($mono)
        {{-- Parçalar üzerinde ince parlama --}}
        <path d="M6 6H40" stroke="#fff" stroke-opacity=".45" stroke-width="3" stroke-linecap="round" />
        <path d="M60 6H94" stroke="#fff" stroke-opacity=".45" stroke-width="3" stroke-linecap="round" />
    @endunless
</svg>
