{{-- Yıldızlar: $rating (0–5, ondalıklı olabilir), $size (Tailwind boyut sınıfı). Yalnızca onaylı yorumlardan gelen puanla kullanılır. --}}
@php $size ??= 'size-4'; @endphp
<span class="inline-flex items-center gap-0.5" role="img" aria-label="5 üzerinden {{ number_format((float) $rating, 1, ',', '') }} puan">
    @for ($i = 1; $i <= 5; $i++)
        <svg viewBox="0 0 24 24" class="{{ $size }} {{ $rating >= $i - 0.25 ? 'text-puzzle-yellow' : ($rating >= $i - 0.75 ? 'text-puzzle-yellow/60' : 'text-line') }}" fill="currentColor" aria-hidden="true"><path d="M12 2.5l2.95 6.1 6.55.95-4.75 4.6 1.12 6.55L12 17.6l-5.87 3.1 1.12-6.55L2.5 9.55l6.55-.95z"/></svg>
    @endfor
</span>
