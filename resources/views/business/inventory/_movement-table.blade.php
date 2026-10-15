@php use App\Domain\Inventory\Quantity; @endphp
@if ($movements->isEmpty())
    <p class="p-5 text-sm text-muted">Stok hareketi yok.</p>
@else
    <div class="overflow-x-auto">
        <table class="table-base">
            <thead>
                <tr>
                    <th>Tarih</th>
                    @if ($showProduct)<th>Ürün</th>@endif
                    <th>Depo</th>
                    <th>Hareket</th>
                    <th class="text-right">Miktar</th>
                    <th class="text-right">Sonraki stok</th>
                    <th class="hidden lg:table-cell">Açıklama</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @foreach ($movements as $m)
                    @php $positive = bccomp((string) $m->quantity, '0', 3) > 0; @endphp
                    <tr>
                        <td class="whitespace-nowrap text-muted tabular-nums">{{ $m->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</td>
                        @if ($showProduct)
                            <td><a href="{{ route('business.products.show', $m->product_id) }}" class="font-medium hover:text-primary">{{ $m->product->name }}</a><p class="text-xs text-muted">{{ $m->product->sku }}</p></td>
                        @endif
                        <td>{{ $m->warehouse->name }}</td>
                        <td>{{ $m->typeLabel() }}</td>
                        <td @class(['text-right font-medium tabular-nums', 'text-success' => $positive, 'text-danger' => ! $positive])>{{ $positive ? '+' : '' }}{{ Quantity::format($m->quantity) }}</td>
                        <td class="text-right tabular-nums">{{ Quantity::format($m->balance_after) }}</td>
                        <td class="hidden max-w-64 truncate text-muted lg:table-cell" title="{{ $m->note }}">{{ $m->note ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
