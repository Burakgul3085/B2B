@php
    use App\Domain\Store\Storefront\HomeFeed;
    use App\Domain\Store\Storefront\Palette;

    $member = auth('customer')->user();
@endphp
<x-layouts.store title="Kampanyalar" description="Yapboz Kırtasiye'deki güncel kampanyalar, indirim kuponları ve ücretsiz kargo fırsatları." :canonical="route('store.campaigns')">
    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        @include('store.front._breadcrumbs', ['items' => [['Kampanyalar', null]]])

        <div class="mt-4">
            <h1 class="flex items-center gap-3 font-display text-2xl font-extrabold tracking-tight sm:text-3xl">
                Kampanyalar
                @if ($campaigns->isNotEmpty())<span class="rounded-full bg-primary-soft px-3 py-1 font-sans text-sm font-semibold text-primary tabular-nums">{{ $campaigns->count() }}</span>@endif
            </h1>
            <p class="mt-1.5 text-muted">Güncel indirimler ve kuponlar. Siparişte tek indirim uygulanır; sepetinize en avantajlısı kendiliğinden seçilir.</p>
        </div>

        @if ($campaigns->isEmpty())
            <div class="mt-8 rounded-2xl border border-dashed border-line px-6 py-16 text-center">
                <x-icon name="gift" class="mx-auto size-8 text-slate-300" />
                <p class="mt-3 font-medium">Şu anda yayında kampanya yok.</p>
                <p class="mt-1 text-sm text-muted">Yeni kampanyalardan haberdar olmak için e-bültenimize abone olabilirsiniz.</p>
                <a href="{{ route('store.products') }}" class="mt-5 inline-flex rounded-xl bg-navy px-5 py-2.5 text-sm font-semibold text-white hover:bg-puzzle-orange">Ürünlere göz atın</a>
            </div>
        @else
            <div class="mt-6 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($campaigns as $i => $campaign)
                    @php
                        $tone = Palette::classes(Palette::CYCLE[$i % count(Palette::CYCLE)]);
                        $daysLeft = $campaign->ends_at ? (int) ceil(now()->diffInHours($campaign->ends_at) / 24) : null;
                        $productsUrl = route('store.products', ['kampanya' => $campaign->id]);
                        $needsAccount = $campaign->audience !== 'all' && ! $member;
                    @endphp
                    <article class="group flex flex-col overflow-hidden rounded-3xl border border-line bg-white shadow-xs transition duration-300 hover:-translate-y-0.5 hover:shadow-lg" @if ($campaign->isCoupon()) x-data="copyCode(@alpine($campaign->code))" @endif>
                        @if ($campaign->image_path)
                            {{-- Görsel yüklendiği oranda, kırpılmadan ve dolgu eklenmeden gösterilir; kartın boyu görsele göre değişir. --}}
                            <img src="{{ route('store.media.discount', ['storeDiscount' => $campaign, 'w' => 960]) }}" alt="{{ $campaign->name }}" loading="lazy" class="block h-auto w-full">

                        @else
                            <div class="relative flex aspect-[3/2] items-center overflow-hidden px-7 text-white {{ $tone['solid'] }}">
                                <span class="absolute -right-8 -bottom-10 size-40 rounded-full bg-white/15" aria-hidden="true"></span>
                                <span class="absolute top-6 right-16 size-14 rounded-full bg-white/10" aria-hidden="true"></span>
                                <div class="relative">
                                    <p class="font-display text-5xl font-extrabold leading-none">{{ HomeFeed::percentLabel($campaign) }}</p>
                                    <p class="mt-2 text-sm font-semibold tracking-wide text-white/85 uppercase">{{ $campaign->type === 'free_shipping' ? 'teslimat bizden' : 'indirim' }}</p>
                                </div>
                            </div>
                        @endif

                        <div class="flex flex-1 flex-col gap-3 p-5 sm:p-6">
                            <div class="flex flex-wrap gap-1.5">
                                @if ($campaign->image_path)<span class="rounded-md bg-puzzle-orange px-2 py-0.5 text-[11px] font-bold text-white">{{ HomeFeed::percentLabel($campaign) }}{{ $campaign->type === 'free_shipping' ? '' : ' indirim' }}</span>@endif
                                @if ($campaign->audience === 'new_members')<span class="rounded-md bg-violet-100 px-2 py-0.5 text-[11px] font-bold text-violet-800">Yeni üyelere özel</span>
                                @elseif ($campaign->audience === 'members')<span class="rounded-md bg-sky-100 px-2 py-0.5 text-[11px] font-bold text-sky-800">Üyelere özel</span>@endif
                                @if ($campaign->first_order_only)<span class="rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-700">İlk siparişe</span>@endif
                                @if ($daysLeft !== null && $daysLeft <= 7)<span class="rounded-md bg-red-100 px-2 py-0.5 text-[11px] font-bold text-red-800">Son {{ max(1, $daysLeft) }} gün</span>@endif
                            </div>
                            <div>
                                <h2 class="font-display text-xl font-extrabold tracking-tight text-ink">{{ $campaign->name }}</h2>
                                <p class="mt-1 text-[15px] font-semibold text-primary">{{ $campaign->headline() }}</p>
                                @if ($campaign->description)<p class="mt-2 text-sm leading-relaxed text-muted">{{ $campaign->description }}</p>@endif
                            </div>
                            <p class="text-xs text-muted">
                                @if ($campaign->ends_at){{ $campaign->ends_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}'e kadar geçerli. @else Süresiz. @endif
                                @if ($campaign->type === 'percent' && $campaign->max_discount) En fazla {{ \App\Domain\Support\Money::format($campaign->max_discount) }} indirim. @endif
                                @if ($campaign->audience === 'new_members' && $campaign->new_member_days) Üyelikten sonraki {{ $campaign->new_member_days }} gün içinde, ilk siparişte. @endif
                            </p>

                            <div class="mt-auto flex flex-wrap items-center gap-2 border-t border-line pt-4">
                                @if ($campaign->isCoupon())
                                    <span class="rounded-lg border border-dashed border-navy/40 px-3 py-1.5 font-mono text-sm font-bold tracking-wider text-navy-ink">{{ $campaign->code }}</span>
                                    <button type="button" @click="copy" class="rounded-lg bg-navy px-3 py-1.5 text-sm font-semibold text-white transition hover:bg-puzzle-orange">
                                        <span x-show="! copied">Kodu kopyala</span><span x-show="copied" x-cloak>Kopyalandı ✓</span>
                                    </button>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-lg bg-green-50 px-3 py-1.5 text-xs font-semibold text-green-800"><x-icon name="check" class="size-3.5" /> Sepette kendiliğinden uygulanır</span>
                                @endif
                                @if ($needsAccount)
                                    <a href="{{ route('store.register') }}" class="ml-auto text-sm font-semibold text-primary hover:underline">Üye ol →</a>
                                @else
                                    <a href="{{ $productsUrl }}" class="ml-auto text-sm font-semibold text-primary hover:underline">{{ $campaign->isScoped() ? 'Kampanyalı ürünler' : 'Alışverişe başla' }} →</a>
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.store>
