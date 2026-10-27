{{-- Giriş doğrulama kodu: parola doğrulandıktan sonra e-postaya giden 4 haneli kod (App\Domain\Auth\LoginCode). --}}
@php use App\Domain\Auth\Area; @endphp
<x-layouts.auth :area="$area" title="E-postanızı kontrol edin" subtitle="Girişi tamamlamak için e-postanıza gönderdiğimiz 4 haneli kodu yazın.">
    <div class="mb-6 flex items-center gap-3 rounded-xl bg-primary-soft px-4 py-3 text-sm text-ink">
        <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-white text-primary shadow-xs"><x-icon name="mail" class="size-5" /></span>
        <p>Kod <span class="font-semibold">{{ $email }}</span> adresine gönderildi. Gelen kutunuzda yoksa gereksiz (spam) klasörüne bakın.</p>
    </div>

    @if (session('status'))
        <div role="status" class="mb-5 flex items-start gap-2.5 rounded-lg border border-green-200 bg-success-soft px-4 py-3 text-sm text-green-900">
            <x-icon name="check-circle" class="mt-0.5 size-4 shrink-0" />{{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route($area->value.'.login.code.verify') }}" class="space-y-5" novalidate>
        @csrf
        <div>
            <label for="code" class="mb-1.5 block text-sm font-medium text-ink">Doğrulama kodu</label>
            <input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" required autofocus autocomplete="one-time-code"
                placeholder="••••" class="field-input py-4 text-center font-mono text-3xl font-bold tracking-[0.6em]" aria-describedby="code-help">
            @error('code')<p role="alert" class="mt-1.5 text-sm text-danger">{{ $message }}</p>@enderror
            <p id="code-help" class="mt-1.5 text-xs text-muted">Kod 10 dakika geçerlidir.</p>
        </div>

        <x-button @class([
            'w-full py-3! text-base! shadow-md',
            'bg-teal-700! hover:bg-teal-800!' => $area === Area::Business,
            'bg-violet-700! hover:bg-violet-800!' => $area === Area::StoreAdmin,
        ])>
            Doğrula ve giriş yap <x-icon name="arrow-right" class="size-4" />
        </x-button>
    </form>

    <div class="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-line pt-5 text-sm">
        <form method="POST" action="{{ route($area->value.'.login.code.resend') }}">
            @csrf
            <button class="font-medium text-primary hover:underline">Yeni kod gönder</button>
            @if ($resendIn > 0)<span class="text-muted">({{ $resendIn }} sn sonra)</span>@endif
        </form>
        <a href="{{ route($area->loginRoute()) }}" class="text-muted hover:text-ink">Farklı hesapla giriş yap</a>
    </div>
</x-layouts.auth>
