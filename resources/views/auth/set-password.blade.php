<x-layouts.auth :area="$area" :title="$title">
    <p class="mb-5 text-sm text-muted">Parolanız en az 10 karakter olmalı, harf ve rakam içermelidir.</p>

    <form method="POST" action="{{ $action }}" class="space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-field name="password" label="Yeni parola" type="password" required autofocus autocomplete="new-password" />
        <x-field name="password_confirmation" label="Yeni parola (tekrar)" type="password" required autocomplete="new-password" />
        <x-button class="w-full">Parolayı kaydet</x-button>
    </form>
</x-layouts.auth>
