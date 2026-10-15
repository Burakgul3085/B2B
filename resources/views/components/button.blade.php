@props(['variant' => 'primary', 'href' => null, 'type' => 'submit', 'icon' => null, 'size' => 'md'])
@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-lg font-medium transition focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-60';
    $sizes = ['sm' => 'px-3 py-1.5 text-sm', 'md' => 'px-4 py-2 text-sm'];
    $variants = [
        'primary' => 'bg-primary text-white shadow-sm hover:bg-primary-hover',
        'secondary' => 'border border-line bg-white text-ink shadow-xs hover:bg-slate-50',
        'danger' => 'bg-danger text-white shadow-sm hover:bg-red-800',
        'ghost' => 'text-muted hover:bg-slate-100 hover:text-ink',
    ];
    $classes = $base.' '.($sizes[$size] ?? $sizes['md']).' '.($variants[$variant] ?? $variants['primary']);
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-icon :name="$icon" class="size-4" />@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-icon :name="$icon" class="size-4" />@endif
        {{ $slot }}
    </button>
@endif
