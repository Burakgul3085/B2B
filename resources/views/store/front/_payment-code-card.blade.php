{{--
    Arama kutusuna yazılan ödeme kodunun kartı. Tutar ve açıklama yönetimin oluşturduğu kayıttandır; müşteri yalnızca
    kodu sepete ekler. Kullanılamayan kodda (kullanılmış, iptal, süresi dolmuş) sepete ekleme yerine iletişim yönlendirmesi çıkar.
--}}
@php
    use App\Domain\Store\PaymentCodes\PaymentCodeSettings;
    use App\Domain\Support\Money;

    $state = $code->state();
    $itemName = app(PaymentCodeSettings::class)->get('item_name');
@endphp
<section class="help-box relative isolate mt-5 overflow-clip rounded-3xl p-[2px] shadow-sm" aria-labelledby="odeme-kodu-karti">
    <div class="relative overflow-clip rounded-[calc(1.5rem-2px)] bg-white px-6 py-7 sm:px-8">
        <span class="help-box-shine pointer-events-none absolute inset-y-0 -left-1/3 -z-10 w-1/3" aria-hidden="true"></span>
        <div class="flex flex-col gap-6 sm:flex-row sm:items-center">
            <span class="flex size-16 shrink-0 items-center justify-center rounded-2xl bg-puzzle-orange-soft text-puzzle-orange ring-1 ring-puzzle-orange/20"><x-icon name="key" class="size-8" /></span>
            <div class="min-w-0 flex-1">
                <p class="text-[11px] font-extrabold tracking-[0.18em] text-puzzle-orange uppercase">{{ $itemName }} · Kod <span class="font-mono">{{ $code->display() }}</span></p>
                <h2 id="odeme-kodu-karti" class="mt-1 font-display text-2xl font-extrabold tracking-tight text-ink">{{ $code->title }}</h2>
                <p class="mt-1 text-sm text-muted">Tutar mağazamızca belirlendi, KDV dahildir. Kargo, sepetinizdeki diğer ürünlerle birlikte hesaplanır.</p>
            </div>
            <div class="flex flex-col items-start gap-3 sm:items-end">
                <p @class(['font-display text-3xl font-extrabold tabular-nums', 'text-ink' => $state === 'active', 'text-muted line-through' => $state !== 'active'])>{{ Money::format($code->amount) }}</p>
                @if ($state === 'active')
                    <form method="POST" action="{{ route('store.payment-code.add') }}">
                        @csrf
                        <x-store.bot-trap />
                        <input type="hidden" name="code" value="{{ $code->code }}">
                        <button class="inline-flex items-center gap-2 rounded-2xl bg-navy px-6 py-3 font-semibold text-white shadow-sm transition hover:bg-navy-2 active:scale-95"><x-icon name="cart" class="size-5" /> Sepete ekle</button>
                    </form>
                    @if ($code->expires_at)<p class="text-xs text-muted">{{ $code->expires_at->timezone('Europe/Istanbul')->format('d.m.Y') }} tarihine kadar geçerli</p>@endif
                @else
                    <p class="rounded-xl bg-danger-soft px-3 py-2 text-sm font-medium text-danger">
                        {{ match ($state) { 'used' => 'Bu kod kullanıldı.', 'cancelled' => 'Bu kod iptal edildi.', default => 'Bu kodun süresi doldu.' } }}
                        <a href="{{ route('store.contact') }}" class="underline">Bize ulaşın</a>
                    </p>
                @endif
            </div>
        </div>
        @error('payment_code')<p role="alert" class="mt-4 text-sm font-medium text-danger">{{ $message }}</p>@enderror
    </div>
</section>
