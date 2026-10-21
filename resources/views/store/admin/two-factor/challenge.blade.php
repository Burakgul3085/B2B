<x-layouts.auth :area="\App\Domain\Auth\Area::StoreAdmin" title="Doğrulama kodu">
    <p class="mb-5 text-sm text-muted">Doğrulama uygulamanızdaki 6 haneli kodu girin.</p>

    <form method="POST" action="{{ route('store_admin.two-factor.verify') }}" class="space-y-5">
        @csrf
        <x-field name="code" label="Kod" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus />
        <x-button class="w-full bg-violet-700! hover:bg-violet-800!">Devam et</x-button>
    </form>

    <form method="POST" action="{{ route('store_admin.logout') }}" class="mt-4 text-center">
        @csrf
        <button class="text-sm text-muted hover:underline">Farklı hesapla giriş yap</button>
    </form>
</x-layouts.auth>
