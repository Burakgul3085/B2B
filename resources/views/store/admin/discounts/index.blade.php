@php
    use App\Domain\Support\Money;
    use App\Models\Store\StoreDiscount;
@endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" title="Kampanya ve Kuponlar">
    <x-page-header title="Kampanya ve Kuponlar" subtitle="Kupon: müşteri sepette kodu girer. Kampanya: koşulları tutan sepete kendiliğinden uygulanır. Bir siparişte tek indirim geçerlidir; müşteriye en avantajlısı uygulanır.">
        <x-button :href="route('store_admin.discounts.create')" icon="plus">Yeni indirim</x-button>
    </x-page-header>

    @error('discount')<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror

    <nav class="mb-4 flex flex-wrap gap-2 text-sm" aria-label="Durum">
        <a href="{{ route('store_admin.discounts.index') }}" @class(['rounded-lg px-3 py-1.5 font-medium', 'bg-primary text-white' => ! $filter, 'border border-line text-ink hover:bg-slate-50' => $filter])>
            Tümü <span class="tabular-nums opacity-70">{{ $total }}</span></a>
        @foreach ($filters as $key => $label)
            <a href="{{ route('store_admin.discounts.index', ['durum' => $key]) }}" @class(['rounded-lg px-3 py-1.5 font-medium', 'bg-primary text-white' => $filter === $key, 'border border-line text-ink hover:bg-slate-50' => $filter !== $key])>
                {{ $label }} <span class="tabular-nums opacity-70">{{ $counts[$key] }}</span></a>
        @endforeach
    </nav>

    <x-card :padding="false">
        @if ($discounts->isEmpty())
            <x-empty-state icon="percent" :title="$filter ? 'Bu durumda indirim yok' : 'Henüz indirim yok'" text="Yüzde, tutar veya ücretsiz teslimat indirimi tanımlayın." />
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>İndirim</th><th>Kod</th><th>Koşul</th><th>Geçerlilik</th><th class="text-right">Kullanım</th><th>Durum</th><th><span class="sr-only">İşlem</span></th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($discounts as $discount)
                            @php [$label, $tone] = StoreDiscount::STATUSES[$discount->state]; @endphp
                            <tr>
                                <td><a href="{{ route('store_admin.discounts.edit', $discount) }}" class="font-semibold text-ink hover:text-primary">{{ $discount->name }}</a>
                                    <p class="text-xs text-muted">{{ $discount->isCoupon() ? 'Kupon' : 'Otomatik kampanya' }}@if ($discount->show_on_page) · Kampanyalar sayfasında @endif</p></td>
                                <td class="font-mono text-sm">{{ $discount->code ?? '—' }}</td>
                                <td class="text-sm">{{ $discount->summary() }}
                                    @if ($discount->first_order_only)<span class="block text-xs text-muted">Yalnızca ilk sipariş</span>@endif
                                    @if ($discount->audience === 'new_members' && $discount->new_member_days)<span class="block text-xs text-muted">Üyelikten sonraki {{ $discount->new_member_days }} gün</span>@endif</td>
                                <td class="text-xs text-muted">{{ $discount->starts_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i') ?? 'Hemen' }} →<br>{{ $discount->ends_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i') ?? 'Süresiz' }}</td>
                                <td class="text-right tabular-nums">{{ $discount->used_count }}@if ($discount->usage_limit) / {{ $discount->usage_limit }}@endif
                                    <span class="block text-xs text-muted">{{ Money::format($discount->used_amount ?? 0) }}</span></td>
                                <td><x-badge :tone="$tone">{{ $label }}</x-badge></td>
                                <td class="text-right"><a href="{{ route('store_admin.discounts.create', ['kopya' => $discount->id]) }}" class="inline-flex items-center gap-1 text-sm text-muted hover:text-primary" title="Bu indirimden yeni bir tane oluştur"><x-icon name="copy" class="size-4" /> Kopyala</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
</x-layouts.panel>
