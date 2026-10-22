@php use App\Models\DealerRelationship; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Platform" :title="'Destek görünümü · '.$business->display_name">
    <x-page-header :title="'Destek görünümü · '.$business->display_name" subtitle="Salt okunur. Baktığınız her bölüm işletmenin destek kayıtlarına yazılır.">
        <x-slot:breadcrumb><a href="{{ route('platform.businesses.show', $business) }}" class="hover:underline">{{ $business->display_name }}</a></x-slot:breadcrumb>
        <x-badge tone="warning" class="px-3! py-1! text-sm!">Erişim bitişi: {{ $grant->expires_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</x-badge>
    </x-page-header>

    <p class="mb-4 rounded-lg bg-surface px-4 py-3 text-sm text-muted">Neden: {{ $grant->reason }}</p>

    <div class="mb-4 flex flex-wrap gap-2 text-sm">
        @foreach ($sections as $key => $label)
            <a href="{{ route('platform.businesses.support', [$business, $key]) }}" @class([
                'rounded-full px-3.5 py-1.5 font-medium ring-1',
                'bg-primary text-white ring-primary' => $section === $key,
                'bg-white text-ink ring-line hover:ring-primary' => $section !== $key,
            ])>{{ $label }}</a>
        @endforeach
    </div>

    <x-card :padding="false">
        @if ($rows->isEmpty())
            <x-empty-state icon="inbox" title="Kayıt yok" />
        @elseif ($section === 'orders')
            <table class="table-base">
                <thead><tr><th>Sipariş</th><th>Bayi</th><th>Durum</th><th class="text-right">Tutar</th></tr></thead>
                <tbody class="divide-y divide-line">
                    @foreach ($rows as $o)
                        <tr><td class="font-medium">{{ $o->order_no }}<p class="text-xs text-muted">{{ $o->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</p></td>
                            <td>{{ $o->relationship?->dealer?->legal_name ?? '—' }}</td><td>{{ $o->statusLabel() }}</td>
                            <td class="text-right tabular-nums">{{ \App\Domain\Support\Money::format($o->grand_total) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @elseif ($section === 'dealers')
            <table class="table-base">
                <thead><tr><th>Bayi</th><th>Kod</th><th>Şehir</th><th>Durum</th></tr></thead>
                <tbody class="divide-y divide-line">
                    @foreach ($rows as $r)
                        <tr><td class="font-medium">{{ $r->dealer?->legal_name }}</td><td class="font-mono text-sm">{{ $r->dealer_code }}</td>
                            <td>{{ $r->dealer?->city }}</td><td>{{ DealerRelationship::STATUSES[$r->status] ?? $r->status }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <table class="table-base">
                <thead><tr><th>SKU</th><th>Ürün</th><th>Birim</th><th class="text-right">Liste fiyatı</th><th>Durum</th></tr></thead>
                <tbody class="divide-y divide-line">
                    @foreach ($rows as $p)
                        <tr><td class="font-mono text-sm">{{ $p->sku }}</td><td>{{ $p->name }}</td><td>{{ $p->unit?->name }}</td>
                            <td class="text-right tabular-nums">{{ \App\Domain\Support\Money::formatUnit($p->list_price) }}</td><td>{{ $p->is_active ? 'Aktif' : 'Pasif' }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-card>
    <p class="mt-3 text-xs text-muted">En son 50 sipariş, 100 bayi ve 100 ürün gösterilir.</p>
</x-layouts.panel>
