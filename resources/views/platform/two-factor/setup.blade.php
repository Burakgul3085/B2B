<x-layouts.auth :area="\App\Domain\Auth\Area::Platform" title="İki adımlı doğrulamayı kurun">
    <p class="mb-5 text-sm text-muted">Platform paneli için iki adımlı doğrulama zorunludur. Google Authenticator, Microsoft Authenticator veya benzeri bir uygulamayla QR kodu okutun, ardından uygulamadaki 6 haneli kodu girin.</p>

    <div class="mb-5 flex justify-center rounded-xl border border-line bg-white p-4">{!! $qr !!}</div>

    <details class="mb-5 text-sm">
        <summary class="cursor-pointer text-muted">QR kodu okutamıyor musunuz?</summary>
        <p class="mt-2 font-mono text-xs break-all text-ink">{{ $secret }}</p>
    </details>

    <form method="POST" action="{{ route('platform.two-factor.confirm') }}" class="space-y-5">
        @csrf
        <x-field name="code" label="Doğrulama kodu" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus />
        <x-button class="w-full">Doğrula ve etkinleştir</x-button>
    </form>
</x-layouts.auth>
