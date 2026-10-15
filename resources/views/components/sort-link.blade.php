@props(['column', 'label', 'filters'])
@php
    $active = ($filters['sort'] ?? 'name') === $column;
    $dir = $active && ($filters['dir'] ?? 'asc') === 'asc' ? 'desc' : 'asc';
@endphp
<a href="{{ request()->fullUrlWithQuery(['sort' => $column, 'dir' => $dir, 'page' => null]) }}" class="inline-flex items-center gap-1 hover:text-ink">
    {{ $label }}
    @if ($active)<span aria-hidden="true">{{ ($filters['dir'] ?? 'asc') === 'asc' ? '↑' : '↓' }}</span>@endif
</a>
