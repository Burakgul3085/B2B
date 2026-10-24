<x-store.auth-shell title="Üye ol" heading="Üye ol" :wide="true"
    panel-title="Aramıza hoş geldiniz!" panel-text="Birkaç bilgiyle üyeliğinizi oluşturun; alışverişleriniz daha hızlı ve kolay olsun.">
    <p class="-mt-6 mb-8 text-muted">Zaten üye misiniz? <a href="{{ route('store.login') }}" class="font-semibold text-navy-ink hover:underline">Giriş yapın</a></p>
    <form method="POST" action="{{ route('store.register.store') }}" class="space-y-5" novalidate>
        @csrf
        <x-store.bot-trap />
        <div class="grid gap-5 sm:grid-cols-2">
            <x-field name="first_name" label="Ad" required autocomplete="given-name" maxlength="60" />
            <x-field name="last_name" label="Soyad" required autocomplete="family-name" maxlength="60" />
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
            <x-field name="email" label="E-posta" type="email" required autocomplete="email" placeholder="ornek@eposta.com" />
            <x-field name="phone" label="Cep telefonu (isteğe bağlı)" type="tel" autocomplete="tel" placeholder="05xx xxx xx xx" hint="Sipariş ve teslimat bilgilendirmesi için." />
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
            <div class="relative" x-data="passwordReveal">
                <x-field name="password" label="Parola" type="password" required autocomplete="new-password" hint="En az 10 karakter; harf ve rakam içermeli." />
                <button type="button" class="password-eye" @click="toggle()" :aria-label="shown ? 'Parolayı gizle' : 'Parolayı göster'" aria-label="Parolayı göster">
                    <x-icon name="eye" class="size-5" x-show="! shown" /><x-icon name="eye-off" class="size-5" x-show="shown" x-cloak />
                </button>
            </div>
            <x-field name="password_confirmation" label="Parola (tekrar)" type="password" required autocomplete="new-password" />
        </div>
        <div class="space-y-3 rounded-2xl border border-line bg-surface/60 p-5">
            <label class="flex items-start gap-3 text-sm leading-relaxed">
                <input type="checkbox" name="kvkk" value="1" @checked(old('kvkk')) class="mt-0.5 size-5 shrink-0 rounded-md border-line text-navy" required>
                <span>Kişisel verilerimin işlenmesine ilişkin <a href="{{ route('store.legal', 'kvkk') }}" target="_blank" rel="noopener" class="font-semibold text-navy-ink underline">aydınlatma metnini</a> okudum.<span class="text-danger"> *</span></span>
            </label>
            @error('kvkk')<p class="text-sm text-danger">{{ $message }}</p>@enderror
            <label class="flex items-start gap-3 text-sm leading-relaxed">
                <input type="checkbox" name="marketing" value="1" @checked(old('marketing')) class="mt-0.5 size-5 shrink-0 rounded-md border-line text-navy">
                <span>Kampanya ve indirimlerden e-posta ve SMS ile haberdar olmak istiyorum. <span class="text-muted">(İsteğe bağlı)</span></span>
            </label>
        </div>
        <button class="flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-navy font-semibold text-white shadow-lg shadow-navy/20 transition hover:bg-puzzle-orange">
            Üye ol <x-icon name="arrow-right" class="size-4" />
        </button>
    </form>
</x-store.auth-shell>
