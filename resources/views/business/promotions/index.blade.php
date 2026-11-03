@php use App\Domain\Support\Money; use App\Models\Promotion; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Business" title="Kupon ve Kampanyalar">
    <x-page-header title="Kupon ve Kampanyalar" subtitle="Bayilerinize sipariş indirimi: kupon kodunu bayi sepette girer, kampanya koşulu tutan siparişe kendiliğinden uygulanır.">
        <x-button icon="plus" :href="route('business.promotions.create')">Yeni kupon / kampanya</x-button>
    </x-page-header>
    @error('promotion')<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror

    <x-card :padding="false">
        @if ($promotions->isEmpty())
            <x-empty-state icon="gift" title="Henüz kupon veya kampanya yok" text="Ör. “OKUL10” kodu ile %10 indirim ya da ₺10.000 ve üzeri siparişlere otomatik %5 indirim.">
                <x-button icon="plus" :href="route('business.promotions.create')">Yeni kupon / kampanya</x-button>
            </x-empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-line text-left text-xs text-muted">
                        <tr>
                            <th class="px-5 py-3 font-medium">Ad</th><th class="px-3 py-3 font-medium">Kod</th><th class="px-3 py-3 font-medium">İndirim</th>
                            <th class="px-3 py-3 font-medium">Kime</th><th class="px-3 py-3 text-right font-medium">Kullanım</th><th class="px-3 py-3 font-medium">Durum</th><th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($promotions as $promotion)
                            @php [$statusLabel, $tone] = Promotion::STATUSES[$promotion->status($promotion->used_count)]; @endphp
                            <tr>
                                <td class="px-5 py-3">
                                    <a href="{{ route('business.promotions.edit', $promotion) }}" class="font-medium hover:text-primary">{{ $promotion->name }}</a>
                                    <span class="block text-xs text-muted">{{ $promotion->starts_at?->timezone('Europe/Istanbul')->format('d.m.Y') ?? 'Hemen' }} – {{ $promotion->ends_at?->timezone('Europe/Istanbul')->format('d.m.Y') ?? 'süresiz' }}</span>
                                </td>
                                <td class="px-3 py-3">
                                    @if ($promotion->code)<span class="font-mono">{{ $promotion->code }}</span>@else<span class="text-muted">Otomatik</span>@endif
                                </td>
                                <td class="px-3 py-3">{{ $promotion->summary() }}</td>
                                <td class="px-3 py-3">{{ Promotion::TARGETS[$promotion->target] }}{{ $promotion->target === 'dealers' ? ' ('.count($promotion->dealer_ids ?? []).')' : '' }}</td>
                                <td class="px-3 py-3 text-right tabular-nums">
                                    {{ $promotion->used_count }}{{ $promotion->usage_limit ? ' / '.$promotion->usage_limit : '' }}
                                    @if ($promotion->used_amount)<span class="block text-xs text-muted">{{ Money::format($promotion->used_amount) }}</span>@endif
                                </td>
                                <td class="px-3 py-3"><x-badge :tone="$tone">{{ $statusLabel }}</x-badge></td>
                                <td class="px-5 py-3 text-right">
                                    <form method="POST" action="{{ route('business.promotions.toggle', $promotion) }}">@csrf @method('PUT')
                                        <button class="text-sm text-primary hover:underline">{{ $promotion->is_active ? 'Pasife al' : 'Etkinleştir' }}</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
</x-layouts.panel>
