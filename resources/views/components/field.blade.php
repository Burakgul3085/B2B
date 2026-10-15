@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'hint' => null,
    'required' => false,
    'options' => null,   // select için [değer => etiket]
    'textarea' => false,
])
@php
    $id = 'f_'.str_replace(['[', ']', '.'], '_', $name);
    $errorKey = str_replace(['[', ']'], ['.', ''], $name);
    $hasError = $errors->has($errorKey);
    $current = old($errorKey, $value);
@endphp
<div {{ $attributes->only('class') }}>
    <label for="{{ $id }}" class="mb-1.5 block text-sm font-medium text-ink">
        {{ $label }}@if ($required)<span class="text-danger" aria-hidden="true"> *</span>@endif
    </label>

    @if ($options !== null)
        <select id="{{ $id }}" name="{{ $name }}" class="field-input" @required($required)
            aria-invalid="{{ $hasError ? 'true' : 'false' }}" {{ $attributes->except('class') }}>
            @foreach ($options as $optValue => $optLabel)
                <option value="{{ $optValue }}" @selected((string) $current === (string) $optValue)>{{ $optLabel }}</option>
            @endforeach
        </select>
    @elseif ($textarea)
        <textarea id="{{ $id }}" name="{{ $name }}" rows="3" class="field-input" @required($required)
            aria-invalid="{{ $hasError ? 'true' : 'false' }}" {{ $attributes->except('class') }}>{{ $current }}</textarea>
    @else
        <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" class="field-input" @required($required)
            @if ($type !== 'password') value="{{ $current }}" @endif
            aria-invalid="{{ $hasError ? 'true' : 'false' }}"
            @if ($hint || $hasError) aria-describedby="{{ $id }}_help" @endif
            {{ $attributes->except('class') }}>
    @endif

    @if ($hasError)
        <p id="{{ $id }}_help" class="mt-1.5 text-sm text-danger">{{ $errors->first($errorKey) }}</p>
    @elseif ($hint)
        <p id="{{ $id }}_help" class="mt-1.5 text-xs text-muted">{{ $hint }}</p>
    @endif
</div>
