<x-store.auth-shell title="Giriş yap" heading="Giriş yap" subtitle="Siparişlerinizi ve adreslerinizi görmek için giriş yapın."
    panel-title="Tekrar hoş geldiniz!" panel-text="Hesabınıza giriş yapın, kaldığınız yerden alışverişe devam edin.">
    <form method="POST" action="{{ route('store.login.store') }}" class="space-y-5" novalidate>
        @csrf
        <x-store.bot-trap />
        <x-field name="email" label="E-posta" type="email" required autocomplete="username" autofocus placeholder="ornek@eposta.com" />
        <div class="relative" x-data="passwordReveal">
            <x-field name="password" label="Parola" type="password" required autocomplete="current-password" />
            <button type="button" class="password-eye" @click="toggle()" :aria-label="shown ? 'Parolayı gizle' : 'Parolayı göster'" aria-label="Parolayı göster">
                <x-icon name="eye" class="size-5" x-show="! shown" /><x-icon name="eye-off" class="size-5" x-show="shown" x-cloak />
            </button>
        </div>
        <div class="flex items-center justify-between gap-3">
            <x-toggle name="remember" label="Beni hatırla" />
            <a href="{{ route('store.password.request') }}" class="text-sm font-semibold text-navy-ink hover:underline">Parolamı unuttum</a>
        </div>
        <button class="flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-navy font-semibold text-white shadow-lg shadow-navy/20 transition hover:bg-puzzle-orange">
            Giriş yap <x-icon name="arrow-right" class="size-4" />
        </button>
    </form>

    <div class="my-8 flex items-center gap-4 text-xs font-semibold tracking-wider text-muted uppercase"><span class="h-px flex-1 bg-line"></span>Yeni misiniz?<span class="h-px flex-1 bg-line"></span></div>
    <p class="text-sm leading-relaxed text-slate-600">Üye olarak siparişlerinizi takip edebilir, adreslerinizi kaydedip sonraki alışverişlerinizi hızlandırabilirsiniz.</p>
    <a href="{{ route('store.register') }}" class="mt-4 flex h-12 w-full items-center justify-center rounded-xl border-2 border-navy/15 font-semibold text-navy-ink transition hover:border-navy hover:bg-navy hover:text-white">Üye ol</a>
    <p class="mt-6 rounded-xl bg-surface px-4 py-3 text-xs leading-relaxed text-muted">Bayimizseniz toptan siparişleriniz için <a href="{{ rtrim(config('app.url'), '/') }}/bayi/login" class="font-semibold text-navy-ink hover:underline">bayi girişini</a> kullanın; mağaza üyeliği bayi hesabından ayrıdır.</p>
</x-store.auth-shell>
