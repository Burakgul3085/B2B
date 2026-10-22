<x-layouts.store title="Parolamı unuttum" :noindex="true">
    <div class="mx-auto max-w-md px-4 py-10 sm:px-6">
        <section class="rounded-2xl border border-line p-6 sm:p-8">
            <h1 class="text-2xl font-bold tracking-tight">Parolamı unuttum</h1>
            <p class="mt-1 text-sm text-muted">Üyelik e-postanızı yazın; parola sıfırlama bağlantısı gönderelim.</p>
            <form method="POST" action="{{ route('store.password.email') }}" class="mt-6 space-y-5">
                @csrf
                <x-store.bot-trap />
                <x-field name="email" label="E-posta" type="email" required autocomplete="email" autofocus />
                <x-button class="w-full py-3!">Bağlantı gönder</x-button>
            </form>
            <a href="{{ route('store.login') }}" class="mt-5 inline-block text-sm font-medium text-primary hover:underline">← Giriş sayfasına dön</a>
        </section>
    </div>
</x-layouts.store>
