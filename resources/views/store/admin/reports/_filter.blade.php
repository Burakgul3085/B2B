<div>
    <label for="rf_{{ $name }}" class="mb-1.5 block text-sm font-medium">{{ $f['label'] }}</label>
    @if ($f['type'] === 'select')
        <select id="rf_{{ $name }}" name="{{ $name }}" class="field-input">
            @foreach ($f['options'] as $optValue => $optLabel)
                <option value="{{ $optValue }}" @selected((string) $value === (string) $optValue)>{{ $optLabel }}</option>
            @endforeach
        </select>
    @else
        <input id="rf_{{ $name }}" name="{{ $name }}" type="{{ $f['type'] === 'date' ? 'date' : ($f['type'] === 'number' ? 'number' : 'text') }}" value="{{ $value }}" class="field-input">
    @endif
</div>
