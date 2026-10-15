@php use App\Models\Business; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Platform" title="İşletmeler">
    <x-page-header title="İşletmeler" subtitle="Platformu kullanan toptancı işletmeleri.">
        <x-button :href="route('platform.businesses.create')" icon="plus">Yeni işletme</x-button>
    </x-page-header>

    <x-card :padding="false">
        <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-line p-4">
            <x-field name="q" label="Ara" :value="$filters['q'] ?? ''" placeholder="Unvan, ad veya kısa ad" class="min-w-60 flex-1" />
            <x-field name="status" label="Durum" :options="['' => 'Tümü'] + Business::STATUSES" :value="$filters['status'] ?? ''" class="w-44" />
            <x-button variant="secondary" icon="search">Filtrele</x-button>
        </form>

        @if ($businesses->isEmpty())
            <x-empty-state icon="building" title="İşletme bulunamadı" text="Filtreleri temizleyin veya yeni bir işletme ekleyin.">
                <x-button :href="route('platform.businesses.create')" icon="plus">Yeni işletme</x-button>
            </x-empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>İşletme</th><th>Yetkili</th><th>Lisans</th><th>Durum</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($businesses as $business)
                            @php $license = $business->currentLicense(); @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('platform.businesses.show', $business) }}" class="font-medium text-ink hover:text-primary">{{ $business->display_name }}</a>
                                    <p class="text-xs text-muted">{{ $business->legal_name }}</p>
                                </td>
                                <td>
                                    {{ $business->user?->email ?? '—' }}
                                    @if ($business->user?->status === 'pending_activation')<x-badge tone="warning" class="ml-1">Aktivasyon bekliyor</x-badge>@endif
                                </td>
                                <td class="text-muted">
                                    @if ($license){{ $license->plan }} · {{ $license->ends_on?->format('d.m.Y') ?? 'Süresiz' }}@else<x-badge tone="danger">Geçerli lisans yok</x-badge>@endif
                                </td>
                                <td><x-badge :tone="['active' => 'success', 'suspended' => 'warning', 'closed' => 'neutral'][$business->status]">{{ Business::STATUSES[$business->status] }}</x-badge></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($businesses->hasPages())<div class="border-t border-line p-4">{{ $businesses->links() }}</div>@endif
        @endif
    </x-card>
</x-layouts.panel>
