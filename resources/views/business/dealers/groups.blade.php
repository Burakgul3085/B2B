<x-layouts.panel :area="\App\Domain\Auth\Area::Business" title="Bayi Grupları">
    <x-page-header title="Bayi Grupları" subtitle="Bayileri gruplayarak gruba fiyat listesi ve indirim tanımlayın." />
    @error('group')<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card title="Gruplar" class="lg:col-span-2" :padding="false">
            @if ($groups->isEmpty())
                <x-empty-state icon="layers" title="Henüz grup yok" text="Ör. “A Grubu Bayiler”, “Okul Tedarikçileri”." />
            @else
                <ul class="divide-y divide-line">
                    @foreach ($groups as $group)
                        <li class="px-5 py-3" x-data="{ edit: false }">
                            <div class="flex flex-wrap items-center gap-3" x-show="!edit">
                                <div class="flex-1 text-sm">
                                    <a href="{{ route('business.dealers.index', ['group' => $group->id]) }}" class="font-medium hover:text-primary">{{ $group->name }}</a>
                                    <span class="text-muted">· {{ $group->relationships_count }} bayi · {{ $group->priceList?->name ?? 'Fiyat listesi yok' }}</span>
                                </div>
                                <button type="button" @click="edit = true" class="text-sm text-primary hover:underline">Düzenle</button>
                                @if ($group->relationships_count === 0)
                                    <form method="POST" action="{{ route('business.dealer-groups.destroy', $group) }}" data-confirm="Grup silinsin mi?">@csrf @method('DELETE')<button class="text-sm text-danger hover:underline">Sil</button></form>
                                @endif
                            </div>
                            <form x-show="edit" x-cloak method="POST" action="{{ route('business.dealer-groups.update', $group) }}" class="flex flex-wrap items-center gap-2">
                                @csrf @method('PUT')
                                <input name="name" value="{{ $group->name }}" class="field-input max-w-56" required aria-label="Grup adı">
                                <select name="price_list_id" class="field-input max-w-56" aria-label="Fiyat listesi">
                                    <option value="">Fiyat listesi yok</option>
                                    @foreach ($priceLists as $id => $name)<option value="{{ $id }}" @selected($group->price_list_id === $id)>{{ $name }}</option>@endforeach
                                </select>
                                <x-button size="sm">Kaydet</x-button>
                                <button type="button" @click="edit = false" class="text-sm text-muted hover:underline">Vazgeç</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
        <x-card title="Yeni grup">
            <form method="POST" action="{{ route('business.dealer-groups.store') }}" class="space-y-4">
                @csrf
                <x-field name="name" label="Grup adı" required />
                <x-field name="price_list_id" label="Fiyat listesi" :options="['' => 'Yok'] + $priceLists->all()" hint="Listede olmayan ürünlerde standart bayi fiyatı uygulanır." />
                <x-button icon="plus">Ekle</x-button>
            </form>
        </x-card>
    </div>
</x-layouts.panel>
