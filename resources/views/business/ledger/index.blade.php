@php use App\Domain\Support\Money; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Business" title="Cari Hesaplar">
    <x-page-header title="Cari Hesaplar" subtitle="Bayilerinizle ticari bakiyeler. Resmî muhasebe kaydı değildir.">
        <x-button variant="secondary" :href="route('business.collections.index')">Tahsilatlar</x-button>
    </x-page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-kpi label="Toplam alacak" :value="Money::format($totals['receivable'])" hint="Borç bakiyeli bayiler" />
        <x-kpi label="Vadesi geçmiş" :value="Money::format($totals['overdue'])" :href="route('business.accounts.index', ['view' => 'overdue'])" hint="Listele" />
        <x-kpi label="Bu ay tahsilat" :value="Money::format($totals['month_collections'])" :href="route('business.collections.index')" hint="Tahsilatlar" />
    </div>

    <x-card :padding="false">
        <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-line p-4">
            <x-field name="q" label="Ara" :value="$filters['q'] ?? ''" placeholder="Bayi adı veya cari kodu" class="min-w-60 flex-1" />
            <x-field name="view" label="Göster" :options="['debt' => 'Borcu olanlar', 'overdue' => 'Vadesi geçenler', 'all' => 'Tümü']" :value="$view" />
            <x-button variant="secondary" icon="search">Filtrele</x-button>
        </form>
        @if ($accounts->isEmpty())
            <x-empty-state icon="check-circle" title="{{ $view === 'overdue' ? 'Vadesi geçmiş alacak yok' : 'Gösterilecek cari hesap yok' }}" />
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>Bayi</th><th class="text-right">Bakiye</th><th class="text-right">Vadesi geçmiş</th><th class="hidden text-right md:table-cell">7 gün içinde</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($accounts as $account)
                            @php $a = $aging[$account->id]; @endphp
                            <tr>
                                <td><a href="{{ route('business.accounts.show', $account) }}" class="font-medium hover:text-primary">{{ $account->relationship->dealer->legal_name }}</a><p class="text-xs text-muted">{{ $account->code }}</p></td>
                                <td class="text-right font-semibold tabular-nums">{{ Money::format($account->balance) }}</td>
                                <td @class(['text-right tabular-nums', 'font-semibold text-danger' => bccomp($a['overdue'], '0', 2) > 0, 'text-muted' => bccomp($a['overdue'], '0', 2) === 0])>{{ Money::format($a['overdue']) }}</td>
                                <td class="hidden text-right text-muted tabular-nums md:table-cell">{{ Money::format($a['due_soon']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
</x-layouts.panel>
