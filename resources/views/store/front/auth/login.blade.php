<x-layouts.store title="Giriş yap" :noindex="true">
    <div class="mx-auto grid max-w-5xl gap-8 px-4 py-10 sm:px-6 md:grid-cols-2">
        <section class="rounded-2xl border border-line p-6 sm:p-8">
            <h1 class="text-2xl font-bold tracking-tight">Giriş yap</h1>
            <p class="mt-1 text-sm text-muted">Siparişlerinizi ve adreslerinizi görmek için giriş yapın.</p>
            <form method="POST" action="{{ route('store.login.store') }}" class="mt-6 space-y-5" novalidate>
                @csrf
                <x-field name="email" label="E-posta" type="email" required autocomplete="username" autofocus />
                <div>
                    <x-field name="password" label="Parola" type="password" required autocomplete="current-password" />
                    <a href="{{ route('store.password.request') }}" class="mt-1.5 inline-block text-sm font-medium text-primary hover:underline">Parolamı unuttum</a>
                </div>
                <x-toggle name="remember" label="Beni hatırla" />
                <x-button class="w-full py-3!">Giriş yap</x-button>
            </form>
        </section>
        <section class="rounded-2xl bg-surface p-6 sm:p-8">
            <h2 class="text-xl font-bold tracking-tight">Yeni misiniz?</h2>
            <p class="mt-2 text-sm leading-relaxed text-slate-600">Üye olarak siparişlerinizi takip edebilir, adreslerinizi kaydedip sonraki alışverişlerinizi hızlandırabilirsiniz.</p>
            <x-button variant="secondary" class="mt-6 w-full py-3!" :href="route('store.register')">Üye ol</x-button>
            <p class="mt-6 text-xs text-muted">Bayimizseniz toptan siparişleriniz için <a href="{{ rtrim(config('app.url'), '/') }}/bayi/login" class="font-medium text-primary hover:underline">bayi girişini</a> kullanın; mağaza üyeliği bayi hesabından ayrıdır.</p>
        </section>
    </div>
</x-layouts.store>
