<x-store.auth-shell title="Parolamı unuttum" heading="Parolamı unuttum" subtitle="Üyelik e-postanızı yazın; parola sıfırlama bağlantısı gönderelim."
    panel-title="Endişelenmeyin." panel-text="Birkaç dakika içinde yeni parolanızı belirleyip hesabınıza dönebilirsiniz."
    :benefits="[['mail', 'E-postanızı yazın', 'Üyelikte kullandığınız adres.'], ['check-circle', 'Bağlantıya tıklayın', 'Size gönderdiğimiz e-postadaki bağlantı.'], ['lock', 'Yeni parolanızı belirleyin', 'En az 10 karakter; harf ve rakam.']]">
    <form method="POST" action="{{ route('store.password.email') }}" class="space-y-5">
        @csrf
        <x-store.bot-trap />
        <x-field name="email" label="E-posta" type="email" required autocomplete="email" autofocus placeholder="ornek@eposta.com" />
        <button class="flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-navy font-semibold text-white shadow-lg shadow-navy/20 transition hover:bg-puzzle-orange">
            Bağlantı gönder <x-icon name="arrow-right" class="size-4" />
        </button>
    </form>
    <a href="{{ route('store.login') }}" class="mt-6 inline-flex items-center gap-1.5 text-sm font-semibold text-navy-ink hover:underline">← Giriş sayfasına dön</a>
</x-store.auth-shell>
