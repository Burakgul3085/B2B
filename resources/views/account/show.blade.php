<x-layouts.panel :area="$area" title="Profil ve Güvenlik">
    <x-page-header title="Profil ve Güvenlik" subtitle="Giriş bilgilerinizi ve parolanızı yönetin." />

    <div class="grid gap-6 lg:grid-cols-2">
        <x-card title="Profil">
            <form method="POST" action="{{ route($area->value.'.account.profile') }}" class="space-y-5">
                @csrf @method('PUT')
                <x-field name="name" label="Ad soyad" :value="$user->name" required />
                <div>
                    <p class="mb-1.5 text-sm font-medium">E-posta adresi</p>
                    <p class="text-sm text-muted">{{ $user->email }}</p>
                    <p class="mt-1 text-xs text-muted">Giriş e-postanızın değişmesi için {{ $area === \App\Domain\Auth\Area::Dealer ? 'toptancınızla' : 'platform yöneticisiyle' }} iletişime geçin.</p>
                </div>
                <x-button>Kaydet</x-button>
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
