@php
    use App\Domain\Support\Money;

    $status = $filters['status'] ?? '';
    $tabs = ['' => 'Tümü', 'open' => 'Devam eden', 'completed' => 'Tamamlanan', 'closed' => 'İptal / Red'];
    $tabUrl = fn (string $key) => route('dealer.orders.index', array_filter(['status' => $key, 'q' => $filters['q'] ?? null]));
@endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Dealer" title="Siparişlerim">
    <x-page-header title="Siparişlerim" subtitle="Verdiğiniz siparişleri ve hangi aşamada olduklarını buradan takip edin.">
        <x-button icon="plus" :href="route('dealer.catalog')">Yeni sipariş ver</x-button>
    </x-page-header>

    {{-- Özet --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <a href="{{ $tabUrl('open') }}" class="group rounded-2xl border border-line bg-white p-5 shadow-xs transition hover:border-primary/30 hover:shadow-md">
            <p class="flex items-center gap-2 text-sm font-medium text-muted"><span class="flex size-8 items-center justify-center rounded-lg bg-primary/10 text-primary"><x-icon name="refresh" class="size-4" /></span>Devam eden siparişler</p>
            <p class="mt-3 text-2xl font-bold text-ink tabular-nums">{{ $stats['open_count'] }} <span class="text-base font-medium text-muted">sipariş</span></p>
            <p class="text-sm text-muted">Toplam {{ Money::format($stats['open_total']) }}</p>
        </a>
        <div class="rounded-2xl border border-line bg-white p-5 shadow-xs">
            <p class="flex items-center gap-2 text-sm font-medium text-muted"><span class="flex size-8 items-center justify-center rounded-lg bg-amber-100 text-amber-700"><x-icon name="cart" class="size-4" /></span>Bu ayki alımlarınız</p>
            <p class="mt-3 text-2xl font-bold text-ink tabular-nums">{{ Money::format($stats['month_total']) }}</p>
            <p class="text-sm text-muted">{{ now()->translatedFormat('F Y') }} · iptal ve redler hariç</p>
        </div>
        <a href="{{ $tabUrl('completed') }}" class="group rounded-2xl border border-line bg-white p-5 shadow-xs transition hover:border-primary/30 hover:shadow-md">
            <p class="flex items-center gap-2 text-sm font-medium text-muted"><span class="flex size-8 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700"><x-icon name="check-circle" class="size-4" /></span>Tamamlanan siparişler</p>
            <p class="mt-3 text-2xl font-bold text-ink tabular-nums">{{ $stats['completed_count'] }} <span class="text-base font-medium text-muted">sipariş</span></p>
            <p class="text-sm text-muted">Teslim edilen ve kapanan</p>
        </a>
    </div>

    <x-card :padding="false">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line p-4">
            <nav class="flex flex-wrap gap-1 rounded-xl bg-slate-100 p-1" aria-label="Sipariş durumu">
                @foreach ($tabs as $key => $label)
                    @php $active = $status === $key; @endphp
                    <a href="{{ $tabUrl($key) }}" @class([
                        'inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-semibold transition',
                        'bg-white text-ink shadow-sm' => $active,
                        'text-muted hover:text-ink' => ! $active,
                    ]) @if ($active) aria-current="page" @endif>
                        {{ $label }}
                        <span @class(['rounded-full px-1.5 text-[11px] tabular-nums', 'bg-primary text-white' => $active, 'bg-slate-200 text-slate-600' => ! $active])>{{ $counts[$key] ?? 0 }}</span>
                    </a>
                @endforeach
            </nav>
            <form method="GET" class="relative w-full sm:w-72" role="search">
                @if ($status)<input type="hidden" name="status" value="{{ $status }}">@endif
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted" />
                <input name="q" value="{{ $filters['q'] ?? '' }}" class="field-input pl-9" placeholder="Sipariş no veya ürün adı" aria-label="Sipariş no veya ürün adı">
            </form>
        </div>

        @if ($orders->isEmpty())
            <x-empty-state icon="file" :title="($filters['q'] ?? null) || $status ? 'Bu filtreye uyan sipariş yok' : 'Henüz siparişiniz yok'">
                <x-button :href="route('dealer.catalog')">Ürünlere göz at</x-button>
            </x-empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead>
                        <tr>
                            <th>Sipariş</th>
                            <th class="hidden lg:table-cell">Ürünler</th>
                            <th class="text-right">Tutar</th>
                            <th>Durum</th>
                            <th class="w-10"><span class="sr-only">Detay</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($orders as $order)
                            @php $date = $order->created_at->timezone('Europe/Istanbul'); @endphp
                            <tr class="group transition hover:bg-slate-50/70">
                                <td>
                                    <a href="{{ route('dealer.orders.show', $order) }}" class="font-semibold text-primary hover:underline">{{ $order->order_no }}</a>
                                    <p class="text-xs text-muted tabular-nums">{{ $date->format('d.m.Y H:i') }} · {{ $date->diffForHumans() }}</p>
                                </td>
                                <td class="hidden max-w-md lg:table-cell">
                                    <p class="truncate text-sm text-ink">{{ $order->items->take(2)->pluck('name')->join(', ') }}</p>
                                    <p class="text-xs text-muted">{{ $order->items_count }} kalem @if ($order->items_count > 2)· +{{ $order->items_count - 2 }} ürün daha @endif</p>
                                </td>
                                <td class="text-right font-semibold whitespace-nowrap tabular-nums">{{ Money::format($order->grand_total) }}</td>
                                <td>
                                    <x-badge :tone="$order->statusTone()">{{ $order->statusLabel() }}</x-badge>
                                    <div class="mt-1.5">@include('dealer.orders._progress', ['order' => $order, 'compact' => true])</div>
                                </td>
                                <td class="text-right">
                                    <a href="{{ route('dealer.orders.show', $order) }}" class="inline-flex size-8 items-center justify-center rounded-full text-muted transition group-hover:bg-white group-hover:text-primary group-hover:shadow-sm" aria-label="{{ $order->order_no }} detayı">
                                        <x-icon name="arrow-right" class="size-4" />
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($orders->hasPages())<div class="border-t border-line p-4">{{ $orders->links() }}</div>@endif
        @endif
    </x-card>
</x-layouts.panel>
