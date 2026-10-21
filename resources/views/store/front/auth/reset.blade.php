<x-layouts.store title="Yeni parola" :noindex="true">
    <div class="mx-auto max-w-md px-4 py-10 sm:px-6">
        <section class="rounded-2xl border border-line p-6 sm:p-8">
            <h1 class="text-2xl font-bold tracking-tight">Yeni parola belirleyin</h1>
            <form method="POST" action="{{ route('store.password.update') }}" class="mt-6 space-y-5">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <x-field name="password" label="Yeni parola" type="password" required autocomplete="new-password" hint="En az 10 karakter; harf ve rakam içermeli." />
                <x-field name="password_confirmation" label="Yeni parola (tekrar)" type="password" required autocomplete="new-password" />
                <x-button class="w-full py-3!">Parolayı kaydet</x-button>
            </form>
        </section>
    </div>
</x-layouts.store>
