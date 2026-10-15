@props(['tone' => 'neutral'])
@php
    $tones = [
        'neutral' => 'bg-slate-100 text-slate-700',
        'primary' => 'bg-primary-soft text-primary',
        'success' => 'bg-success-soft text-success',
        'warning' => 'bg-warning-soft text-warning',
        'danger' => 'bg-danger-soft text-danger',
        'info' => 'bg-info-soft text-info',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium '.($tones[$tone] ?? $tones['neutral'])]) }}>{{ $slot }}</span>
