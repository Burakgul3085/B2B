@php use App\Domain\Support\Money; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Business" :title="$list->name">
    <x-page-header :title="$list->name" :subtitle="$list->groups->isEmpty() ? 'Henüz bir bayi grubuna atanmadı.' : 'Gruplar: '.$list->groups->pluck('name')->join(', ')">
        <x-slot:breadcrumb><a href="{{ route('business.pricing') }}" class="hover:underline">Fiyatlandırma</a></x-slot:breadcrumb>
        @if ($list->isCurrent())<x-badge tone="success">Geçerli</x-badge>@else<x-badge>Geçerli değil</x-badge>@endif
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card title="Ürün fiyatları" class="lg:col-span-2" :padding="false">
            <form method="GET" class="flex gap-2 border-b border-line p-4">
                <input name="q" value="{{ $term }}" class="field-input max-w-sm" placeholder="Ürün adı veya kodu" aria-label="Ara">
                <x-button variant="secondary" icon="search">Ara</x-button>
            </form>
            @if ($items->isEmpty())
                <x-empty-state icon="list" title="Listede ürün yok" text="Sağdaki formla tek tek ekleyin veya toplu doldurun." />
            @else
                <div class="overflow-x-auto">
                    <table class="table-base">
                        <thead><tr><th>Ürün</th><th class="text-right">Standart bayi</th><th class="text-right">Liste fiyatı</th><th></th></tr></thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($items as $item)
                                <tr>
                                    <td><span class="font-medium">{{ $item->product->name }}</span><p class="text-xs text-muted">{{ $item->product->sku }}</p></td>
                                    <td class="text-right text-muted tabular-nums">{{ Money::formatUnit($item->product->dealer_price ?? $item->product->list_price) }}</td>
                                    <td class="text-right">
                                        <form method="POST" action="{{ route('business.price-lists.items.store', $list) }}" class="flex justify-end gap-2">
                                            @csrf
                                            <input type="hidden" name="product_id" value="{{ $item->product_id }}">
                                            <input type="number" name="price" step="0.0001" min="0" value="{{ $item->price }}" class="field-input w-32 py-1 text-right" aria-label="Fiyat">
                                            <button class="text-sm text-primary hover:underline">Kaydet</button>
                                        </form>
                                    </td>
                                    <td class="text-right"><form method="POST" action="{{ route('business.price-lists.items.destroy', [$list, $item]) }}">@csrf @method('DELETE')<button class="text-sm text-danger hover:underline">Çıkar</button></form></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($items->hasPages())<div class="border-t border-line p-4">{{ $items->links() }}</div>@endif
            @endif
        </x-card>

        <div class="space-y-6">
            <x-card title="Ürün ekle">
                <form method="POST" action="{{ route('business.price-lists.items.store', $list) }}" class="space-y-4">
                    @csrf
                    <x-product-picker />
                    <x-field name="price" label="Fiyat (₺)" type="number" step="0.0001" min="0" required hint="Ürünün KDV dahil/hariç ayarına göre yorumlanır." />
                    <x-button>Ekle</x-button>
                </form>
            </x-card>

            <x-card title="Toplu doldur" description="Standart bayi fiyatı (yoksa liste fiyatı) üzerinden yüzde farkla listeyi doldurur.">
                <form method="POST" action="{{ route('business.price-lists.fill', $list) }}" class="space-y-4" onsubmit="return confirm('Liste toplu olarak doldurulsun mu?')">
                    @csrf
                    <x-field name="category" label="Kategori" :options="['' => 'Tüm ürünler'] + \App\Models\Category::with('parent')->get()->sortBy(fn ($c) => $c->fullName())->mapWithKeys(fn ($c) => [$c->id => $c->fullName()])->all()" />
                    <x-field name="percent" label="Fark (%)" type="number" step="0.01" value="0" required hint="Ör. -5 = %5 daha düşük." />
                    <x-toggle name="overwrite" label="Listede olan fiyatların da üzerine yaz" />
                    <x-button variant="secondary">Doldur</x-button>
                </form>
            </x-card>

            <x-card title="Liste ayarları">
                <form method="POST" action="{{ route('business.price-lists.update', $list) }}" class="space-y-4">
                    @csrf @method('PUT')
                    <x-field name="name" label="Liste adı" :value="$list->name" required />
                    <div class="grid grid-cols-2 gap-3">
                        <x-field name="starts_on" label="Başlangıç" type="date" :value="$list->starts_on?->toDateString()" />
                        <x-field name="ends_on" label="Bitiş" type="date" :value="$list->ends_on?->toDateString()" />
                    </div>
                    <x-toggle name="is_active" label="Aktif" :checked="$list->is_active" />
                    <x-button variant="secondary">Kaydet</x-button>
                </form>
            </x-card>
        </div>
    </div>
</x-layouts.panel>
