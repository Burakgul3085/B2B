{{--
    Bayi sipariş ekranı: onaylanan siparişte ödeme paneli (kart, havale bilgileri, teslimde ödeme) ve ödeme durumu.
    Sonra öde siparişinde son ödeme günü ve kalan tutar gösterilir; bayi dilediği an kartla veya havaleyle öder.
    Değişkenler: $order, $payable, $paymentMethods, $accounts, $dueAmount (ödenecek kalan tutar)
--}}
@php use App\Domain\Support\Money; use App\Domain\Support\Payments\Iban; use App\Models\Order; @endphp

@if (request('odeme') === 'sonuc')
    @if ($order->payment_status === 'paid')
        <div role="status" class="mb-5 flex items-start gap-3 rounded-xl border border-green-200 bg-success-soft px-4 py-3 text-sm text-green-900">
            <x-icon name="check-circle" class="mt-0.5 size-4 shrink-0" /><p>Ödemeniz alındı, teşekkür ederiz. Siparişiniz hazırlanacak.</p>
        </div>
    @elseif ($order->awaitingPayment() && ! in_array($order->status, ['rejected', 'cancelled'], true))
        {{-- Ödeme dönüşünden kalan bağlantı sonradan açılırsa (ör. iptal ve iade sonrası) yanlış uyarı gösterilmez. --}}
        <div role="alert" class="mb-5 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">Ödeme alınamadı. Kart bilgilerinizi kontrol edip yeniden deneyebilir veya havale ile ödeyebilirsiniz.</div>
    @endif
@endif
@error('payment')<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror

