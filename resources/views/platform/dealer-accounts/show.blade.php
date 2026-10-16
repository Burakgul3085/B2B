@php
    $statuses = ['pending_activation' => 'Şifre bekliyor', 'active' => 'Etkin', 'disabled' => 'Devre dışı'];
    $relStatuses = \App\Models\DealerRelationship::STATUSES;
@endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Platform" :title="$account->dealer->legal_name">
    <x-page-header :title="$account->dealer->legal_name" subtitle="Bayi giriş hesabı">
        <x-slot:breadcrumb><a href="{{ route('platform.dealer-accounts.index') }}" class="hover:underline">Bayi Hesapları</a></x-slot:breadcrumb>
        <x-badge :tone="['pending_activation' => 'warning', 'active' => 'success', 'disabled' => 'neutral'][$account->status]">{{ $statuses[$account->status] }}</x-badge>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="Hesap bilgileri" description="E-posta ve şifre hemen değişir; işlem gerekçesiyle denetim kaydına yazılır. Şifre hiçbir zaman ekranda gösterilmez.">
                <form method="POST" action="{{ route('platform.dealer-accounts.update', $account) }}" class="grid gap-5 sm:grid-cols-2">
                    @csrf @method('PUT')
                    <x-field name="name" label="Ad soyad" :value="old('name', $account->name)" required />
                    <x-field name="email" label="Giriş e-postası" type="email" :value="old('email', $account->email)" required autocomplete="off" />
                    <x-field name="password" label="Yeni şifre (isteğe bağlı)" type="password" autocomplete="new-password" hint="Boş bırakılırsa şifre değişmez. En az 10 karakter; harf ve rakam." />
                    <x-field name="password_confirmation" label="Yeni şifre (tekrar)" type="password" autocomplete="new-password" />
                    <x-field name="reason" label="Gerekçe" textarea required class="sm:col-span-2" hint="Ör. Bayi telefonla aradı, eski adresine erişemiyor." />
                    <div class="sm:col-span-2"><x-button>Kaydet</x-button></div>
                </form>
            </x-card>

            <x-card title="Çalıştığı toptancılar" :padding="false">
                <table class="table-base">
                    <thead><tr><th>Toptancı</th><th>Bayi kodu</th><th>İlişki durumu</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($relationships as $r)
                            <tr>
                                <td><a href="{{ route('platform.businesses.show', $r->business_id) }}" class="font-medium hover:text-primary">{{ $r->business->display_name }}</a><p class="text-xs text-muted">{{ $r->business->legal_name }}</p></td>
                                <td class="tabular-nums">{{ $r->dealer_code }}</td>
                                <td>{{ $relStatuses[$r->status] ?? $r->status }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-muted">Hiçbir toptancıyla ilişkisi yok.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Giriş">
                @if ($account->status === 'pending_activation')
                    <x-badge tone="warning">Şifre belirlenmedi</x-badge>
                    <p class="mt-2 text-xs text-muted">Soldaki formdan şifre belirlediğinizde hesap etkinleşir.</p>
                @endif
                <p class="mt-2 text-xs text-muted">Son giriş: {{ $account->last_login_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i') ?? '—' }}</p>
            </x-card>

            <x-card title="Hesap durumu" description="Devre dışı bırakılan bayi hiçbir toptancıya giriş yapamaz.">
                <form method="POST" action="{{ route('platform.dealer-accounts.status', $account) }}" class="space-y-4">
                    @csrf @method('PUT')
                    <x-field name="status" label="Durum" :options="['active' => 'Etkin', 'disabled' => 'Devre dışı']" :value="$account->status === 'disabled' ? 'disabled' : 'active'" />
                    <x-field name="reason" label="Gerekçe" textarea required />
                    <x-button variant="secondary" size="sm">Durumu güncelle</x-button>
                </form>
            </x-card>
        </div>
    </div>
</x-layouts.panel>
