{{-- Aranabilir kategori seçici: $categories [kimlik => "Üst › Alt"], $value. Gönderilen alan category_id; doğrulama sunucudadır. --}}
@php
    $current = old('category_id', $value);
    $hasError = $errors->has('category_id');
    $pickerOptions = collect($categories)->map(fn ($label, $id) => ['id' => $id, 'label' => $label])->values()->all();
    $currentLabel = filled($current) ? ($categories[$current] ?? 'Kategorisiz') : 'Kategorisiz';
@endphp
<div class="relative" x-data="categoryPicker(@alpine($pickerOptions), @alpine($current === null ? '' : (string) $current), 'Kategorisiz')"
    @click.outside="close()" @keydown.escape="close()">
    <label for="f_category_id" class="mb-1.5 block text-sm font-medium text-ink">Kategori</label>
    <input type="hidden" name="category_id" value="{{ $current }}" :value="selected">
    <button type="button" id="f_category_id" x-ref="button" class="field-input flex items-center justify-between gap-2 text-left"
        @click="toggle()" @keydown.arrow-down.prevent="show()" :aria-expanded="open" aria-haspopup="listbox"
        aria-invalid="{{ $hasError ? 'true' : 'false' }}">
        <span class="truncate" x-text="currentLabel">{{ $currentLabel }}</span>
        <span class="shrink-0 text-xs text-muted" aria-hidden="true">▼</span>
    </button>
    <div x-show="open" x-cloak class="absolute inset-x-0 z-20 mt-1 rounded-lg border border-line bg-white shadow-lg">
        <div class="border-b border-line p-2">
            <input type="search" x-ref="search" x-model="q" @input="typed()" class="field-input" placeholder="Kategori ara (ör. cetvel, parti)"
                autocomplete="off" aria-label="Kategori ara"
                @keydown.arrow-down.prevent="down()" @keydown.arrow-up.prevent="up()" @keydown.enter.prevent="enter()">
        </div>
        <ul x-ref="list" role="listbox" class="max-h-72 overflow-y-auto py-1 text-sm">
            <template x-for="(item, index) in results" :key="item.id">
                <li role="option" class="cursor-pointer px-3 py-2" :data-active="isActive(index)" :aria-selected="isSelected(item.id)"
                    :class="isActive(index) ? 'bg-primary/10 text-ink' : 'text-ink hover:bg-slate-50'"
                    @mouseenter="hover(index)" @click="choose(item.id)">
                    <span x-text="item.label" :class="isSelected(item.id) ? 'font-semibold' : ''"></span>
                </li>
            </template>
            <li x-show="empty" class="px-3 py-2 text-muted">Eşleşen kategori yok.</li>
        </ul>
    </div>
    @if ($hasError)
        <p class="mt-1.5 text-sm text-danger">{{ $errors->first('category_id') }}</p>
    @endif
</div>
