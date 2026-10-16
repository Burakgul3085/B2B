<x-layouts.panel :area="$area" title="Profil ve Güvenlik">
    <x-page-header title="Profil ve Güvenlik" subtitle="Giriş bilgilerinizi ve parolanızı yönetin." />

    <div class="grid gap-6 lg:grid-cols-2">
        <x-card title="Profil">
            <form method="POST" action="{{ route($area->value.'.account.profile') }}" class="space-y-5">
                @csrf @method('PUT')
                <x-field name="name" label="Ad soyad" :value="$user->name" required />
                <x-button>Kaydet</x-button>
            </form>
        </x-card>

        <x-card title="Giriş e-postası" description="Yeni adrese doğrulama bağlantısı gönderilir; bağlantıyı açana kadar mevcut adresinizle giriş yaparsınız.">
            @if (session('email_verify_url'))
                <div class="mb-5 rounded-xl border border-amber-200 bg-warning-soft p-4 text-sm text-amber-900" x-data="{ copied: false }">
                    <p class="font-semibold">Doğrulama bağlantısı (demo ortamı)</p>
                    <p class="mt-1">Sistem e-postası henüz tanımlı olmadığı için bağlantı burada gösteriliyor. Canlı ortamda yalnızca yeni adrese e-postayla gider.</p>
                    <div class="mt-3 flex gap-2">
                        <input readonly value="{{ session('email_verify_url') }}" class="field-input font-mono text-xs" x-ref="link" aria-label="Doğrulama bağlantısı">
                        <x-button type="button" variant="secondary" @click="navigator.clipboard.writeText($refs.link.value); copied = true" x-text="copied ? 'Kopyalandı' : 'Kopyala'">Kopyala</x-button>
                        <x-button :href="session('email_verify_url')">Aç</x-button>
                    </div>
                </div>
            @endif
            <dl class="mb-5 text-sm">
                <dt class="text-muted">Mevcut giriş e-postası</dt>
                <dd class="mt-0.5 font-medium">{{ $user->email }}</dd>
                @if ($pendingEmail)
                    <dd class="mt-2"><x-badge tone="warning">Doğrulama bekliyor: {{ $pendingEmail->new_email }}</x-badge></dd>
                @endif
            </dl>
            <form method="POST" action="{{ route($area->value.'.account.email') }}" class="space-y-5">
                @csrf
                <x-field name="email" label="Yeni e-posta adresi" type="email" required autocomplete="email" />
                <x-field name="email_password" label="Mevcut parola" type="password" required autocomplete="current-password" hint="Güvenliğiniz için parolanızla onaylayın." />
                <x-button variant="secondary">Doğrulama bağlantısı gönder</x-button>
            </form>
        </x-card>

        <x-card title="Parola değiştir" description="Değişiklikten sonra diğer cihazlardaki oturumlarınız kapanır.">
            <form method="POST" action="{{ route($area->value.'.account.password') }}" class="space-y-5">
                @csrf @method('PUT')
                <x-field name="current_password" label="Mevcut parola" type="password" required autocomplete="current-password" />
                <x-field name="password" label="Yeni parola" type="password" required autocomplete="new-password" hint="En az 10 karakter; harf ve rakam içermeli." />
                <x-field name="password_confirmation" label="Yeni parola (tekrar)" type="password" required autocomplete="new-password" />
                <x-button>Parolayı değiştir</x-button>
            </form>
        </x-card>
    </div>
</x-layouts.panel>
