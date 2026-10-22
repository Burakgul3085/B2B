@props(['name' => 'product_id', 'label' => 'Ürün', 'product' => null, 'required' => true])
@php $selected = old($name) && ! $product ? \App\Models\Product::find(old($name)) : $product; @endphp
<div x-data="productPicker('{{ route('business.stock.lookup') }}', @alpine($selected ? ['id' => $selected->id, 'name' => $selected->name, 'sku' => $selected->sku] : null))" {{ $attributes->only('class') }}>
    <label class="mb-1.5 block text-sm font-medium">{{ $label }}@if ($required)<span class="text-danger"> *</span>@endif</label>
    <input type="hidden" name="{{ $name }}" :value="selected && selected.id">

    <template x-if="selected">
        <div class="flex items-center justify-between rounded-lg border border-line bg-slate-50 px-3 py-2 text-sm">
            <span><span class="font-medium" x-text="selected.name"></span> <span class="text-muted" x-text="'· ' + selected.sku"></span></span>
            <button type="button" class="text-sm font-medium text-primary hover:underline" @click="change()">Değiştir</button>
        </div>
    </template>

    <div x-show="!selected" class="relative" @click.outside="open = false">
        <input x-ref="search" type="search" x-model="q" @input.debounce.300ms="search()" @keydown.escape="open = false"
            class="field-input" placeholder="Ürün adı, kodu veya barkod yazın" autocomplete="off"
            aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}">
        <ul x-cloak x-show="open" class="absolute z-20 mt-1 max-h-72 w-full overflow-auto rounded-lg border border-line bg-white py-1 text-sm shadow-lg">
            <template x-for="p in results" :key="p.id">
                <li><button type="button" @click="pick(p)" class="block w-full px-3 py-2 text-left hover:bg-slate-50">
                    <span class="font-medium" x-text="p.name"></span>
                    <span class="block text-xs text-muted" x-text="p.sku + ' · ' + p.unit"></span>
                </button></li>
            </template>
            <li x-show="!loading && results.length === 0" class="px-3 py-2 text-muted">Ürün bulunamadı.</li>
        </ul>
    </div>
    @error($name)<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@enderror
</div>