@if ($payable)
    @php
        $dueAmount ??= (string) $order->grand_total;
        $whole = bccomp($dueAmount, (string) $order->grand_total, 2) === 0;
        $days = $order->payment_due_on ? (int) now('Europe/Istanbul')->startOfDay()->diffInDays($order->payment_due_on, false) : null;
    @endphp
    <section class="mb-6 overflow-hidden rounded-2xl border-2 border-primary/30 bg-white shadow-xs print:hidden" aria-labelledby="odeme-baslik">
        <div class="flex flex-wrap items-center justify-between gap-4 bg-primary-soft/50 px-5 py-4 sm:px-6">
            <div>
                @if ($order->isPayLater())
                    <h2 id="odeme-baslik" class="text-base font-semibold text-ink">{{ $order->paymentMethodLabel() }}</h2>
                    @if ($order->payment_due_on)
                        <p @class(['text-sm', 'font-semibold text-red-700' => $days < 0, 'text-muted' => $days >= 0])>Son ödeme günü <b>{{ $order->payment_due_on->format('d.m.Y') }}</b> · {{ $days < 0 ? abs($days).' gün gecikti' : ($days === 0 ? 'bugün' : $days.' gün kaldı') }}</p>
                    @else
                        <p class="text-sm text-muted">Son ödeme günü teslimatla belli olur{{ $order->payment_term_days ? ': teslimden '.$order->payment_term_days.' gün sonra' : ' (teslimatta ödenir)' }}. Dilerseniz şimdi de ödeyebilirsiniz.</p>
                    @endif
                @else
                    <h2 id="odeme-baslik" class="text-base font-semibold text-ink">Siparişiniz onaylandı, ödemenizi bekliyoruz</h2>
                    <p class="text-sm text-muted">Yalnızca onaylanan tutarı ödersiniz. Siparişiniz ödemeniz alındıktan sonra hazırlanır.</p>
                @endif
            </div>
            <div class="text-right">
                <p class="text-xs text-muted">{{ $whole ? 'Ödenecek tutar' : 'Kalan tutar' }}</p>
                <p class="text-3xl font-bold tabular-nums text-ink">{{ Money::format($dueAmount) }}</p>
                @if ($order->requested_total !== null && bccomp((string) $order->requested_total, (string) $order->grand_total, 2) !== 0)
                    <p class="text-xs text-muted">Sipariş ettiğiniz: <span class="line-through">{{ Money::format($order->requested_total) }}</span></p>
                @endif
            </div>
        </div>

        <div class="space-y-5 px-5 py-5 sm:px-6">
            @if ($order->payments->where('driver', 'iyzico')->where('status', 'initialized')->where('created_at', '>', now()->subMinutes(30))->isNotEmpty())
                <div class="flex items-start gap-3 rounded-xl bg-warning-soft px-4 py-3 text-sm text-amber-900" role="status" x-data="paymentWatcher(@alpine(route('dealer.orders.payment-status', $order)))">
                    <span class="mt-0.5 size-4 shrink-0 animate-spin rounded-full border-2 border-amber-400 border-t-transparent" aria-hidden="true"></span>
                    <p>Açık bir kart ödemeniz var; sonucu kontrol ediliyor. Ödemeyi tamamladıysanız <b>tekrar ödeme yapmayın</b>, bu ekran kendiliğinden yenilenir.</p>
                </div>
            @endif
            @if ($order->payment_method === 'card' || ($order->isPayLater() && isset($paymentMethods['card']) && $whole))
                <form method="POST" action="{{ route('dealer.orders.pay', $order) }}" class="flex flex-wrap items-center gap-3" x-data="{ sending: false }" @submit="sending = true">
                    @csrf
                    <x-button icon="lock" x-bind:disabled="sending"><span x-text="sending ? 'Yönlendiriliyor…' : 'Kartla öde'">Kartla öde</span></x-button>
                    <p class="text-xs text-muted">iyzico'nun güvenli ödeme sayfasına yönlendirilirsiniz; kart bilgileriniz bize gelmez.</p>
                </form>
            @endif
            @if ($order->payment_method === 'transfer' || ($order->isPayLater() && $accounts !== []))
                <div @class(['border-t border-line pt-4' => $order->isPayLater() && isset($paymentMethods['card']) && $whole])>
                    <p class="text-sm font-medium">Havale / EFT bilgileri</p>
                    <p class="mt-1 text-sm text-muted">Açıklamaya <b class="font-mono text-ink">{{ $order->order_no }}</b> yazarak aşağıdaki hesaplardan birine <b class="text-ink">{{ Money::format($dueAmount) }}</b> gönderin.{{ $order->isPayLater() ? '' : ' Ödemeniz hesabımıza geçince siparişiniz hazırlanır.' }}</p>
                    <ul class="mt-3 grid gap-3 sm:grid-cols-2">
                        @foreach ($accounts as $a)
                            <li class="flex items-center gap-3 rounded-xl border border-line p-3">
                                <span class="flex h-10 w-24 shrink-0 items-center justify-center rounded-lg bg-[#fff] px-2 ring-1 ring-line">
                                    @if ($a['logo'])<img src="{{ $a['logo'] }}" alt="{{ $a['bank'] }}" class="max-h-6 max-w-full object-contain">@else<span class="text-xs font-bold text-muted">{{ mb_substr($a['bank'], 0, 2) }}</span>@endif
                                </span>
                                <span class="min-w-0 text-sm">
                                    <span class="block font-semibold">{{ $a['bank'] }}</span>
                                    <span class="block text-muted">{{ $a['holder'] }}</span>
                                    <span class="block font-mono text-xs break-all">{{ Iban::format($a['iban']) }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                    @if ($accounts === [])<p class="mt-2 text-sm text-danger">Hesap bilgisi şu anda gösterilemiyor; lütfen toptancınızla iletişime geçin.</p>@endif
                </div>
            @endif
            @if ($order->isPayLater() && ! $order->payment_due_on)
                <p class="text-sm text-muted">Teslimatta <b class="text-ink">nakit veya kartla (POS)</b> da ödeyebilirsiniz.</p>
            @elseif ($order->payment_method === 'on_delivery')
                <p class="text-sm">Ödemeyi teslimatta <b>nakit veya kartla (POS)</b> yapacaksınız. Teslimatta {{ Money::format($order->grand_total) }} hazır bulundurun.</p>
            @endif

            @if (count($paymentMethods) > 1)
                <form method="POST" action="{{ route('dealer.orders.payment-method', $order) }}" class="flex flex-wrap items-end gap-2 border-t border-line pt-4">
                    @csrf
                    <x-field name="payment_method" label="Ödeme yöntemini değiştir" :options="$paymentMethods" :value="$order->payment_method" class="min-w-56" />
                    <x-button variant="secondary" size="sm">Değiştir</x-button>
                </form>
            @endif
        </div>
    </section>
@elseif ($order->payment_status === 'paid')
    <div class="mb-6 flex items-start gap-3 rounded-xl border border-green-200 bg-success-soft px-4 py-3 text-sm text-green-900">
        <x-icon name="check-circle" class="mt-0.5 size-4 shrink-0" />
        <p><b>Ödendi:</b> {{ Money::format($order->grand_total) }} · {{ $order->payments->firstWhere('status', 'succeeded')?->label() ?? $order->paymentMethodLabel() }}
            @if ($order->paid_at) · {{ $order->paid_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}@endif</p>
    </div>
@elseif ($order->payment_status === 'refunded')
    <div class="mb-6 rounded-xl border border-line bg-surface px-4 py-3 text-sm">Kart ödemeniz sipariş iptal edildiği için iade edildi. İadenin kartınıza yansıması bankanıza göre birkaç gün sürebilir.</div>
@endif
