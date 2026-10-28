@php use App\Domain\Support\Money; @endphp
<x-layouts.store title="Sepetim" :noindex="true">
    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">Sepetim</h1>

        @if ($summary->isEmpty())
            <div class="mt-8 rounded-2xl border border-dashed border-line px-6 py-16 text-center">
                <x-icon name="cart" class="mx-auto size-10 text-slate-300" />
                <p class="mt-4 font-medium">Sepetiniz boş</p>
                <x-button class="mt-6" :href="route('store.products')">Alışverişe başla</x-button>
            </div>
        @else
            @if ($printNotice = app(\App\Domain\Store\Print\PrintSettings::class)->minimumNotice($summary))
                <p class="mt-6 rounded-xl border border-amber-200 bg-warning-soft px-4 py-3 text-sm text-amber-900">{{ $printNotice }} Kopya ekleyebilir veya başka belge yükleyebilirsiniz.</p>
            @endif
            @if ($summary->hasIssues())
                <p class="mt-6 rounded-xl border border-amber-200 bg-warning-soft px-4 py-3 text-sm text-amber-900">Sepetinizdeki bazı satırlar şu anda sipariş edilemiyor. İşaretli satırları düzenleyin; bu satırlar toplama dahil edilmez.</p>
            @endif

            <div class="mt-6 grid gap-8 lg:grid-cols-3">
                <ul class="h-fit divide-y divide-line rounded-2xl border border-line lg:col-span-2">
                    @foreach ($summary->lines as $line)
                        @php $url = route('store.product', $line->product->slug); @endphp
                        <li @class(['flex gap-4 p-4 sm:p-5', 'bg-danger-soft/30' => $line->issue])>
                            <a href="{{ $url }}" class="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-line bg-[#fff] p-1 sm:size-24">
                                @if ($line->product->primaryImage)
                                    <img src="{{ route('store.media.product', ['storeImage' => $line->product->primaryImage, 'w' => 320]) }}" alt="" class="max-h-full max-w-full object-contain mix-blend-multiply">
                                @else
                                    <x-icon name="image" class="size-6 text-slate-300" />
                                @endif
                            </a>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap justify-between gap-2">
                                    <div class="min-w-0">
                                        <a href="{{ $url }}" class="font-medium hover:text-primary">{{ $line->product->name }}</a>
                                        @if ($line->variant->option_value)<p class="text-sm text-muted">{{ $line->product->variant_name }}: {{ $line->variant->option_value }}</p>@endif
                                        <p class="text-xs text-muted">{{ Money::format($line->unitPrice()) }} / adet</p>
                                    </div>
                                    <p @class(["font-semibold tabular-nums", "text-muted line-through" => $line->issue])>{{ Money::format($line->total()) }}</p>
                                </div>
                                @if ($line->issue === 'unavailable')
                                    <p class="mt-2 text-sm font-medium text-danger">Bu ürün şu anda satışta değil veya tükendi.</p>
                                @elseif ($line->issue === 'insufficient')
                                    <p class="mt-2 text-sm font-medium text-danger">Stokta yalnızca {{ $line->available }} adet var; adedi azaltın.</p>
                                @endif
                                <div class="mt-3 flex flex-wrap items-center gap-4">
                                    @unless ($line->issue === 'unavailable')
                                        <form method="POST" action="{{ route('store.cart.update', $line->item->id) }}" class="flex items-center gap-2">
                                            @csrf @method('PUT')
                                            <label class="sr-only" for="q{{ $line->item->id }}">Adet</label>
                                            <select id="q{{ $line->item->id }}" name="quantity" class="field-input w-auto py-1.5" data-autosubmit>
                                                @foreach (range(1, max(1, min(20, max($line->available, $line->quantity())))) as $n)
                                                    <option value="{{ $n }}" @selected($n === $line->quantity())>{{ $n }}</option>
                                                @endforeach
                                            </select>
                                            <noscript><x-button size="sm" variant="secondary">Güncelle</x-button></noscript>
                                        </form>
                                    @endunless
                                    <form method="POST" action="{{ route('store.cart.remove', $line->item->id) }}">@csrf @method('DELETE')<button class="text-sm text-muted hover:text-danger">Kaldır</button></form>
                                </div>
                            </div>
                        </li>
                    @endforeach
                    @foreach ($summary->prints as $line)
                        <li @class(['flex gap-4 p-4 sm:p-5', 'bg-danger-soft/30' => $line->issue])>
                            <a href="{{ route('store.print') }}" class="flex size-20 shrink-0 items-center justify-center rounded-xl border border-line bg-puzzle-blue/5 text-puzzle-blue sm:size-24"><x-icon name="printer" class="size-7" /></a>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="font-medium break-all">{{ $line->name() }}</p>
                                        <p class="text-sm text-muted">{{ $line->job->summary() }}</p>
                                        @if ($line->job->options['note'] ?? null)<p class="text-xs text-muted">Not: {{ $line->job->options['note'] }}</p>@endif
                                    </div>
                                    <p @class(["font-semibold tabular-nums", "text-muted line-through" => $line->issue])>{{ Money::format($line->total()) }}</p>
                                </div>
                                @if ($line->issue)
                                    <p class="mt-2 text-sm font-medium text-danger">Bu baskı şu anda sipariş edilemiyor. Kaldırıp belgeyi yeniden yükleyin.</p>
                                @endif
                                <div class="mt-3">
                                    <form method="POST" action="{{ route('store.cart.remove', $line->item->id) }}">@csrf @method('DELETE')<button class="text-sm text-muted hover:text-danger">Kaldır</button></form>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>

                <aside class="h-fit rounded-2xl border border-line p-6 lg:sticky lg:top-40">
                    <h2 class="text-lg font-semibold">Sipariş özeti</h2>
                    <dl class="mt-4 space-y-2 text-sm">
                        @if ($summary->lines)<div class="flex justify-between"><dt class="text-muted">Ürünler ({{ collect($summary->orderable())->sum(fn ($l) => $l->quantity()) }} adet)</dt><dd class="tabular-nums">{{ Money::format($summary->productsSubtotal()) }}</dd></div>@endif
                        @if ($summary->prints)<div class="flex justify-between"><dt class="text-muted">Fotokopi ({{ count($summary->printsOrderable()) }} belge)</dt><dd class="tabular-nums">{{ Money::format($summary->printsSubtotal()) }}</dd></div>@endif
                        @if ($discount && bccomp($discount->amount, '0', 2) > 0)
                            <div class="flex justify-between text-green-800"><dt>{{ $discount->label() }}</dt><dd class="tabular-nums">−{{ Money::format($discount->amount) }}</dd></div>
                        @endif
                        <div class="flex justify-between"><dt class="text-muted">Teslimat</dt><dd @class(['text-muted' => ! $discount?->freeShipping, 'text-green-800' => $discount?->freeShipping])>{{ $discount?->freeShipping ? 'Ücretsiz ('.$discount->label().')' : 'Ödeme adımında hesaplanır' }}</dd></div>
                        @if (! $discount)
                            @foreach ($summary->vatBreakdown() as $rate => $vat)
                                <div class="flex justify-between text-xs"><dt class="text-muted">Tutara dahil KDV (%{{ $rate }})</dt><dd class="text-muted tabular-nums">{{ Money::format($vat) }}</dd></div>
                            @endforeach
                        @else
                            <p class="text-xs text-muted">Fiyatlara KDV dahildir; KDV indirimli tutar üzerinden hesaplanır.</p>
                        @endif
                    </dl>
                    <div class="mt-4 flex justify-between border-t border-line pt-4 text-base font-bold"><span>Toplam</span><span class="tabular-nums">{{ Money::format(bcsub($summary->subtotal(), $discount?->amount ?? '0.00', 2)) }}</span></div>

                    @if ($next)
                        <div class="mt-4 flex items-start gap-2.5 rounded-xl bg-amber-50 px-3.5 py-3 text-sm text-amber-900 ring-1 ring-amber-200">
                            <x-icon name="percent" class="mt-0.5 size-4 shrink-0" />
                            <p><strong class="tabular-nums">{{ Money::format($next['missing']) }}</strong> daha {{ $next['discount']->isScoped() ? 'kampanyalı ürün' : 'ürün' }} ekleyin,
                                <strong>{{ $next['discount']->name }}</strong> kampanyasından yararlanın: {{ $next['discount']->headline() }}.</p>
                        </div>
                    @endif

                    {{-- Kupon: tek indirim uygulanır; girilen kupon kampanyanın yerine geçer. --}}
                    <div class="mt-4 border-t border-line pt-4">
                        @if ($cart?->coupon_code)
                            <div class="flex items-center justify-between gap-2 text-sm">
                                <span>Kupon: <span class="font-mono font-semibold">{{ $cart->coupon_code }}</span></span>
                                <form method="POST" action="{{ route('store.cart.coupon.remove') }}">@csrf @method('DELETE')<button class="text-muted hover:text-danger">Kaldır</button></form>
                            </div>
                            @if ($couponError)<p class="mt-1 text-xs text-danger">{{ $couponError }}</p>@endif
                        @else
                            <form method="POST" action="{{ route('store.cart.coupon') }}" class="flex gap-2">
                                @csrf
                                <label for="kupon" class="sr-only">Kupon kodu</label>
                                <input id="kupon" name="code" value="{{ old('code') }}" maxlength="40" placeholder="Kupon kodu" autocomplete="off" class="field-input min-w-0 flex-1 uppercase">
                                <button class="rounded-lg border border-line px-4 text-sm font-medium hover:bg-slate-50">Uygula</button>
                            </form>
                            @error('code')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                            @if ($discount)<p class="mt-1 text-xs text-muted">Kupon girerseniz kampanyanın yerine kupon uygulanır.</p>@endif
                        @endif
                    </div>
                    @if ($summary->hasIssues())
                        <button type="button" disabled class="mt-6 w-full rounded-xl bg-primary px-6 py-3 font-semibold text-white opacity-50">Siparişi tamamla</button>
                        <p class="mt-2 text-center text-xs text-danger">Devam etmek için işaretli satırları düzenleyin.</p>
                    @else
                        <a href="{{ route('store.checkout') }}" class="mt-6 block w-full rounded-xl bg-primary px-6 py-3 text-center font-semibold text-white transition hover:bg-primary-hover">Siparişi tamamla</a>
                    @endif
                    <a href="{{ route('store.products') }}" class="mt-4 block text-center text-sm font-medium text-primary hover:underline">Alışverişe devam et</a>
                </aside>
            </div>
        @endif
    </div>
</x-layouts.store>
