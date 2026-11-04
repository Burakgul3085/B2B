{{-- Yeni ürün: diğer seçenekler (ör. renkler). Her satır ürünün bir seçeneği olur; KDV, desi, ağırlık ve kritik stok ilk seçenekten alınır. --}}
@php
    $rowErrors = collect($errors->getMessages())->filter(fn ($m, $k) => str_starts_with($k, 'options.'))->all();
    $oldRows = collect((array) old('options', []))->filter(fn ($r) => is_array($r))
        ->map(fn ($r) => collect($r)->only(['option_value', 'sku', 'barcode', 'price', 'stock'])->map(fn ($v) => is_scalar($v) ? (string) $v : '')
            ->all())
        ->values()->all();
@endphp
<div x-show="option.trim() !== ''" x-cloak x-data="optionRows(@alpine($oldRows), @alpine($rowErrors), {{ \App\Domain\Store\Catalog\CreateStoreProduct::MAX_OPTIONS }})" class="rounded-xl border border-line">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-4 py-3">
        <div>
            <p class="text-sm font-medium">Diğer seçenekler</p>
            <p class="text-xs text-muted">Aynı ürünün diğer <span x-text="option.toLowerCase()"></span> seçenekleri. KDV, desi, ağırlık ve kritik stok ilk seçenekten alınır; fiyat boşsa ilk seçeneğin fiyatı kullanılır.</p>
        </div>
        <button type="button" @click="add()" x-show="canAdd" class="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline"><x-icon name="plus" class="size-4" /> Seçenek ekle</button>
    </div>
    @error('options')<p class="px-4 pt-3 text-sm text-danger">{{ $message }}</p>@enderror
    <p x-show="empty" class="px-4 py-3 text-sm text-muted">Tek seçenek varsa boş bırakın. Başka renkler için “Seçenek ekle”ye basın.</p>
    <fieldset :disabled="option.trim() === ''">
    <ul class="divide-y divide-line">
        <template x-for="(row, i) in rows" :key="row.key">
            <li class="grid gap-3 px-4 py-3 sm:grid-cols-5 sm:items-start">
                <div>
                    <label class="mb-1 block text-xs font-medium">Değer *</label>
                    <input :name="field(i, 'option_value')" x-model="row.option_value" maxlength="80" required placeholder="Ör. Kırmızı" class="field-input">
                    <p x-show="error(i, 'option_value')" x-text="error(i, 'option_value')" class="mt-1 text-xs text-danger"></p>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium">Stok kodu (SKU) *</label>
                    <input :name="field(i, 'sku')" x-model="row.sku" maxlength="64" required class="field-input">
                    <p x-show="error(i, 'sku')" x-text="error(i, 'sku')" class="mt-1 text-xs text-danger"></p>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium">Barkod</label>
                    <input :name="field(i, 'barcode')" x-model="row.barcode" maxlength="64" inputmode="numeric" class="field-input">
                    <p x-show="error(i, 'barcode')" x-text="error(i, 'barcode')" class="mt-1 text-xs text-danger"></p>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium">Fiyat (KDV dahil, ₺)</label>
                    <input :name="field(i, 'price')" x-model="row.price" inputmode="decimal" placeholder="Aynı" class="field-input">
                    <p x-show="error(i, 'price')" x-text="error(i, 'price')" class="mt-1 text-xs text-danger"></p>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium">Stok</label>
                    <input :name="field(i, 'stock')" x-model="row.stock" type="number" step="1" min="0" placeholder="0" class="field-input">
                    <p x-show="error(i, 'stock')" x-text="error(i, 'stock')" class="mt-1 text-xs text-danger"></p>
                    <button type="button" @click="remove(i)" class="mt-2 text-xs text-danger hover:underline">Satırı sil</button>
                </div>

            </li>
        </template>
    </ul>
    </fieldset>
</div>
