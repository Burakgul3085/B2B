<x-layouts.panel :area="\App\Domain\Auth\Area::Business" title="Destek Erişimi">
    <x-page-header title="Destek Erişimi" subtitle="Platform yöneticileri siparişlerinizi, bayilerinizi ve ürünlerinizi varsayılan olarak göremez. Teknik destek gerektiğinde belirli bir yöneticiye süreli, salt okunur erişim verin." />

    <div class="grid gap-6 xl:grid-cols-3">
        <x-card title="Erişim ver">
            <form method="POST" action="{{ route('business.support-access.store') }}" class="space-y-4">
                @csrf
                <x-field name="platform_admin_id" label="Platform yöneticisi" :options="['' => 'Seçin'] + $admins->all()" required />
                <x-field name="hours" label="Süre" :options="$durations" value="24" required />
                <x-field name="reason" label="Neden" textarea required maxlength="500" placeholder="ör. Sipariş onay ekranındaki hatanın incelenmesi" />
                <x-button icon="key">Erişim ver</x-button>
            </form>
            <p class="mt-4 text-xs text-muted">Yönetici yalnızca görüntüleyebilir; hiçbir kaydı değiştiremez. Her görüntüleme aşağıda listelenir.</p>
        </x-card>

        <div class="space-y-6 xl:col-span-2">
            <x-card title="Verilen erişimler" :padding="false">
                @if ($grants->isEmpty())
                    <p class="p-5 text-sm text-muted">Henüz destek erişimi verilmedi.</p>
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($grants as $grant)
                            <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3 text-sm">
                                <div class="min-w-0">
                                    <p class="font-medium">{{ $grant->admin?->name ?? 'Yönetici #'.$grant->platform_admin_id }}</p>
                                    <p class="text-xs text-muted">{{ $grant->reason }}</p>
                                    <p class="text-xs text-muted">{{ $grant->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }} →
                                        {{ ($grant->revoked_at ?? $grant->expires_at)->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</p>
                                </div>
                                @if ($grant->isActive())
                                    <div class="flex items-center gap-3">
                                        <x-badge tone="warning">Etkin · {{ $grant->expires_at->diffForHumans(['parts' => 2, 'short' => true, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]) }} kaldı</x-badge>
                                        <form method="POST" action="{{ route('business.support-access.destroy', $grant->id) }}" data-confirm="Erişim hemen kaldırılsın mı?">@csrf @method('DELETE')<x-button size="sm" variant="secondary">Kaldır</x-button></form>
                                    </div>
                                @else
                                    <x-badge>{{ $grant->revoked_at ? 'Kaldırıldı' : 'Süresi doldu' }}</x-badge>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            <x-card title="Destek kayıtları" description="Verilen erişimler ve platform yöneticisinin baktığı bölümler." :padding="false">
                @if ($views->isEmpty())
                    <p class="p-5 text-sm text-muted">Kayıt yok.</p>
                @else
                    <table class="table-base">
                        <thead><tr><th>Tarih</th><th>Kim</th><th>İşlem</th></tr></thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($views as $log)
                                <tr>
                                    <td class="whitespace-nowrap text-muted tabular-nums">{{ $log->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</td>
                                    <td>{{ $log->actor_type === 'platform' ? ($adminNames[$log->actor_id] ?? 'Platform yöneticisi') : 'Siz' }}</td>
                                    <td>{{ match ($log->action) {
                                        'support.granted' => 'Erişim verildi ('.($log->new_values['admin'] ?? '').', '.($log->new_values['hours'] ?? '').' saat)',
                                        'support.revoked' => 'Erişim kaldırıldı',
                                        default => 'Görüntüledi: '.(['orders' => 'Siparişler', 'dealers' => 'Bayiler', 'products' => 'Ürünler'][$log->new_values['section'] ?? ''] ?? '—'),
                                    } }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-card>
        </div>
    </div>
</x-layouts.panel>
