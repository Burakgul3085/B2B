@php use App\Models\Store\StoreStockMovement; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" title="Stok Hareketleri">
    <x-page-header title="Stok Hareketleri" :subtitle="$variant ? $variant->product->name.' · '.$variant->sku : 'Hareketler silinmez; yanlış bir giriş ters kayıtla geri alınır.'">
        <x-slot:breadcrumb><a href="{{ route('store_admin.stock.index') }}" class="hover:underline">Stok Durumu</a></x-slot:breadcrumb>
    </x-page-header>

    <x-card :padding="false">
        <form method="GET" class="grid gap-3 border-b border-line p-4 sm:grid-cols-2 lg:grid-cols-5 lg:items-end">
            @if ($variant)<input type="hidden" name="variant" value="{{ $variant->id }}">@endif
            <x-field name="q" label="Ara" :value="$filters['q'] ?? ''" placeholder="Ürün adı veya stok kodu" />
            <x-field name="type" label="Tür" :options="['' => 'Tümü'] + StoreStockMovement::TYPES" :value="$filters['type'] ?? ''" />
            <x-field name="from" label="Başlangıç" type="date" :value="$filters['from'] ?? ''" />
            <x-field name="to" label="Bitiş" type="date" :value="$filters['to'] ?? ''" />
            <x-button variant="secondary" icon="search">Filtrele</x-button>
        </form>

        @if ($movements->isEmpty())
            <x-empty-state icon="refresh" title="Hareket yok" />
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>Tarih</th><th>Ürün</th><th>Tür</th><th class="text-right">Miktar</th><th class="text-right">Sonraki stok</th><th>Açıklama</th><th>Kim</th><th></th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($movements as $m)
                            <tr>
                                <td class="whitespace-nowrap text-muted tabular-nums">{{ $m->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</td>
                                <td>
                                    <a href="{{ route('store_admin.products.edit', $m->variant->product_id) }}" class="text-ink hover:text-primary">{{ $m->variant->product->name }}</a>
                                    <p class="text-xs text-muted">@if ($m->variant->option_value){{ $m->variant->option_value }} · @endif{{ $m->variant->sku }}</p>
                                </td>
                                <td><x-badge :tone="match ($m->type) { 'receipt', 'return' => 'success', 'sale' => 'primary', 'reversal' => 'neutral', default => 'warning' }">{{ $m->typeLabel() }}</x-badge></td>
                                <td @class(['text-right font-semibold tabular-nums', 'text-success' => $m->quantity > 0, 'text-danger' => $m->quantity < 0])>{{ $m->quantity > 0 ? '+' : '' }}{{ $m->quantity }}</td>
                                <td class="text-right tabular-nums">{{ $m->balance_after }}</td>
                                <td class="max-w-64 text-sm text-muted">{{ $m->note ?? '—' }}</td>
                                <td class="text-sm text-muted">{{ match ($m->actor_type) { 'store_admin' => $actors[$m->actor_id] ?? 'Yönetici', 'customer' => 'Müşteri', null => 'Sistem', default => $m->actor_type } }}</td>
                                <td class="text-right whitespace-nowrap">
                                    @if ($m->reversal)
                                        <span class="text-xs text-muted">Geri alındı</span>
                                    @elseif (in_array($m->type, StoreStockMovement::REVERSIBLE, true))
                                        <form method="POST" action="{{ route('store_admin.stock.reverse', $m) }}" data-confirm="Bu hareket ters kayıtla geri alınsın mı? Stok {{ $m->quantity > 0 ? $m->quantity.' adet azalır' : abs($m->quantity).' adet artar' }}.">
                                            @csrf
                                            <button class="text-sm font-medium text-primary hover:underline">Geri al</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($movements->hasPages())<div class="border-t border-line p-4">{{ $movements->links() }}</div>@endif
        @endif
    </x-card>
</x-layouts.panel>
