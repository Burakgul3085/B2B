{{-- Bir varyant için stok işlemleri (giriş / sayım / düzeltme). $variant. Açık sekme Alpine "tab" değişkenindedir. --}}
<div x-data="{ tab: 'receive' }" class="space-y-3">
    <div class="inline-flex rounded-lg border border-line bg-white p-0.5 text-sm" role="tablist">
        @foreach (['receive' => 'Stok girişi', 'count' => 'Sayım', 'adjust' => 'Düzeltme'] as $key => $label)
            <button type="button" role="tab" @click="tab = '{{ $key }}'" :aria-selected="tab === '{{ $key }}'"
                :class="tab === '{{ $key }}' ? 'bg-primary text-white' : 'text-muted hover:text-ink'" class="rounded-md px-3 py-1.5 font-medium">{{ $label }}</button>
        @endforeach
    </div>

    <form x-show="tab === 'receive'" method="POST" action="{{ route('store_admin.stock.receive', $variant) }}" class="flex flex-wrap items-end gap-2">
        @csrf
        <label class="text-sm"><span class="mb-1 block text-muted">Gelen adet</span>
            <input type="number" name="quantity" min="1" required class="field-input w-28" inputmode="numeric"></label>
        <label class="min-w-48 flex-1 text-sm"><span class="mb-1 block text-muted">Açıklama (isteğe bağlı)</span>
            <input name="note" maxlength="500" class="field-input" placeholder="ör. Tedarikçi irsaliyesi 1234"></label>
        <x-button size="sm" icon="plus">Ekle</x-button>
    </form>

    <form x-show="tab === 'count'" x-cloak method="POST" action="{{ route('store_admin.stock.count', $variant) }}" class="flex flex-wrap items-end gap-2">
        @csrf
        <label class="text-sm"><span class="mb-1 block text-muted">Sayılan adet</span>
            <input type="number" name="actual" min="0" required value="{{ $variant->stock_quantity }}" class="field-input w-28" inputmode="numeric"></label>
        <label class="min-w-48 flex-1 text-sm"><span class="mb-1 block text-muted">Açıklama (isteğe bağlı)</span>
            <input name="note" maxlength="500" class="field-input" placeholder="ör. Ay sonu sayımı"></label>
        <x-button size="sm" variant="secondary">Stoğu güncelle</x-button>
        <p class="w-full text-xs text-muted">Raftaki gerçek adedi girin; fark hareket olarak kaydedilir.</p>
    </form>

    <form x-show="tab === 'adjust'" x-cloak method="POST" action="{{ route('store_admin.stock.adjust', $variant) }}" class="flex flex-wrap items-end gap-2">
        @csrf
        <label class="text-sm"><span class="mb-1 block text-muted">Yön</span>
            <select name="direction" class="field-input w-28"><option value="out">Düş (−)</option><option value="in">Ekle (+)</option></select></label>
        <label class="text-sm"><span class="mb-1 block text-muted">Adet</span>
            <input type="number" name="quantity" min="1" required class="field-input w-24" inputmode="numeric"></label>
        <label class="min-w-48 flex-1 text-sm"><span class="mb-1 block text-muted">Açıklama (zorunlu)</span>
            <input name="note" maxlength="500" required class="field-input" placeholder="ör. Hasarlı ürün, kayıp"></label>
        <x-button size="sm" variant="secondary">Kaydet</x-button>
    </form>
</div>
