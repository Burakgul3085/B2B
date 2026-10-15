@php use App\Domain\Auth\Area; @endphp
<x-layouts.auth :area="$area" title="Giriş yap">
    <form method="POST" action="{{ route($area->value.'.login.store') }}" class="space-y-5" novalidate>
        @csrf
        @if ($errors->has('email') && ! $errors->has('password'))
            <div role="alert" class="rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $errors->first('email') }}</div>
        @endif

        <div>
            <label for="email" class="mb-1.5 block text-sm font-medium">E-posta adresi</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="field-input">
        </div>

        <div>
            <div class="mb-1.5 flex items-center justify-between">
                <label for="password" class="block text-sm font-medium">Parola</label>
                <a href="{{ route($area->value.'.password.request') }}" class="text-sm font-medium text-primary hover:underline">Parolamı unuttum</a>
            </div>
            <input id="password" name="password" type="password" required autocomplete="current-password" class="field-input">
            @error('password')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@enderror
        </div>

        <x-button class="w-full">Giriş yap</x-button>
    </form>

    @if ($area !== Area::Platform)
        <x-slot:footer>
            @if ($area === Area::Business)
                Bayi misiniz? <a href="{{ route('dealer.login') }}" class="font-medium text-primary hover:underline">Bayi girişi</a>
            @else
                Toptancı mısınız? <a href="{{ route('business.login') }}" class="font-medium text-primary hover:underline">Toptancı girişi</a>
            @endif
        </x-slot:footer>
    @endif
</x-layouts.auth>
