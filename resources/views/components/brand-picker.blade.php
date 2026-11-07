{{-- Yeni marka formu: hazır marka kütüphanesinden logolu öneri (brandPicker). $library: BrandLibrary::suggestions(), $existing: eklenmiş adlar --}}
@props(['action', 'library', 'existing'])
<form method="POST" action="{{ $action }}" class="space-y-4" x-data="brandPicker(@alpine($library), @alpine($existing), @alpine(old('name', '')))" @keydown.escape="close">
    @csrf
    <input type="hidden" name="library" :value="slug">
    <div class="relative">
        <label for="brand_name" class="mb-1.5 block text-sm font-medium text-ink">Marka adı<span class="text-danger" aria-hidden="true"> *</span></label>
        <div class="flex items-center gap-2">
            <template x-if="selected && selected.l">
                <span class="flex h-10 w-14 shrink-0 items-center justify-center rounded-lg border border-line bg-white p-1"><img :src="selected.l" alt="" class="max-h-full max-w-full object-contain"></span>
            </template>
            <input id="brand_name" name="name" x-model="query" @input="typed" @focus="typed" @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)"
                @keydown.enter="enter" @click.outside="close" autocomplete="off" required maxlength="120" class="field-input" role="combobox"
                aria-autocomplete="list" aria-controls="brand_suggestions" :aria-expanded="open && matches.length > 0"
                aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" placeholder="Yazmaya başlayın, ör. Faber-Castell">
        </div>
        <ul id="brand_suggestions" role="listbox" x-cloak x-show="open && matches.length > 0"
            class="absolute z-20 mt-1 max-h-80 w-full overflow-auto rounded-lg border border-line bg-white py-1 shadow-lg">
            <template x-for="(item, index) in matches" :key="item.s">
                <li role="option" :aria-selected="index === active" @mousedown.prevent="choose(item)" @mouseenter="active = index"
                    :class="{ 'bg-primary-soft': index === active, 'cursor-pointer': !isAdded(item), 'opacity-60': isAdded(item) }"
                    class="flex items-center gap-3 px-3 py-2 text-sm">
                    <span class="flex h-8 w-12 shrink-0 items-center justify-center rounded border border-line bg-white p-0.5">
                        <template x-if="item.l"><img :src="item.l" alt="" class="max-h-full max-w-full object-contain" loading="lazy"></template>
                        <template x-if="!item.l"><span class="text-[10px] text-muted">Logo yok</span></template>
                    </span>
                    <span class="flex-1 font-medium text-ink" x-text="item.n"></span>
                    <span x-show="isAdded(item)" class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-muted">Ekli</span>
                </li>
            </template>
        </ul>
        @error('name')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@enderror
        <p class="mt-1.5 text-xs text-muted" x-show="slug">Logosu ile birlikte eklenecek.</p>
        <p class="mt-1.5 text-xs text-muted" x-show="!slug">Listede yoksa adını yazıp ekleyin; logoyu sonra yükleyebilirsiniz.</p>
    </div>
    <x-button icon="plus">Ekle</x-button>
</form>
