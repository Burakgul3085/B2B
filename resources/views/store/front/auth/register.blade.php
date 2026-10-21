<x-layouts.store title="Üye ol" :noindex="true">
    <div class="mx-auto max-w-xl px-4 py-10 sm:px-6">
        <section class="rounded-2xl border border-line p-6 sm:p-8">
            <h1 class="text-2xl font-bold tracking-tight">Üye ol</h1>
            <p class="mt-1 text-sm text-muted">Zaten üye misiniz? <a href="{{ route('store.login') }}" class="font-medium text-primary hover:underline">Giriş yapın</a></p>
            <form method="POST" action="{{ route('store.register.store') }}" class="mt-6 space-y-5" novalidate>
                @csrf
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-field name="first_name" label="Ad" required autocomplete="given-name" maxlength="60" />
                    <x-field name="last_name" label="Soyad" required autocomplete="family-name" maxlength="60" />
                </div>
                <x-field name="email" label="E-posta" type="email" required autocomplete="email" />
                <x-field name="phone" label="Cep telefonu (isteğe bağlı)" type="tel" autocomplete="tel" placeholder="05xx xxx xx xx" hint="Sipariş ve teslimat bilgilendirmesi için." />
                <x-field name="password" label="Parola" type="password" required autocomplete="new-password" hint="En az 10 karakter; harf ve rakam içermeli." />
                <x-field name="password_confirmation" label="Parola (tekrar)" type="password" required autocomplete="new-password" />
                <div class="space-y-3 rounded-xl bg-surface p-4">
                    <label class="flex items-start gap-3 text-sm">
                        <input type="checkbox" name="kvkk" value="1" @checked(old('kvkk')) class="mt-0.5 size-4 rounded border-line text-primary" required>
                        <span>Kişisel verilerimin işlenmesine ilişkin <span class="font-medium">aydınlatma metnini</span> okudum.<span class="text-danger"> *</span></span>
                    </label>
                    @error('kvkk')<p class="text-sm text-danger">{{ $message }}</p>@enderror
                    <label class="flex items-start gap-3 text-sm">
                        <input type="checkbox" name="marketing" value="1" @checked(old('marketing')) class="mt-0.5 size-4 rounded border-line text-primary">
                        <span>Kampanya ve indirimlerden e-posta ve SMS ile haberdar olmak istiyorum. (İsteğe bağlı)</span>
                    </label>
                </div>
                <x-button class="w-full py-3!">Üye ol</x-button>
            </form>
        </section>
    </div>
</x-layouts.store>
