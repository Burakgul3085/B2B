<x-layouts.panel :area="\App\Domain\Auth\Area::Dealer" title="Sevk Adresleri">
    <x-page-header title="Sevk Adresleri" :subtitle="$currentBusiness->display_name.' siparişlerinde kullanılacak adresler.'" />

    @unless ($relationship->can_edit_addresses)
        <div class="mb-6 rounded-lg border border-blue-200 bg-info-soft px-4 py-3 text-sm text-sky-900">Adres değişikliklerini {{ $currentBusiness->display_name }} yapıyor. Yeni adres veya değişiklik için toptancınızla iletişime geçin. Varsayılan adresinizi seçebilirsiniz.</div>
    @endunless

    <div class="grid gap-6 lg:grid-cols-2">
        <x-card title="Kayıtlı adresler" :padding="false">
            @forelse ($addresses as $address)
                <div class="border-b border-line px-5 py-4 last:border-0" x-data="{ edit: false }">
                    <div x-show="!edit" class="flex gap-3">
                        <div class="flex-1 text-sm">
                            <p class="font-medium">{{ $address->title }} @if ($address->is_default)<x-badge tone="primary" class="ml-1">Varsayılan</x-badge>@endif</p>
                            <p class="text-muted">{{ $address->recipient_name }} · {{ $address->phone }}</p>
                            <p>{{ $address->oneLine() }}</p>
                            @if ($address->note)<p class="text-xs text-muted">{{ $address->note }}</p>@endif
                        </div>
                        <div class="flex flex-col items-end gap-1 text-sm">
                            @unless ($address->is_default)
                                <form method="POST" action="{{ route('dealer.addresses.default', $address) }}">@csrf @method('PUT')<button class="text-primary hover:underline">Varsayılan yap</button></form>
                            @endunless
                            @if ($relationship->can_edit_addresses)
                                <button type="button" @click="edit = true" class="text-primary hover:underline">Düzenle</button>
                                <form method="POST" action="{{ route('dealer.addresses.destroy', $address) }}" onsubmit="return confirm('Adres kaldırılsın mı?')">@csrf @method('DELETE')<button class="text-danger hover:underline">Kaldır</button></form>
                            @endif
                        </div>
                    </div>
                    @if ($relationship->can_edit_addresses)
                        <div x-show="edit" x-cloak>
                            @include('partials.address-form', ['action' => route('dealer.addresses.update', $address), 'address' => $address])
                            <button type="button" @click="edit = false" class="mt-2 text-sm text-muted hover:underline">Vazgeç</button>
                        </div>
                    @endif
                </div>
            @empty
                <x-empty-state icon="building" title="Kayıtlı adresiniz yok" class="py-8" />
            @endforelse
        </x-card>

        @if ($relationship->can_edit_addresses)
            <x-card title="Yeni adres" description="Sipariş verirken bu adreslerden birini seçeceksiniz. Adres değişikliği önceki siparişleri etkilemez.">
                @include('partials.address-form', ['action' => route('dealer.addresses.store'), 'submit' => 'Adres ekle'])
            </x-card>
        @endif
    </div>
</x-layouts.panel>
