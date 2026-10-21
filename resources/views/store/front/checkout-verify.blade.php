@php use App\Domain\Support\Money; @endphp
<x-layouts.store title="Sipariş doğrulama" :noindex="true">
    <div class="mx-auto max-w-md px-4 py-10 sm:px-6">
        <section class="rounded-2xl border border-line p-6 sm:p-8">
            <h1 class="text-2xl font-bold tracking-tight">Telefonunuzu doğrulayın</h1>
            <p class="mt-2 text-sm leading-relaxed text-slate-600"><span class="font-semibold text-ink">{{ $phone }}</span> numarasına 6 haneli bir kod gönderdik. Kodu girince kapıda ödemeli siparişiniz ({{ Money::format($total) }}) oluşturulacak.</p>
            @if ($smsLogged)
                <p class="mt-3 rounded-lg bg-warning-soft px-3 py-2 text-xs text-amber-900">Geliştirme modu: SMS gönderilmedi, kod uygulama günlüğüne (storage/logs/laravel.log) yazıldı.</p>
            @endif
            <form method="POST" action="{{ route('store.checkout.verify.store') }}" class="mt-6 space-y-5">
                @csrf
                <x-field name="code" label="Doğrulama kodu" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus />
                <x-button class="w-full py-3!">Siparişi tamamla</x-button>
            </form>
            <div class="mt-5 flex justify-between text-sm">
                <a href="{{ route('store.checkout') }}" class="text-muted hover:text-primary">← Bilgileri düzenle</a>
                <form method="POST" action="{{ route('store.checkout.verify.resend') }}">@csrf<button class="font-medium text-primary hover:underline">Kodu yeniden gönder</button></form>
            </div>
        </section>
    </div>
</x-layouts.store>
