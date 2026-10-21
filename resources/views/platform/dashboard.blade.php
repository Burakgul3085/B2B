@php use App\Domain\Auth\Area; use App\Domain\Platform\CreateBusiness; use App\Models\Business; @endphp
<x-layouts.panel :area="Area::Platform" title="Genel Durum">
    <x-page-header title="Genel Durum" subtitle="İşletmeler, lisanslar ve son platform işlemleri.">
        @if (CreateBusiness::allowed())<x-button :href="route('platform.businesses.create')" icon="plus">Yeni işletme</x-button>@endif
    </x-page-header>

    <div class="grid gap-4 sm:grid-cols-3">
        @foreach (Business::STATUSES as $key => $label)
            <x-kpi :label="$label.' işletme'" :value="$statusCounts[$key] ?? 0"
                :href="route('platform.businesses.index', ['status' => $key])" hint="Listeyi aç" />
        @endforeach
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <x-card title="30 gün içinde biten lisanslar" :padding="false">
            @forelse ($expiring as $license)
                <a href="{{ route('platform.businesses.show', $license->business_id) }}" class="flex items-center justify-between border-b border-line px-5 py-3 text-sm last:border-0 hover:bg-slate-50">
                    <span class="font-medium">{{ $license->display_name }}</span>
                    <x-badge tone="warning">{{ $license->ends_on->format('d.m.Y') }}</x-badge>
                </a>
            @empty
                <x-empty-state icon="check-circle" title="Yaklaşan lisans bitişi yok" class="py-8" />
            @endforelse
        </x-card>

        <x-card title="Şifresi belirlenmemiş işletmeler" description="Eski aktivasyon akışından kalan, yetkilisine henüz şifre verilmemiş işletmeler. İşletme detayından şifre belirleyin." :padding="false">
            @forelse ($pendingOwners as $business)
                <a href="{{ route('platform.businesses.show', $business) }}" class="flex items-center justify-between border-b border-line px-5 py-3 text-sm last:border-0 hover:bg-slate-50">
                    <span class="font-medium">{{ $business->display_name }}</span>
                    <span class="text-muted">{{ $business->created_at->format('d.m.Y') }}</span>
                </a>
            @empty
                <x-empty-state icon="check-circle" title="Şifre bekleyen işletme yok" class="py-8" />
            @endforelse
        </x-card>
    </div>

    <x-card title="Son platform işlemleri" class="mt-6" :padding="false">
        <x-slot:actions><x-button variant="ghost" size="sm" :href="route('platform.audit-logs')">Tümü</x-button></x-slot:actions>
        @include('platform.partials.audit-table', ['logs' => $recent])
    </x-card>
</x-layouts.panel>
