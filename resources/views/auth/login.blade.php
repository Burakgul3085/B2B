@php use App\Domain\Auth\Area; @endphp
<x-layouts.auth :area="$area" title="Giriş yap" :subtitle="match ($area) {
    Area::Dealer => 'Sipariş panelinize erişmek için hesabınızla giriş yapın.',
    Area::Business => 'İşletme panelinize erişmek için giriş yapın.',
    Area::Platform => 'Yönetici hesabınızla giriş yapın.',
}">
    <form method="POST" action="{{ route($area->value.'.login.store') }}" class="space-y-5" novalidate>
        @csrf
        @if ($errors->has('email') && ! $errors->has('password'))
            <div role="alert" class="flex items-start gap-2.5 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">
                <x-icon name="alert" class="mt-0.5 size-4 shrink-0" />{{ $errors->first('email') }}
            </div>
        @endif

        <div>
            <label for="email" class="mb-1.5 block text-sm font-medium text-ink">E-posta adresi</label>
            <div class="relative">
                <x-icon name="mail" class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-slate-400" />
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                    placeholder="ornek@firma.com" class="field-input py-3 pl-10">
            </div>
        </div>

        <div x-data="{ show: false }">
            <div class="mb-1.5 flex items-center justify-between">
                <label for="password" class="block text-sm font-medium text-ink">Parola</label>
                <a href="{{ route($area->value.'.password.request') }}" class="text-sm font-medium text-primary hover:underline">Parolamı unuttum</a>
            </div>
            <div class="relative">
                <x-icon name="lock" class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-slate-400" />
                <input id="password" name="password" type="password" :type="show ? 'text' : 'password'" required autocomplete="current-password"
                    placeholder="Parolanız" class="field-input py-3 pr-11 pl-10">
                <button type="button" @click="show = ! show" class="absolute top-1/2 right-2 -translate-y-1/2 rounded-md p-1.5 text-slate-400 hover:text-ink"
                    :aria-label="show ? 'Parolayı gizle' : 'Parolayı göster'" aria-label="Parolayı göster">
                    <x-icon name="eye" class="size-4" x-show="! show" />
                    <x-icon name="eye-off" class="size-4" x-show="show" x-cloak />
                </button>
            </div>
            @error('password')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@enderror
        </div>

        <x-button @class([
            'w-full py-3! text-base! shadow-md',
            'bg-teal-700! hover:bg-teal-800!' => $area === Area::Business,
            'bg-slate-900! hover:bg-slate-800!' => $area === Area::Platform,
        ])>
            Giriş yap <x-icon name="arrow-right" class="size-4" />
        </x-button>
    </form>

    @if ($area === Area::Dealer)
        <x-slot:footer>
            <div class="flex items-center gap-3">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary-soft text-primary"><x-icon name="users" class="size-5" /></span>
                <div class="min-w-0">
                    <p class="font-medium text-ink">Bayimiz olmak ister misiniz?</p>
                    <a href="{{ route('public.apply') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-primary hover:underline">Bayilik başvurusu yapın <x-icon name="arrow-right" class="size-3.5" /></a>
                </div>
            </div>
        </x-slot:footer>
    @endif
</x-layouts.auth>
