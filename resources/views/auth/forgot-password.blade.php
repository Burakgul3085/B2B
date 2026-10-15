<x-layouts.auth :area="$area" title="Parolamı unuttum">
    <p class="mb-5 text-sm text-muted">Hesabınıza kayıtlı e-posta adresini girin. Size yeni parola belirleme bağlantısı gönderelim.</p>

    <form method="POST" action="{{ route($area->value.'.password.email') }}" class="space-y-5">
        @csrf
        <x-field name="email" label="E-posta adresi" type="email" required autofocus autocomplete="email" />
        <x-button class="w-full">Bağlantı gönder</x-button>
    </form>

    <x-slot:footer>
        <a href="{{ route($area->loginRoute()) }}" class="font-medium hover:underline">Girişe dön</a>
    </x-slot:footer>
</x-layouts.auth>
