{{-- Tema: açık / koyu / sistem (resources/js/theme.js, "tema" çerezi). inline: vitrin alt bilgisindeki tek satırlık hâli. --}}
@props(['inline' => false])
<div x-data="themeSwitch" @class(['flex items-center gap-2' => $inline, 'border-t border-line px-4 py-2.5' => ! $inline])>
    <p @class(['text-xs text-muted', 'mb-1.5' => ! $inline])>Tema</p>
    <div @class(['grid grid-cols-3 gap-1 rounded-lg bg-slate-100 p-1 text-xs font-medium', 'w-48' => $inline]) role="radiogroup" aria-label="Tema">
        @foreach (['light' => 'Açık', 'dark' => 'Koyu', 'auto' => 'Sistem'] as $mode => $label)
            <button type="button" role="radio" @click="set('{{ $mode }}')" :aria-checked="mode === '{{ $mode }}'"
                :class="mode === '{{ $mode }}' ? 'bg-white text-ink shadow-xs' : 'text-muted hover:text-ink'" class="rounded-md px-2 py-1">{{ $label }}</button>
        @endforeach
    </div>
</div>
