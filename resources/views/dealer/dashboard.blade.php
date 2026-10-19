@php
    use App\Domain\Inventory\Quantity;
    use App\Domain\Support\Money;

    $dealer = auth('dealer')->user()->dealer;
    $balanceState = bccomp($kpis['balance'], '0', 2);
    $hasOverdue = bccomp($kpis['overdue'], '0', 2) > 0;
    $change = (float) $kpis['last_month_amount'] > 0
        ? round(((float) $kpis['month_amount'] - (float) $kpis['last_month_amount']) / (float) $kpis['last_month_amount'] * 100)
        : null;
    $requestsOn = $currentBusiness->moduleEnabled('product_requests');
@endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Dealer" title="Hesap Özeti">
    {{-- Kimlik şeridi --}}
    <section class="mb-6 overflow-hidden rounded-2xl border border-line bg-white shadow-xs">
        <div class="flex flex-wrap items-center gap-5 bg-linear-to-r from-primary-dark to-primary px-6 py-5 text-white">
            <span class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-white p-1.5 shadow-lg ring-4 ring-white/15">
                @if ($dealer->logo_path)
                    <img src="{{ route('dealer.logo') }}?v={{ $dealer->updated_at?->timestamp }}" alt="{{ $dealer->legal_name }} logosu" class="max-h-full max-w-full object-contain">
                @else
                    <span class="text-2xl font-bold text-primary">{{ mb_substr($dealer->legal_name, 0, 1) }}</span>
                @endif
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-sm text-white/70">Hesap özeti</p>
                <h1 class="truncate text-2xl font-bold tracking-tight">{{ $dealer->legal_name }}</h1>
                <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-white/80">
                    <span>Bayi kodu <b class="font-semibold text-white">{{ $relationship->dealer_code }}</b></span>
                    @if ($relationship->group)<span class="rounded-full bg-amber-400 px-2 py-0.5 text-xs font-bold text-primary-dark">{{ $relationship->group->name }}</span>@endif
                    <span>· Ödeme vadesi {{ $relationship->payment_term_days }} gün</span>
                    @if ($relationship->approved_at)<span>· {{ $relationship->approved_at->translatedFormat('F Y') }}'den beri bayimiz</span>@endif
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('dealer.account.statement') }}" class="inline-flex items-center gap-2 rounded-lg bg-white/10 px-4 py-2 text-sm font-semibold ring-1 ring-white/20 transition hover:bg-white/20"><x-icon name="list" class="size-4" />Cari hesap</a>
                <a href="{{ route('dealer.catalog') }}" class="inline-flex items-center gap-2 rounded-lg bg-amber-400 px-4 py-2 text-sm font-bold text-primary-dark shadow-sm transition hover:bg-amber-300"><x-icon name="package" class="size-4" />Sipariş ver</a>
            </div>
        </div>

        {{-- Temel göstergeler --}}
        <div class="grid divide-y divide-line sm:grid-cols-2 sm:divide-x lg:grid-cols-4 lg:divide-y-0">
            <a href="{{ route('dealer.orders.index', ['status' => 'open']) }}" class="group p-5 transition hover:bg-slate-50">
                <p class="flex items-center gap-2 text-sm font-medium text-muted"><span class="flex size-8 items-center justify-center rounded-lg bg-primary/10 text-primary"><x-icon name="refresh" class="size-4" /></span>Devam eden sipariş</p>
                <p class="mt-3 text-3xl font-bold text-ink tabular-nums">{{ $kpis['open_orders'] }}</p>
                <p class="mt-1 text-xs text-muted">{{ $kpis['pending_orders'] > 0 ? $kpis['pending_orders'].' sipariş onay bekliyor' : 'Onay bekleyen yok' }} <span class="text-primary group-hover:underline">· Siparişlerim</span></p>
            </a>
            <div class="p-5">
                <p class="flex items-center gap-2 text-sm font-medium text-muted"><span class="flex size-8 items-center justify-center rounded-lg bg-amber-100 text-amber-700"><x-icon name="cart" class="size-4" /></span>Bu ayki alımınız</p>
                <p class="mt-3 text-3xl font-bold text-ink tabular-nums">{{ Money::format($kpis['month_amount']) }}</p>
                <p class="mt-1 text-xs text-muted">
                    @if ($change !== null)
                        <span @class(['font-semibold', 'text-emerald-700' => $change >= 0, 'text-red-700' => $change < 0])>{{ $change >= 0 ? '▲' : '▼' }} %{{ abs($change) }}</span> geçen aya göre
                    @else
                        Onaylanan siparişler, KDV dahil
                    @endif
                </p>
            </div>
            <a href="{{ route('dealer.account.statement') }}" class="group p-5 transition hover:bg-slate-50">
                <p class="flex items-center gap-2 text-sm font-medium text-muted"><span @class(['flex size-8 items-center justify-center rounded-lg', 'bg-red-100 text-red-700' => $hasOverdue, 'bg-emerald-100 text-emerald-700' => ! $hasOverdue])><x-icon name="file" class="size-4" /></span>{{ $balanceState < 0 ? 'Alacağınız' : 'Cari borcunuz' }}</p>
                <p @class(['mt-3 text-3xl font-bold tabular-nums', 'text-ink' => $balanceState >= 0, 'text-emerald-700' => $balanceState < 0])>{{ Money::format(ltrim($kpis['balance'], '-')) }}</p>
                <p @class(['mt-1 text-xs', 'font-semibold text-red-700' => $hasOverdue, 'text-muted' => ! $hasOverdue])>
                    {{ $hasOverdue ? Money::format($kpis['overdue']).' gecikmiş ödeme' : ($balanceState > 0 ? 'Gecikmiş ödemeniz yok' : 'Borcunuz bulunmuyor') }} <span class="font-normal text-primary group-hover:underline">· Ekstre</span>
                </p>
            </a>
            <{{ $requestsOn ? 'a' : 'div' }} @if ($requestsOn) href="{{ route('dealer.requests.index') }}" @endif class="group p-5 transition {{ $requestsOn ? 'hover:bg-slate-50' : '' }}">
                <p class="flex items-center gap-2 text-sm font-medium text-muted"><span class="flex size-8 items-center justify-center rounded-lg bg-violet-100 text-violet-700"><x-icon name="inbox" class="size-4" /></span>Açık ürün talebi</p>
                <p class="mt-3 text-3xl font-bold text-ink tabular-nums">{{ $kpis['open_requests'] }}</p>
                <p class="mt-1 text-xs text-muted">Stoğa girince haber veririz @if ($requestsOn)<span class="text-primary group-hover:underline">· Taleplerim</span>@endif</p>
            </{{ $requestsOn ? 'a' : 'div' }}>
        </div>
    </section>

    @if ($arrived->isNotEmpty())
        <div class="mb-6 flex flex-wrap items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-900">
            <span class="flex size-9 items-center justify-center rounded-full bg-emerald-100 text-emerald-700"><x-icon name="check-circle" class="size-5" /></span>
            <p class="font-semibold">Talep ettiğiniz ürünler stoğa girdi:</p>
            @foreach ($arrived as $r)
                <a href="{{ route('dealer.catalog.show', $r->product_id) }}" class="rounded-full bg-white px-3 py-1 font-medium ring-1 ring-emerald-200 transition hover:ring-emerald-400">{{ $r->product->name }}</a>
            @endforeach
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Sol: alım grafiği ve son siparişler --}}
        <div class="space-y-6 lg:col-span-2">
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-2xl border border-line bg-white p-5 shadow-xs">
                    <p class="text-sm text-muted">{{ now()->year }} toplam alım</p>
                    <p class="mt-1 text-2xl font-bold text-ink tabular-nums">{{ Money::format($kpis['year_amount']) }}</p>
                </div>
                <div class="rounded-2xl border border-line bg-white p-5 shadow-xs">
                    <p class="text-sm text-muted">{{ now()->year }} sipariş sayısı</p>
                    <p class="mt-1 text-2xl font-bold text-ink tabular-nums">{{ $kpis['year_count'] }}</p>
                </div>
                <div class="rounded-2xl border border-line bg-white p-5 shadow-xs">
                    <p class="text-sm text-muted">Ortalama sipariş tutarı</p>
                    <p class="mt-1 text-2xl font-bold text-ink tabular-nums">{{ Money::format($kpis['year_average']) }}</p>
                </div>
            </div>

            <x-chart title="Aylık alımlarınız" description="Son 12 ay, onaylanan siparişler (KDV dahil)" height="h-60"
                :empty="array_sum($monthly['values']) == 0"
                :config="['type' => 'bar', 'unit' => 'money', 'name' => 'Alım tutarı', 'labels' => $monthly['labels'], 'values' => $monthly['values']]"
                :headers="['Ay', 'Tutar']"
                :rows="collect($monthly['labels'])->map(fn ($l, $i) => [$l, Money::format($monthly['values'][$i])])->all()" />

            <x-card title="Son siparişler" :padding="false">
                <x-slot:actions><a href="{{ route('dealer.orders.index') }}" class="text-sm font-medium text-primary hover:underline">Tümünü gör</a></x-slot:actions>
                @if ($recentOrders->isEmpty())
                    <x-empty-state icon="file" title="Henüz siparişiniz yok" text="Ürünleri sepete ekleyerek ilk siparişinizi verin." class="py-8">
                        <x-button :href="route('dealer.catalog')">Ürünlere göz at</x-button>
                    </x-empty-state>
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($recentOrders as $order)
                            <li>
                                <a href="{{ route('dealer.orders.show', $order) }}" class="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-3.5 transition hover:bg-slate-50">
                                    <div class="min-w-0 flex-1">
                                        <p class="font-semibold text-primary">{{ $order->order_no }}</p>
                                        <p class="text-xs text-muted">{{ $order->created_at->timezone('Europe/Istanbul')->format('d.m.Y') }} · {{ $order->items_count }} kalem</p>
                                    </div>
                                    <div class="hidden sm:block">@include('dealer.orders._progress', ['order' => $order, 'compact' => true])</div>
                                    <div class="w-36"><x-badge :tone="$order->statusTone()">{{ $order->statusLabel() }}</x-badge></div>
                                    <p class="w-28 text-right font-semibold text-ink tabular-nums">{{ Money::format($order->grand_total) }}</p>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>

        {{-- Sağ: duyurular, en çok alınanlar, iletişim --}}
        <div class="space-y-6">
            <x-card title="Duyurular" :padding="false">
                <x-slot:actions><a href="{{ route('dealer.announcements.index') }}" class="text-sm font-medium text-primary hover:underline">Tümü</a></x-slot:actions>
                @if ($announcements->isEmpty())
                    <p class="p-5 text-sm text-muted">Son 30 günde yeni duyuru yok.</p>
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($announcements as $a)
                            <li class="px-5 py-4 text-sm">
                                <div class="flex items-start justify-between gap-3">
                                    <p class="font-semibold text-ink">{{ $a->title }}</p>
                                    <span class="shrink-0 text-xs text-muted tabular-nums">{{ $a->published_at->timezone('Europe/Istanbul')->format('d.m.Y') }}</span>
                                </div>
                                <p class="mt-1 line-clamp-3 whitespace-pre-line text-muted">{{ $a->body }}</p>
                                @include('partials.announcement-attachments', ['announcement' => $a, 'route' => 'dealer.announcements.attachment'])
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            <x-card title="En çok aldıklarınız" :padding="false">
                @if ($topProducts->isEmpty())
                    <p class="p-5 text-sm text-muted">Onaylanmış siparişiniz olduğunda burada görünür.</p>
                @else
                    <ol class="divide-y divide-line text-sm">
                        @foreach ($topProducts as $p)
                            @php $image = $images[$p->product_id]?->primaryImage ?? null; @endphp
                            <li>
                                <a href="{{ route('dealer.catalog.show', $p->product_id) }}" class="flex items-center gap-3 px-5 py-3 transition hover:bg-slate-50">
                                    <span class="w-4 text-xs font-bold text-muted">{{ $loop->iteration }}</span>
                                    <span class="flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-line bg-white p-1">
                                        @if ($image)<img src="{{ route('dealer.images.show', $image) }}" alt="" class="max-h-full max-w-full object-contain" loading="lazy">@else<x-icon name="image" class="size-5 text-slate-300" />@endif
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate font-medium text-ink">{{ $p->name }}</span>
                                        <span class="text-xs text-muted">{{ $p->times }} siparişte</span>
                                    </span>
                                    <span class="shrink-0 text-right font-semibold tabular-nums">{{ Quantity::format($p->qty) }}<span class="block text-[11px] font-normal text-muted">{{ $p->unit_name }}</span></span>
                                </a>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-card>

            @if ($currentBusiness->phone || $currentBusiness->email)
                <x-card title="Toptancınıza ulaşın">
                    <p class="mb-3 font-semibold text-ink">{{ $currentBusiness->display_name }}</p>
                    <ul class="space-y-2.5 text-sm">
                        @if ($currentBusiness->phone)
                            <li><a href="tel:{{ preg_replace('/[^0-9+]/', '', $currentBusiness->phone) }}" class="flex items-center gap-3 hover:text-primary"><span class="flex size-8 items-center justify-center rounded-lg bg-slate-100 text-muted"><x-icon name="phone" class="size-4" /></span>{{ $currentBusiness->phone }}</a></li>
                        @endif
                        @if ($currentBusiness->email)
                            <li><a href="mailto:{{ $currentBusiness->email }}" class="flex items-center gap-3 break-all hover:text-primary"><span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-muted"><x-icon name="mail" class="size-4" /></span>{{ $currentBusiness->email }}</a></li>
                        @endif
                    </ul>
                    @if ($currentBusiness->moduleEnabled('messaging'))
                        <a href="{{ route('dealer.messages.create') }}" class="mt-4 flex items-center justify-center gap-2 rounded-lg border border-line px-4 py-2 text-sm font-semibold text-ink transition hover:bg-slate-50"><x-icon name="inbox" class="size-4" />Mesaj gönder</a>
                    @endif
                </x-card>
            @endif
        </div>
    </div>
</x-layouts.panel>
