<x-layouts.auth :area="$area" title="E-posta değişikliğini onaylayın">
    <p class="mb-5 text-sm text-muted">{{ $area->label() }} hesabınızın giriş e-postası şu adres olarak değiştirilecek:</p>
    <p class="mb-6 rounded-lg border border-line bg-slate-50 px-4 py-3 text-center font-medium">{{ $newEmail }}</p>

    <form method="POST" action="{{ route($area->value.'.email.confirm') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-button class="w-full">Değişikliği onayla</x-button>
    </form>
    <p class="mt-4 text-center text-xs text-muted">Bu talebi siz yapmadıysanız bu sayfayı kapatın; e-postanız değişmez.</p>
</x-layouts.auth>
