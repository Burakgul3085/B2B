@php use App\Domain\Inventory\Quantity; use App\Models\ProductRequest; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Dealer" title="Taleplerim">
    <x-page-header title="Ürün taleplerim" subtitle="Stokta olmayan ürünler için toptancınıza ilettiğiniz talepler. Talep stok ayırmaz ve siparişe dönüşmez." />

    <x-card :padding="false">
        @if ($requests->isEmpty())
            <x-empty-state icon="inbox" title="Talebiniz yok" text="Stokta olmayan bir ürünün sayfasından talep oluşturabilirsiniz." />
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>Ürün</th><th class="text-right">Miktar</th><th>Durum</th><th>Toptancı yanıtı</th><th>Tarih</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($requests as $r)
                            <tr>
                                <td><a href="{{ route('dealer.catalog.show', $r->product_id) }}" class="font-medium hover:text-primary">{{ $r->product->name }}</a><p class="text-xs text-muted">{{ $r->product->sku }}</p></td>
                                <td class="text-right tabular-nums">{{ Quantity::format($r->quantity) }} {{ $r->product->unit->name }}</td>
                                <td>
                                    <x-badge :tone="$r->statusTone()">{{ ProductRequest::STATUSES[$r->status] }}</x-badge>
                                    @if ($r->status === 'stock_updated')<a href="{{ route('dealer.catalog.show', $r->product_id) }}" class="mt-1 block text-xs font-medium text-primary hover:underline">Sipariş ver</a>@endif
                                </td>
                                <td class="max-w-64 text-sm">{{ $r->response_note ?? '—' }}</td>
                                <td class="text-muted tabular-nums">{{ $r->created_at->format('d.m.Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($requests->hasPages())<div class="border-t border-line p-4">{{ $requests->links() }}</div>@endif
        @endif
    </x-card>
</x-layouts.panel>
