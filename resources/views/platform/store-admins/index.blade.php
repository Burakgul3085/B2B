<x-layouts.panel :area="\App\Domain\Auth\Area::Platform" title="Mağaza Yöneticileri">
    <x-page-header title="Mağaza Yöneticileri" subtitle="Mağaza yönetim paneline ({{ config('store.admin_domain') }}) giriş yapabilen hesaplar. Süper admin yalnızca buradan açılır; diğer yöneticileri süper admin açar.">
        <x-button :href="route('platform.store-admins.create')" icon="plus">Yeni hesap</x-button>
    </x-page-header>

    <x-card :padding="false">
        @if ($admins->isEmpty())
            <x-empty-state icon="cart" title="Henüz mağaza yöneticisi yok" text="Mağaza yönetimini başlatmak için bir süper admin hesabı açın." class="py-10" />
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>Ad soyad</th><th>Giriş e-postası</th><th>Rol</th><th>Son giriş</th><th>Durum</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($admins as $a)
                            <tr @class(['opacity-60' => ! $a->is_active])>
                                <td><a href="{{ route('platform.store-admins.show', $a) }}" class="font-medium text-ink hover:text-primary">{{ $a->name }}</a></td>
                                <td class="text-muted">{{ $a->email }}</td>
                                <td><x-badge :tone="$a->is_super ? 'primary' : 'neutral'">{{ $a->roleLabel() }}</x-badge></td>
                                <td class="text-sm text-muted tabular-nums">{{ $a->last_login_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i') ?? '—' }}</td>
                                <td><x-badge :tone="$a->is_active ? 'success' : 'neutral'">{{ $a->is_active ? 'Etkin' : 'Pasif' }}</x-badge></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
</x-layouts.panel>
