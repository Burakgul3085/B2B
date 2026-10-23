{{-- Kampanya ve kupon kartları: "Ana sayfada göster" işaretli, geçerli, kodlu kuponlar (en fazla 3). --}}
@if ($coupons->isNotEmpty())
    <section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8" aria-labelledby="kuponlar">
        @include('store.front.home._heading', ['block' => $block, 'eyebrow' => 'Fırsatlar', 'color' => 'orange'])
        <div class="grid gap-4 {{ [1 => 'md:grid-cols-1', 2 => 'md:grid-cols-2', 3 => 'md:grid-cols-3'][min(3, $coupons->count())] }}">
            @foreach ($coupons as $i => $coupon)
                @php $c = \App\Domain\Store\Storefront\Palette::classes(\App\Domain\Store\Storefront\Palette::CYCLE[$i % 4]); @endphp
                <article class="relative flex overflow-hidden rounded-3xl bg-navy text-white" x-data="copyCode(@alpine($coupon->code))">
                    <div class="flex w-32 shrink-0 flex-col items-center justify-center p-4 text-center sm:w-36 {{ $c['solid'] }}">
                        <span class="font-display text-3xl font-extrabold leading-none">{{ \App\Domain\Store\Storefront\HomeFeed::percentLabel($coupon) }}</span>
                        <span class="mt-1 text-xs font-semibold tracking-wide text-white/85 uppercase">{{ $coupon->type === 'free_shipping' ? 'teslimat' : 'indirim' }}</span>
                    </div>
                    {{-- bilet kesiği --}}
                    <span class="absolute top-1/2 left-32 size-6 -translate-x-1/2 -translate-y-1/2 rounded-full bg-white sm:left-36" aria-hidden="true"></span>
                    <div class="flex min-w-0 flex-1 flex-col gap-3 border-l-2 border-dashed border-white/25 p-5">
                        <div>
                            <p class="font-display text-lg font-bold">{{ $coupon->name }}</p>
                            <p class="mt-0.5 text-sm text-white/65">
                                @if ($coupon->min_subtotal){{ \App\Domain\Support\Money::format($coupon->min_subtotal) }} ve üzeri sepette @else Tüm sepetlerde @endif
                                @if ($coupon->first_order_only) · ilk siparişe özel @endif
                                @if ($coupon->ends_at) · {{ $coupon->ends_at->timezone('Europe/Istanbul')->format('d.m.Y') }}'e kadar @endif
                            </p>
                        </div>
                        <div class="mt-auto flex items-center gap-2">
                            <span class="rounded-lg border border-dashed border-white/40 px-3 py-1.5 font-mono text-sm font-bold tracking-wider">{{ $coupon->code }}</span>
                            <button type="button" @click="copy" class="rounded-lg bg-white px-3 py-1.5 text-sm font-semibold text-navy transition hover:bg-puzzle-sun">
                                <span x-show="! copied">Kodu kopyala</span><span x-show="copied" x-cloak>Kopyalandı ✓</span>
                            </button>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@endif
