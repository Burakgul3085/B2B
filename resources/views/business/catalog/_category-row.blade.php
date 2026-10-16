@php $count = $productCounts[$category->id] ?? 0; @endphp
<li @class(['flex flex-wrap items-center gap-3 py-3 pr-5', 'pl-5' => $level === 0, 'pl-12 bg-slate-50/50' => $level === 1]) x-data="{ edit: false }">
    <div class="flex-1" x-show="!edit">
        <span @class(['font-medium' => $level === 0])>{{ $category->name }}</span>
        <span class="text-muted">· {{ $count }} ürün</span>
        @unless ($category->is_active)<x-badge class="ml-1">Pasif</x-badge>@endunless
    </div>
    <form x-cloak x-show="edit" method="POST" action="{{ route('business.categories.update', $category) }}" class="flex flex-1 flex-wrap items-center gap-2">
        @csrf @method('PUT')
        <input name="name" value="{{ $category->name }}" class="field-input max-w-56" required maxlength="120" aria-label="Kategori adı">
        <select name="parent_id" class="field-input max-w-48" aria-label="Üst kategori">
            <option value="">Ana kategori</option>
            @foreach ($categories as $parent)
                @continue($parent->id === $category->id)
                <option value="{{ $parent->id }}" @selected($category->parent_id === $parent->id)>{{ $parent->name }}</option>
            @endforeach
        </select>
        <input type="number" name="sort" value="{{ $category->sort }}" min="0" class="field-input w-20" aria-label="Sıra">
        <label class="flex items-center gap-1.5"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($category->is_active) class="size-4 rounded"> Aktif</label>
        <x-button size="sm">Kaydet</x-button>
    </form>
    <button type="button" @click="edit = !edit" class="text-sm text-primary hover:underline" x-text="edit ? 'Vazgeç' : 'Düzenle'"></button>
    @if ($count === 0 && ($level === 1 || $category->children->isEmpty()))
        <form method="POST" action="{{ route('business.categories.destroy', $category) }}" data-confirm="Kategori silinsin mi?">@csrf @method('DELETE')<button class="text-sm text-danger hover:underline">Sil</button></form>
    @endif
</li>
