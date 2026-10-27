<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" title="Yöneticiler">
    <x-page-header title="Yöneticiler" subtitle="Mağaza yönetim paneline giriş yapabilen kişiler. Hesaplar silinmez, pasife alınır.">
        <x-button :href="route('store_admin.admins.create')" icon="plus">Yeni yönetici</x-button>
    </x-page-header>

    <x-card :padding="false">
        <div class="overflow-x-auto">
            <table class="table-base">
                <thead><tr><th>Ad soyad</th><th>Giriş e-postası</th><th>Rol</th><th>Son giriş</th><th>Durum</th><th></th></tr></thead>
                <tbody class="divide-y divide-line">
                    @foreach ($admins as $a)
                        <tr @class(['opacity-60' => ! $a->is_active])>
                            <td class="font-medium">{{ $a->name }}@if ($a->id === $me->id) <span class="text-xs font-normal text-muted">(siz)</span>@endif</td>
                            <td class="text-muted">{{ $a->email }}</td>
                            <td><x-badge :tone="$a->is_super ? 'primary' : 'neutral'">{{ $a->roleLabel() }}</x-badge></td>
                            <td class="text-sm text-muted tabular-nums">{{ $a->last_login_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i') ?? '—' }}</td>
                            <td><x-badge :tone="$a->is_active ? 'success' : 'neutral'">{{ $a->is_active ? 'Etkin' : 'Pasif' }}</x-badge></td>
                            <td class="text-right">
                                @if (! $a->is_super && $a->id !== $me->id)
                                    <a href="{{ route('store_admin.admins.edit', $a) }}" class="text-sm font-medium text-primary hover:underline">Yönet</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>
    <p class="mt-3 text-xs text-muted">Süper admin hesaplarını yalnızca platform yöneticisi değiştirebilir.</p>
</x-layouts.panel>
