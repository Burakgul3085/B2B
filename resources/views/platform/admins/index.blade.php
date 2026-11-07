<x-layouts.panel :area="\App\Domain\Auth\Area::Platform" title="Platform Yöneticileri">
    <x-page-header title="Platform Yöneticileri" subtitle="Platform paneline giriş yapabilen hesaplar. Kendi hesabınızı ve son etkin yöneticiyi silemezsiniz; hesabınızı Profil ve Güvenlik'ten değiştirebilirsiniz." />

    @error('admin')<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror

    <x-card :padding="false">
        <div class="overflow-x-auto">
            <table class="table-base">
                <thead><tr><th>Ad soyad</th><th>Giriş e-postası</th><th>İki adımlı doğrulama</th><th>Son giriş</th><th>Durum</th><th class="text-right"><span class="sr-only">İşlem</span></th></tr></thead>
                <tbody class="divide-y divide-line">
                    @foreach ($admins as $a)
                        <tr @class(['opacity-60' => ! $a->is_active])>
                            <td class="font-medium text-ink">{{ $a->name }}@if ($a->is($me)) <span class="text-sm font-normal text-muted">(siz)</span>@endif</td>
                            <td class="text-muted">{{ $a->email }}</td>
                            <td><x-badge :tone="$a->hasTwoFactorEnabled() ? 'success' : 'neutral'">{{ $a->hasTwoFactorEnabled() ? 'Açık' : 'Kapalı' }}</x-badge></td>
                            <td class="text-sm text-muted tabular-nums">{{ $a->last_login_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i') ?? '—' }}</td>
                            <td><x-badge :tone="$a->is_active ? 'success' : 'neutral'">{{ $a->is_active ? 'Etkin' : 'Pasif' }}</x-badge></td>
                            <td class="text-right">
                                @unless ($a->is($me))
                                    <form method="POST" action="{{ route('platform.admins.destroy', $a) }}" data-confirm="{{ $a->name }} hesabı kalıcı olarak silinsin mi? Bu işlem geri alınamaz.">
                                        @csrf @method('DELETE')
                                        <x-button variant="danger" size="sm">Sil</x-button>
                                    </form>
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>
</x-layouts.panel>
