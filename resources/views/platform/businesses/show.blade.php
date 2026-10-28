@php use App\Models\Business; use App\Models\BusinessModule; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Platform" :title="$business->display_name">
    <x-page-header :title="$business->display_name" :subtitle="$business->legal_name">
        <x-slot:breadcrumb><a href="{{ route('platform.businesses.index') }}" class="hover:underline">İşletmeler</a></x-slot:breadcrumb>
        <x-badge :tone="['active' => 'success', 'suspended' => 'warning', 'closed' => 'neutral'][$business->status]">{{ Business::STATUSES[$business->status] }}</x-badge>
        <x-button variant="secondary" :href="route('platform.businesses.edit', $business)">Düzenle</x-button>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="İşletme bilgileri">
                <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                    @foreach ([
                        'Kısa ad' => $business->slug, 'E-posta' => $business->email, 'Telefon' => $business->phone,
                        'Vergi dairesi / no' => trim(($business->tax_office ?? '').' '.($business->tax_number ?? '')),
                        'İl / ilçe' => trim(($business->city ?? '').' / '.($business->district ?? ''), ' /'),
                        'Oluşturulma' => $business->created_at->timezone('Europe/Istanbul')->format('d.m.Y'),
                    ] as $label => $value)
                        <div><dt class="text-muted">{{ $label }}</dt><dd class="mt-0.5 font-medium">{{ $value ?: '—' }}</dd></div>
                    @endforeach
                </dl>
            </x-card>

            <x-card title="Lisans dönemleri" :padding="false">
                @if ($business->licenses->isEmpty())
                    <x-empty-state icon="key" title="Lisans tanımlı değil" class="py-8" />
                @else
                    <table class="table-base">
                        <thead><tr><th>Plan</th><th>Başlangıç</th><th>Bitiş</th><th>Sınırlar</th></tr></thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($business->licenses as $license)
                                <tr>
                                    <td class="font-medium">{{ $license->plan }}</td>
                                    <td class="tabular-nums">{{ $license->starts_on->format('d.m.Y') }}</td>
                                    <td class="tabular-nums">{{ $license->ends_on?->format('d.m.Y') ?? 'Süresiz' }}</td>
                                    <td class="text-muted">{{ $license->max_dealers ? $license->max_dealers.' bayi' : 'Bayi sınırı yok' }}, {{ $license->max_products ? $license->max_products.' ürün' : 'ürün sınırı yok' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
                <details class="border-t border-line">
                    <summary class="cursor-pointer px-5 py-3 text-sm font-medium text-primary">Lisans dönemi ekle</summary>
                    <form method="POST" action="{{ route('platform.businesses.licenses.store', $business) }}" class="grid gap-4 px-5 pb-5 sm:grid-cols-3">
                        @csrf
                        <x-field name="plan" label="Plan" value="Standart" required />
                        <x-field name="starts_on" label="Başlangıç" type="date" required />
                        <x-field name="ends_on" label="Bitiş" type="date" />
                        <x-field name="max_dealers" label="Bayi sınırı" type="number" min="1" />
                        <x-field name="max_products" label="Ürün sınırı" type="number" min="1" />
                        <x-field name="note" label="Not" />
                        <div class="sm:col-span-3"><x-button>Ekle</x-button></div>
                    </form>
                </details>
            </x-card>

            <x-card title="Modüller" description="Kapatılan modül işletme panelinde ve bayi panelinde gizlenir.">
                <form method="POST" action="{{ route('platform.businesses.modules', $business) }}">
                    @csrf @method('PUT')
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach (BusinessModule::AVAILABLE as $key => $label)
                            <label class="flex items-center gap-3 rounded-lg border border-line px-4 py-3 text-sm">
                                <input type="checkbox" name="modules[]" value="{{ $key }}" class="size-4 rounded border-line text-primary" @checked($business->moduleEnabled($key))>
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    <x-button class="mt-4" variant="secondary">Modülleri kaydet</x-button>
                </form>
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Ana e-posta (Gmail)" description="Bütün e-postalar bu hesaptan gider; toptancıya gelen bildirimler de bu adrese düşer. Toptancı da kendi E-posta Ayarları ekranından değiştirebilir.">
                @php $mailTone = ['verified' => 'success', 'failed' => 'danger'][$emailSetting?->status] ?? 'warning'; @endphp
                @if ($emailSetting)
                    <p class="text-sm font-medium break-all">{{ $emailSetting->from_email }}</p>
                    <x-badge :tone="$mailTone" class="mt-1">{{ ['verified' => 'Doğrulandı', 'failed' => 'Bağlantı hatalı', 'unverified' => 'Test edilmedi'][$emailSetting->status] ?? $emailSetting->status }}</x-badge>
                @else
                    <x-badge tone="warning">Tanımlı değil — e-postalar gönderilmiyor</x-badge>
                @endif
                @error('mail')<p class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror
                <details class="mt-4 border-t border-line pt-4" @if ($errors->hasAny(['from_email', 'from_name', 'app_password'])) open @endif>
                    <summary class="cursor-pointer text-sm font-medium text-primary">{{ $emailSetting ? 'Ayarı değiştir' : 'Gmail ayarını gir' }}</summary>
                    <form method="POST" action="{{ route('platform.businesses.email', $business) }}" class="mt-3 space-y-4">
                        @csrf @method('PUT')
                        <x-field name="from_email" label="Ana e-posta adresi (Google hesabı)" type="email" :value="old('from_email', $emailSetting?->from_email)" required autocomplete="off" />
                        <x-field name="from_name" label="Görünen ad (isteğe bağlı)" :value="old('from_name', $emailSetting?->from_name)" />
                        <x-field name="app_password" label="16 haneli uygulama şifresi" type="password" autocomplete="new-password" :hint="$emailSetting ? 'Kayıtlı şifre değişmeyecekse boş bırakın.' : 'Google hesabı → Güvenlik → Uygulama şifreleri'" />
                        <x-button variant="secondary" size="sm">Kaydet</x-button>
                    </form>
                </details>
                @if ($emailSetting)
                    <form method="POST" action="{{ route('platform.businesses.email.test', $business) }}" class="mt-3">@csrf<x-button variant="ghost" size="sm" icon="refresh">Bağlantıyı test et</x-button></form>
                @endif
            </x-card>

            <x-card title="İşletme yetkilisi">
                @if ($business->user)
                    <p class="font-medium">{{ $business->user->name }}</p>
                    <p class="text-sm text-muted">{{ $business->user->email }}</p>
                    <div class="mt-3">
                        @if ($business->user->status === 'pending_activation')
                            <x-badge tone="warning">Şifre belirlenmedi</x-badge>
                            <p class="mt-2 text-xs text-muted">Aşağıdan şifre belirlediğinizde hesap etkinleşir.</p>
                        @else
                            <x-badge tone="success">Etkin</x-badge>
                            <p class="mt-2 text-xs text-muted">Son giriş: {{ $business->user->last_login_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i') ?? 'Henüz giriş yapılmadı' }}</p>
                        @endif
                    </div>

                    <details class="mt-4 border-t border-line pt-4" @if ($errors->hasAny(['name', 'email', 'password', 'reason', 'user_email'])) open @endif>
                        <summary class="cursor-pointer text-sm font-medium text-primary">Yetkili bilgilerini düzenle / yeni şifre</summary>
                        @error('user_email')<p class="mt-3 text-sm text-danger">{{ $message }}</p>@enderror
                        <form method="POST" action="{{ route('platform.businesses.user', $business) }}" class="mt-3 space-y-4">
                            @csrf @method('PUT')
                            <x-field name="name" label="Ad soyad" :value="old('name', $business->user->name)" required />
                            <x-field name="email" label="Giriş e-postası" type="email" :value="old('email', $business->user->email)" required autocomplete="off" />
                            <x-field name="password" label="Yeni şifre (isteğe bağlı)" type="password" autocomplete="new-password" hint="Boş bırakılırsa şifre değişmez." />
                            <x-field name="password_confirmation" label="Yeni şifre (tekrar)" type="password" autocomplete="new-password" />
                            <x-field name="reason" label="Gerekçe" textarea required hint="Denetim kaydına yazılır." />
                            <x-button variant="secondary" size="sm">Kaydet</x-button>
                        </form>
                    </details>
                @endif
            </x-card>

            <x-card title="Destek erişimi">
                @if ($supportGrant)
                    <p class="text-sm">İşletme size <span class="font-medium">{{ $supportGrant->expires_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</span> saatine kadar salt okunur erişim verdi.</p>
                    <p class="mt-1 text-xs text-muted">Neden: {{ $supportGrant->reason }}</p>
                    <x-button :href="route('platform.businesses.support', $business)" variant="secondary" class="mt-3" icon="eye">Destek görünümünü aç</x-button>
                @else
                    <p class="text-sm text-muted">Sipariş, bayi ve ürün bilgilerini görmek için işletmenin size Ayarlar → Destek Erişimi ekranından süreli erişim vermesi gerekir.</p>
                @endif
            </x-card>

            <x-card title="Kullanım">
                <dl class="space-y-2 text-sm">
                    @foreach ($usage as $label => $count)
                        <div class="flex justify-between"><dt class="text-muted">{{ $label }}</dt><dd class="font-medium tabular-nums">{{ $count }}</dd></div>
                    @endforeach
                </dl>
                <p class="mt-3 text-xs text-muted">Platform yalnızca adetleri görür; ticari tutarlar işletmeye özeldir.</p>
            </x-card>

            <x-card title="İşletme durumu">
                <form method="POST" action="{{ route('platform.businesses.status', $business) }}" class="space-y-4" x-data="{ status: '{{ old('status', $business->status) }}' }">
                    @csrf @method('PUT')
                    <x-field name="status" label="Durum" :options="Business::STATUSES" :value="$business->status" x-model="status" />
                    <div x-show="status !== 'active'" x-cloak>
                        <x-field name="status_reason" label="Gerekçe" :value="$business->status_reason" textarea hint="Askıya alınan işletmenin kullanıcıları ve bayileri giriş yapamaz." />
                    </div>
                    <x-button variant="secondary">Durumu güncelle</x-button>
                </form>
            </x-card>
        </div>
    </div>
</x-layouts.panel>
