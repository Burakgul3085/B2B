<x-store.auth-shell title="Yeni parola" heading="Yeni parola belirleyin" subtitle="Hesabınız için güçlü bir parola seçin."
    panel-title="Son adım." panel-text="Yeni parolanızı kaydettikten sonra giriş yapabilirsiniz."
    :benefits="[['lock', 'Güçlü parola', 'En az 10 karakter; harf ve rakam içermeli.'], ['shield', 'Güvenli bağlantı', 'Parolanız şifrelenerek saklanır.']]">
    <form method="POST" action="{{ route('store.password.update') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div class="relative" x-data="passwordReveal">
            <x-field name="password" label="Yeni parola" type="password" required autocomplete="new-password" hint="En az 10 karakter; harf ve rakam içermeli." />
            <button type="button" class="password-eye" @click="toggle()" :aria-label="shown ? 'Parolayı gizle' : 'Parolayı göster'" aria-label="Parolayı göster">
                <x-icon name="eye" class="size-5" x-show="! shown" /><x-icon name="eye-off" class="size-5" x-show="shown" x-cloak />
            </button>
        </div>
        <x-field name="password_confirmation" label="Yeni parola (tekrar)" type="password" required autocomplete="new-password" />
        <button class="flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-navy font-semibold text-white shadow-lg shadow-navy/20 transition hover:bg-puzzle-orange">
            Parolayı kaydet <x-icon name="arrow-right" class="size-4" />
        </button>
    </form>
</x-store.auth-shell>
