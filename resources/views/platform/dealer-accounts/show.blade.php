@php
    $statuses = ['pending_activation' => 'Aktivasyon bekliyor', 'active' => 'Etkin', 'disabled' => 'Devre dışı'];
    $relStatuses = \App\Models\DealerRelationship::STATUSES;
@endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Platform" :title="$account->dealer->legal_name">
    <x-page-header :title="$account->dealer->legal_name" subtitle="Bayi giriş hesabı">
        <x-slot:breadcrumb><a href="{{ route('platform.dealer-accounts.index') }}" class="hover:underline">Bayi Hesapları</a></x-slot:breadcrumb>
        <x-badge :tone="['pending_activation' => 'warning', 'active' => 'success', 'disabled' => 'neutral'][$account->status]">{{ $statuses[$account->status] }}</x-badge>
    </x-page-header>

    @if (session('reset_url'))
        <div class="mb-6 rounded-xl border border-amber-200 bg-warning-soft p-4 text-sm text-amber-900">
            <p class="font-semibold">Parola sıfırlama bağlantısı (demo ortamı)</p>
            <p class="mt-1">Sistem e-postası tanımlı olmadığı için gösteriliyor; canlıda yalnızca bayinin e-postasına gider.</p>
            <input readonly value="{{ session('reset_url') }}" class="field-input mt-2 font-mono text-xs" aria-label="Parola sıfırlama bağlantısı">
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="Hesap bilgileri" description="Giriş e-postası doğrulama beklenmeden hemen değişir; eski ve yeni adrese bilgi gider, işlem denetim kaydına yazılır.">
                <form method="POST" action="{{ route('platform.dealer-accounts.update', $account) }}" class="grid gap-5 sm:grid-cols-2">
                    @csrf @method('PUT')
                    <x-field name="name" label="Ad soyad" :value="old('name', $account->name)" required />
                    <x-field name="email" label="Giriş e-postası" type="email" :value="old('email', $account->email)" required />
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
            <x-card title="Parola">
                <p class="text-sm text-muted">Parola platform yöneticisine hiçbir zaman gösterilmez. Bayi parolasını unuttuysa sıfırlama bağlantısı gönderin.</p>
                @error('reset')<p class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror
                @if ($account->status === 'active')
                    <form method="POST" action="{{ route('platform.dealer-accounts.password-reset', $account) }}" class="mt-3">@csrf<x-button variant="secondary" size="sm">Parola sıfırlama bağlantısı gönder</x-button></form>
                @else
                    <p class="mt-3 text-xs text-muted">Yalnızca etkin hesaplara gönderilebilir.</p>
                @endif
                <p class="mt-3 text-xs text-muted">Son giriş: {{ $account->last_login_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i') ?? '—' }}</p>
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
