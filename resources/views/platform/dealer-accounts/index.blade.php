@php $statuses = ['pending_activation' => 'Aktivasyon bekliyor', 'active' => 'Etkin', 'disabled' => 'Devre dışı']; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Platform" title="Bayi Hesapları">
    <x-page-header title="Bayi Hesapları" subtitle="Bayi giriş hesapları. Bir bayi hesabı birden fazla toptancıyla kullanılabilir; giriş e-postasını yalnızca bayi veya platform yöneticisi değiştirebilir." />

    <x-card :padding="false">
        <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-line p-4">
            <x-field name="q" label="Ara" :value="$filters['q'] ?? ''" placeholder="E-posta, yetkili veya firma" class="min-w-60 flex-1" />
            <x-field name="status" label="Durum" :options="['' => 'Tümü'] + $statuses" :value="$filters['status'] ?? ''" class="w-48" />
            <x-button variant="secondary" icon="search">Filtrele</x-button>
        </form>

        @if ($users->isEmpty())
            <x-empty-state icon="users" title="Bayi hesabı bulunamadı" text="Filtreleri temizleyin." class="py-8" />
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>Firma</th><th>Yetkili / giriş e-postası</th><th>Çalıştığı toptancılar</th><th>Son giriş</th><th>Durum</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($users as $u)
                            <tr>
                                <td><a href="{{ route('platform.dealer-accounts.show', $u) }}" class="font-medium text-ink hover:text-primary">{{ $u->dealer->legal_name }}</a></td>
                                <td>{{ $u->name }}<p class="text-xs text-muted">{{ $u->email }}</p></td>
                                <td class="text-sm">{{ ($relationships[$u->dealer_id] ?? collect())->map(fn ($r) => $r->business->display_name)->implode(', ') ?: '—' }}</td>
                                <td class="text-sm text-muted tabular-nums">{{ $u->last_login_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i') ?? '—' }}</td>
                                <td><x-badge :tone="['pending_activation' => 'warning', 'active' => 'success', 'disabled' => 'neutral'][$u->status]">{{ $statuses[$u->status] }}</x-badge></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($users->hasPages())<div class="border-t border-line p-4">{{ $users->links() }}</div>@endif
        @endif
    </x-card>
</x-layouts.panel>
