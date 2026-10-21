<x-layouts.auth :area="\App\Domain\Auth\Area::StoreAdmin" title="İki adımlı doğrulamayı kurun">
    <p class="mb-5 text-sm text-muted">
        @if ($required)
            Süper admin hesabı için iki adımlı doğrulama zorunludur.
        @endif
        Google Authenticator, Microsoft Authenticator veya benzeri bir uygulamayla QR kodu okutun, ardından uygulamadaki 6 haneli kodu girin.
    </p>

    <div class="mb-5 flex justify-center rounded-xl border border-line bg-white p-4">{!! $qr !!}</div>

    <details class="mb-5 text-sm">
        <summary class="cursor-pointer text-muted">QR kodu okutamıyor musunuz?</summary>
        <p class="mt-2 font-mono text-xs break-all text-ink">{{ $secret }}</p>
    </details>

    <form method="POST" action="{{ route('store_admin.two-factor.confirm') }}" class="space-y-5">
        @csrf
        <x-field name="code" label="Doğrulama kodu" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus />
        <x-button class="w-full bg-violet-700! hover:bg-violet-800!">Doğrula ve etkinleştir</x-button>
    </form>

    <div class="mt-4 flex justify-center gap-4 text-sm">
        @unless ($required)
            <a href="{{ route('store_admin.account') }}" class="text-muted hover:underline">Vazgeç</a>
        @endunless
        <form method="POST" action="{{ route('store_admin.logout') }}">
            @csrf
            <button class="text-muted hover:underline">Çıkış yap</button>
        </form>
    </div>
</x-layouts.auth>
