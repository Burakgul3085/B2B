@php use App\Models\DealerApplication; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Platform" title="Bayilik Başvuruları">
    <x-page-header title="Bayilik Başvuruları" subtitle="Bütün toptancılara gelen başvurular. Toptancı da kendi panelinden görür ve onaylayabilir." />

    <x-card :padding="false">
        <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-line p-4">
            <x-field name="status" label="Durum" :options="['open' => 'Açık başvurular'] + DealerApplication::STATUSES" :value="$filters['status']" class="w-52" />
            <x-field name="business" label="Toptancı" :options="['' => 'Tümü'] + $businesses->all()" :value="$filters['business'] ?? ''" class="w-60" />
            <x-button variant="secondary" icon="search">Filtrele</x-button>
        </form>

        @if ($applications->isEmpty())
            <x-empty-state icon="inbox" title="Başvuru yok" text="Seçilen filtrede başvuru bulunmuyor." class="py-8" />
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>Başvuran</th><th>Toptancı</th><th>İl / ilçe</th><th>Tarih</th><th>Durum</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($applications as $a)
                            <tr>
                                <td><a href="{{ route('platform.applications.show', $a->id) }}" class="font-medium text-ink hover:text-primary">{{ $a->legal_name }}</a><p class="text-xs text-muted">{{ $a->contact_name }} · {{ $a->email }}</p></td>
                                <td class="text-sm">{{ $a->business->display_name }}</td>
                                <td class="text-sm text-muted">{{ $a->city }} / {{ $a->district }}</td>
                                <td class="text-sm text-muted tabular-nums">{{ $a->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</td>
                                <td><x-badge :tone="['new' => 'warning', 'in_review' => 'primary', 'waiting_info' => 'warning', 'approved' => 'success'][$a->status] ?? 'neutral'">{{ DealerApplication::STATUSES[$a->status] }}</x-badge></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($applications->hasPages())<div class="border-t border-line p-4">{{ $applications->links() }}</div>@endif
        @endif
    </x-card>
</x-layouts.panel>
