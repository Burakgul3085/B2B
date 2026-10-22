@php $prefix = $area->value; @endphp
<x-layouts.panel :area="$area" title="Raporlar">
    <x-page-header title="Raporlar" subtitle="Filtreleyin, sıralayın, sütun seçin; Excel veya PDF olarak indirin. Ekrandaki ve indirilen rapor aynı filtre ve hesaplamayı kullanır." />

    @if ($saved->isNotEmpty())
        <x-card title="Kayıtlı raporlarım" class="mb-6" :padding="false">
            <ul class="divide-y divide-line">
                @foreach ($saved as $s)
                    <li class="flex items-center gap-3 px-5 py-3">
                        <x-icon name="filter" class="size-4 shrink-0 text-primary" />
                        <a href="{{ route($prefix.'.reports.show', [$s->report_key] + $s->filters) }}" class="flex-1 text-sm">
                            <span class="font-medium text-ink hover:text-primary">{{ $s->name }}</span>
                            <span class="block text-xs text-muted">{{ $titles[$s->report_key] ?? $s->report_key }}</span>
                        </a>
                        <form method="POST" action="{{ route($prefix.'.reports.saved.destroy', $s->id) }}" data-confirm="&quot;{{ $s->name }}&quot; silinsin mi?">@csrf @method('DELETE')
                            <button class="text-sm text-muted hover:text-danger">Sil</button></form>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        @foreach ($groups as $group => $reports)
            <x-card :title="$group" :padding="false">
                <ul class="divide-y divide-line">
                    @foreach ($reports as $report)
                        <li>
                            <a href="{{ route($prefix.'.reports.show', $report->key()) }}" class="flex items-center gap-3 px-5 py-3.5 hover:bg-slate-50">
                                <x-icon name="file" class="size-5 shrink-0 text-primary" />
                                <span class="flex-1">
                                    <span class="block text-sm font-medium">{{ $report->title() }}</span>
                                    <span class="block text-xs text-muted">{{ $report->description() }}</span>
                                </span>
                                <x-icon name="arrow-right" class="size-4 text-muted" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endforeach
    </div>
</x-layouts.panel>
