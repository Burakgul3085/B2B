@php
    $styles = [
        'ok' => ['check-circle', 'text-green-700', 'Tamam'],
        'warn' => ['alert', 'text-amber-600', 'Kontrol edin'],
        'todo' => ['x', 'text-danger', 'Eksik'],
        'later' => ['circle', 'text-slate-400', 'Yayın aşamasında'],
    ];
    $all = collect($groups)->flatMap(fn ($g) => $g['items']);
    $todo = $all->where('status', 'todo')->count();
@endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" title="Yayın Hazırlığı">
    <x-page-header title="Yayın Hazırlığı" subtitle="iyzico üye işyeri başvurusunda incelenen maddeler ve mağazayı ziyaretçilere açmadan önce yapılacaklar.">
        <x-badge :tone="$todo ? 'warning' : 'success'" class="px-3! py-1! text-sm!">{{ $todo ? $todo.' eksik madde' : 'Eksik madde yok' }}</x-badge>
    </x-page-header>

    <div class="space-y-6">
        @foreach ($groups as $group)
            <x-card :title="$group['group']" :padding="false">
                <ul class="divide-y divide-line">
                    @foreach ($group['items'] as $item)
                        @php [$icon, $color, $label] = $styles[$item['status']]; @endphp
                        <li class="flex flex-wrap items-start gap-3 px-5 py-4">
                            <x-icon :name="$icon" class="mt-0.5 size-5 shrink-0 {{ $color }}" />
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-ink">{{ $item['label'] }} <span class="ml-1 text-xs font-normal {{ $color }}">{{ $label }}</span></p>
                                <p class="mt-0.5 text-sm text-muted">{{ $item['hint'] }}</p>
                            </div>
                            @if ($item['route'] && $item['status'] !== 'ok')
                                <a href="{{ route($item['route']) }}" class="text-sm font-medium text-primary hover:underline">Düzenle</a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endforeach
    </div>
</x-layouts.panel>
