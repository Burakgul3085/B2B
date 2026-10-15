@props(['label', 'value', 'hint' => null, 'href' => null])
<div {{ $attributes->merge(['class' => 'rounded-xl border border-line bg-white p-5 shadow-xs']) }}>
    <p class="text-sm font-medium text-muted">{{ $label }}</p>
    <p class="mt-2 text-3xl font-semibold tracking-tight text-ink tabular-nums">{{ $value }}</p>
    @if ($hint || $href)
        <p class="mt-1 text-xs text-muted">
            @if ($href)<a href="{{ $href }}" class="font-medium text-primary hover:underline">{{ $hint ?? 'Görüntüle' }}</a>@else{{ $hint }}@endif
        </p>
    @endif
</div>
