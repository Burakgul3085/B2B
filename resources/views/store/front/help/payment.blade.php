{{-- Ödeme Seçenekleri: açık yöntemler ayarlardan; kartlar ve taksit kartları iyzico kapsamından --}}
@php
    use App\Domain\Store\Payments\BankTransfer;
    use App\Domain\Support\Money;
@endphp
<x-store.help-layout :key="$key" :c="$c" icon="percent">
    @if ($c['show_methods'])
        <div class="space-y-4">
            <div class="rounded-3xl border border-line p-6">
                <p class="flex items-center gap-2 font-display text-lg font-extrabold"><x-icon name="lock" class="size-5 text-puzzle-green" /> Kredi ve banka kartı</p>
                <p class="mt-1 text-sm text-muted">iyzico güvencesiyle, 3D Secure doğrulamalı ödeme. Kart bilgileriniz mağazamızda saklanmaz. Anlaşmalı kredi kartlarıyla taksit yapılabilir.</p>
                <div class="mt-5 rounded-2xl border border-line bg-[#fff] p-5 sm:p-6"><x-store.payment-marks /></div>
            </div>
            @if ($settings['transfer_enabled'] === '1')
                <div class="rounded-3xl border border-line p-6">
                    <p class="flex items-center gap-2 font-display text-lg font-extrabold"><x-icon name="building" class="size-5 text-puzzle-blue" /> Havale / EFT</p>
                    <p class="mt-1 text-sm text-muted">Siparişten sonra {{ $settings['transfer_days'] }} gün içinde, açıklamaya sipariş numaranızı yazarak ödeyin. Ödeme hesabımıza geçince siparişiniz hazırlanır.</p>
                    @if ($banks)
                        <ul class="mt-4 space-y-2.5">
                            @foreach ($banks as $bank)
                                <x-store.bank-account :account="$bank" class="bg-surface/60!" />
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif
            @if ($settings['cod_enabled'] === '1')
                <div class="rounded-3xl border border-line p-6">
                    <p class="flex items-center gap-2 font-display text-lg font-extrabold"><x-icon name="truck" class="size-5 text-puzzle-orange" /> Kapıda ödeme</p>
                    <p class="mt-1 text-sm text-muted">Yalnızca Eskişehir mağaza teslimatında.@if ($settings['cod_max']) En fazla {{ Money::format($settings['cod_max']) }} tutarındaki siparişlerde.@endif</p>
                </div>
            @endif
        </div>
    @endif
</x-store.help-layout>
