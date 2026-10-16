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

        <x-card title="Giriş e-postası" description="Yeni adres hemen geçerli olur; bundan sonra bu adresle giriş yapar, e-postaları bu adreste alırsınız.">
            <dl class="mb-5 text-sm">
                <dt class="text-muted">Mevcut giriş e-postası</dt>
                <dd class="mt-0.5 break-all font-medium">{{ $user->email }}</dd>
            </dl>
            <form method="POST" action="{{ route($area->value.'.account.email') }}" class="space-y-5">
                @csrf
                <x-field name="email" label="Yeni e-posta adresi" type="email" required autocomplete="off" data-1p-ignore data-lpignore="true" />
                <x-field name="email_password" label="Mevcut şifre" type="password" required autocomplete="new-password" hint="Güvenliğiniz için şifrenizle onaylayın." />
                <x-button variant="secondary">E-postayı değiştir</x-button>
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
