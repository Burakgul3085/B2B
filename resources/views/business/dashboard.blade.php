<x-layouts.panel :area="\App\Domain\Auth\Area::Business" title="Dashboard">
    <x-page-header title="Dashboard" :subtitle="'Hoş geldiniz, '.auth('business')->user()->name.'.'" />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-kpi label="Aktif bayi" :value="$kpis['active_dealers']" />
    </div>

    <x-card title="Kurulum adımları" description="Sipariş, stok ve cari göstergeleri ilgili modüller açıldıkça bu sayfada yer alacak." class="mt-6" :padding="false">
        <ul class="divide-y divide-line">
            @foreach ($setup as $step)
                <li>
                    <a href="{{ route($step['route']) }}" class="flex items-center gap-3 px-5 py-3.5 text-sm hover:bg-slate-50">
                        <x-icon :name="$step['done'] ? 'check-circle' : 'circle'" @class(['size-5', 'text-success' => $step['done'], 'text-slate-300' => ! $step['done']]) />
                        <span @class(['flex-1', 'text-muted line-through' => $step['done']])>{{ $step['label'] }}</span>
                        <x-icon name="arrow-right" class="size-4 text-muted" />
                    </a>
                </li>
            @endforeach
        </ul>
    </x-card>
</x-layouts.panel>
