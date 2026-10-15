@props(['name', 'label', 'checked' => false, 'hint' => null])
<label {{ $attributes->merge(['class' => 'flex items-start gap-3 text-sm']) }}>
    <input type="hidden" name="{{ $name }}" value="0">
    <input type="checkbox" name="{{ $name }}" value="1" class="mt-0.5 size-4 rounded border-line text-primary" @checked(old($name, $checked))>
    <span>
        <span class="font-medium text-ink">{{ $label }}</span>
        @if ($hint)<span class="block text-xs text-muted">{{ $hint }}</span>@endif
    </span>
</label>
