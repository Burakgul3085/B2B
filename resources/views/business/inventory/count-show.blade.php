@php use App\Domain\Inventory\Quantity; use App\Models\StockCount; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Business" :title="'Sayım #'.$count->id">
    <x-page-header :title="'Sayım #'.$count->id" :subtitle="$count->warehouse->name.($count->note ? ' · '.$count->note : '')">
        <x-slot:breadcrumb><a href="{{ route('business.stock-counts.index') }}" class="hover:underline">Stok sayımları</a></x-slot:breadcrumb>
        <x-badge :tone="['draft' => 'warning', 'applied' => 'success', 'cancelled' => 'neutral'][$count->status]">{{ StockCount::STATUSES[$count->status] }}</x-badge>
    </x-page-header>

    @error('count')<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror

    @if ($count->status === 'draft')
        <x-card class="mb-6">
            <form method="POST" action="{{ route('business.stock-counts.lines.store', $count) }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <x-field name="code" label="Ürün kodu veya barkod" required autofocus class="min-w-60 flex-1" />
                <x-field name="counted_qty" label="Sayılan miktar" inputmode="decimal" required class="w-40" />
                <x-button icon="plus">Ekle</x-button>
            </form>
            <p class="mt-3 text-xs text-muted">Aynı ürünü tekrar eklerseniz sayılan miktar güncellenir. Barkod okuyucu ile kullanılabilir.</p>
        </x-card>
    @endif

    <x-card :padding="false">
        @if ($count->lines->isEmpty())
            <x-empty-state icon="list" title="Sayıma ürün eklenmedi" />
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead>
                        <tr>
                            <th>Ürün</th>
                            <th class="text-right">{{ $count->status === 'applied' ? 'Sistem (uygulama anı)' : 'Şu anki sistem stoğu' }}</th>
                            <th class="text-right">Sayılan</th>
                            <th class="text-right">Fark</th>
                            @if ($count->status === 'draft')<th></th>@endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($count->lines as $line)
                            @php
                                $system = $count->status === 'applied' ? (string) $line->system_qty : (string) ($current[$line->product_id] ?? '0');
                                $diff = $count->status === 'applied' ? (string) $line->difference : bcsub((string) $line->counted_qty, $system, 3);
                                $cmp = bccomp($diff, '0', 3);
                            @endphp
                            <tr>
                                <td><span class="font-medium">{{ $line->product->name }}</span><p class="text-xs text-muted">{{ $line->product->sku }}</p></td>
                                <td class="text-right tabular-nums">{{ $count->status === 'cancelled' ? '—' : Quantity::format($system) }}</td>
                                <td class="text-right font-medium tabular-nums">{{ Quantity::format($line->counted_qty) }} <span class="text-xs font-normal text-muted">{{ $line->product->unit->name }}</span></td>
                                <td @class(['text-right font-medium tabular-nums', 'text-success' => $cmp > 0, 'text-danger' => $cmp < 0, 'text-muted' => $cmp === 0])>
                                    {{ $count->status === 'cancelled' ? '—' : ($cmp > 0 ? '+' : '').Quantity::format($diff) }}
                                </td>
                                @if ($count->status === 'draft')
                                    <td class="text-right">
                                        <form method="POST" action="{{ route('business.stock-counts.lines.destroy', [$count, $line]) }}">@csrf @method('DELETE')<button class="text-sm text-danger hover:underline">Çıkar</button></form>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($count->status === 'draft')
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line p-4">
                <p class="text-sm text-muted">Fark, sayım uygulandığı andaki stoğa göre yeniden hesaplanır.</p>
                <div class="flex gap-2">
                    <form method="POST" action="{{ route('business.stock-counts.cancel', $count) }}" data-confirm="Sayım iptal edilsin mi? Stok değişmez.">@csrf<x-button variant="ghost">İptal et</x-button></form>
                    @if ($count->lines->isNotEmpty())
                        <form method="POST" action="{{ route('business.stock-counts.apply', $count) }}" data-confirm="Sayım uygulansın mı? Farklar stok hareketi olarak kaydedilecek ve bu işlem geri alınamaz.">@csrf<x-button>Sayımı uygula</x-button></form>
                    @endif
                </div>
            </div>
        @endif
    </x-card>
</x-layouts.panel>
