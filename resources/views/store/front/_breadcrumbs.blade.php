{{-- Gezinti yolu: $items = [[ad, adres|null], ...] --}}
<nav aria-label="Gezinti yolu" class="text-sm text-muted">
    <ol class="flex flex-wrap items-center gap-1.5">
        <li><a href="{{ route('store.home') }}" class="hover:text-primary">Ana sayfa</a></li>
        @foreach ($items as [$label, $url])
            <li aria-hidden="true">›</li>
            <li>@if ($url)<a href="{{ $url }}" class="hover:text-primary">{{ $label }}</a>@else<span class="text-ink" aria-current="page">{{ $label }}</span>@endif</li>
        @endforeach
    </ol>
</nav>
