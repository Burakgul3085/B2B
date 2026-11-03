@php
    use App\Domain\Support\Money;

    $today = now('Europe/Istanbul')->startOfDay();
@endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Dealer" title="Kampanyalar">
    <x-page-header title="Kampanyalar" subtitle="Size açık kupon ve kampanyalar. Kampanya, koşulu tutan siparişe kendiliğinden uygulanır; kupon kodunu sepette girersiniz. Siparişte tek kupon veya kampanya geçerlidir.">
        <x-button variant="secondary" icon="arrow-right" :href="route('dealer.cart')">Sepete git</x-button>
    </x-page-header>

    @if ($rows->isEmpty())
        <x-card><x-empty-state icon="gift" title="Şu an size açık kampanya yok" text="Toptancınız yeni bir kupon veya kampanya başlattığında burada görünür." /></x-card>
    @else
        <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($rows as ['promotion' => $p, 'remaining' => $remaining])
                @php
                    $days = $p->ends_at ? (int) $today->diffInDays($p->ends_at->timezone('Europe/Istanbul')->startOfDay()) : null;
                    $names = collect($p->category_ids ?? [])->map(fn ($id) => $categories[$id] ?? null)->filter();
                @endphp
                <article class="flex flex-col rounded-2xl border border-line bg-white p-5 shadow-xs">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="inline-flex items-center gap-1.5 text-xs font-bold tracking-wide text-primary uppercase">
                                <x-icon name="gift" class="size-3.5" />{{ $p->isCoupon() ? 'Kupon' : 'Kampanya' }}
                            </p>
                            <h2 class="mt-1 text-base font-semibold text-ink">{{ $p->name }}</h2>
                        </div>
                        @if ($days !== null)
                            <span @class(['shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold', 'bg-danger-soft text-danger' => $days <= 3, 'bg-slate-100 text-slate-700' => $days > 3])>
                                {{ $days === 0 ? 'Bugün son gün' : $days.' gün kaldı' }}
                            </span>
                        @endif
                    </div>

                    <p class="mt-3 text-lg font-bold text-ink">{{ $p->summary() }}</p>
                    @if ($p->description)<p class="mt-1 text-sm text-slate-700">{{ $p->description }}</p>@endif

                    <dl class="mt-4 space-y-1.5 text-sm">
                        @if ($p->min_subtotal)
                            <div class="flex justify-between gap-3"><dt class="text-muted">En düşük tutar</dt><dd class="font-medium tabular-nums">{{ Money::format($p->min_subtotal) }} <span class="text-xs font-normal text-muted">KDV hariç</span></dd></div>
                        @endif
                        <div class="flex justify-between gap-3"><dt class="text-muted">Bitiş</dt><dd class="font-medium">{{ $p->ends_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i') ?? 'Süresiz' }}</dd></div>
                        @if ($remaining !== null)
                            <div class="flex justify-between gap-3"><dt class="text-muted">Kalan kullanım hakkınız</dt><dd class="font-medium tabular-nums">{{ $remaining }}</dd></div>
                        @endif
                        @if ($p->isScoped())
                            <div class="flex justify-between gap-3"><dt class="text-muted">Geçerli ürünler</dt><dd class="text-right font-medium">
                                {{ $names->implode(', ') }}{{ $names->isNotEmpty() && $p->product_ids ? ' ve ' : '' }}{{ $p->product_ids ? count($p->product_ids).' seçili ürün' : '' }}
                            </dd></div>
                        @endif
                    </dl>

                    <div class="mt-auto flex flex-wrap items-center gap-2 pt-5">
                        @if ($p->isCoupon())
                            <span class="rounded-lg border border-dashed border-primary/50 bg-primary-soft/40 px-3 py-1.5 font-mono text-sm font-semibold text-primary">{{ $p->code }}</span>
                            <form method="POST" action="{{ route('dealer.cart.promotion') }}">
                                @csrf
                                <input type="hidden" name="code" value="{{ $p->code }}">
                                <input type="hidden" name="to_cart" value="1">
                                <x-button size="sm">Sepete uygula</x-button>
                            </form>
                        @endif
                        @if ($names->isNotEmpty())
                            <a href="{{ route('dealer.catalog', ['category' => $names->keys()->all()]) }}" class="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline">Ürünleri gör <x-icon name="arrow-right" class="size-3.5" /></a>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</x-layouts.panel>
