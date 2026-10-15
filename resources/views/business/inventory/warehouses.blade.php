@php use App\Domain\Inventory\Quantity; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Business" title="Depolar ve Sayım">
    <x-page-header title="Depolar ve Sayım" subtitle="Bayiye gösterilen ve sipariş onayında düşülen stok, varsayılan sevk deposundandır.">
        <x-button variant="secondary" :href="route('business.stock-counts.index')">Stok sayımları</x-button>
    </x-page-header>

    @error('warehouse')<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card title="Depolar" class="lg:col-span-2" :padding="false">
            <ul class="divide-y divide-line">
                @foreach ($warehouses as $warehouse)
                    @php $t = $totals->get($warehouse->id); @endphp
                    <li class="px-5 py-4" x-data="{ edit: false }">
                        <div class="flex flex-wrap items-center gap-3" x-show="!edit">
                            <div class="flex-1">
                                <p class="font-medium">{{ $warehouse->name }} <span class="font-mono text-xs text-muted">{{ $warehouse->code }}</span>
                                    @if ($warehouse->id === $defaultId)<x-badge tone="primary" class="ml-1">Varsayılan sevk deposu</x-badge>@endif
                                    @unless ($warehouse->is_active)<x-badge class="ml-1">Pasif</x-badge>@endunless
                                </p>
                                <p class="text-sm text-muted">{{ (int) ($t->products ?? 0) }} üründe stok · toplam {{ Quantity::format($t->total ?? '0') }} birim</p>
                            </div>
                            @if ($warehouse->id !== $defaultId && $warehouse->is_active)
                                <form method="POST" action="{{ route('business.warehouses.default', $warehouse) }}">@csrf @method('PUT')<button class="text-sm text-primary hover:underline">Varsayılan yap</button></form>
                            @endif
                            <button type="button" @click="edit = true" class="text-sm text-primary hover:underline">Düzenle</button>
                        </div>
                        <form x-cloak x-show="edit" method="POST" action="{{ route('business.warehouses.update', $warehouse) }}" class="grid gap-3 sm:grid-cols-4 sm:items-end">
                            @csrf @method('PUT')
                            <div><label class="mb-1 block text-xs font-medium">Kod</label><input name="code" value="{{ $warehouse->code }}" class="field-input" required></div>
                            <div class="sm:col-span-2"><label class="mb-1 block text-xs font-medium">Ad</label><input name="name" value="{{ $warehouse->name }}" class="field-input" required></div>
                            <label class="flex items-center gap-2 pb-2.5 text-sm"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($warehouse->is_active) class="size-4 rounded"> Aktif</label>
                            <div class="sm:col-span-4"><label class="mb-1 block text-xs font-medium">Adres</label><input name="address" value="{{ $warehouse->address }}" class="field-input"></div>
                            <div class="flex gap-2 sm:col-span-4"><x-button size="sm">Kaydet</x-button><button type="button" @click="edit = false" class="text-sm text-muted hover:underline">Vazgeç</button></div>
                        </form>
                    </li>
                @endforeach
            </ul>
        </x-card>

        <x-card title="Yeni depo">
            <form method="POST" action="{{ route('business.warehouses.store') }}" class="space-y-4">
                @csrf
                <x-field name="code" label="Depo kodu" required hint="Büyük harf, rakam ve tire (ör. ANKARA)." />
                <x-field name="name" label="Depo adı" required />
                <x-field name="address" label="Adres" textarea />
                <x-button icon="plus">Depo ekle</x-button>
            </form>
        </x-card>
    </div>
</x-layouts.panel>
