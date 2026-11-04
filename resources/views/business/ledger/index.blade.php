@php use App\Domain\Support\Money; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Business" title="Cari Hesaplar">
    <x-page-header title="Cari Hesaplar" subtitle="Bayilerinizle aranızdaki borç ve ödeme durumu. Resmî muhasebe kaydı değildir." />
    @include('business.ledger._tabs')

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-kpi label="Bayilerden alacağınız" :value="Money::format($totals['receivable'])" hint="Ödenmemiş toplam borç" />
        <x-kpi label="Vadesi geçen" :value="Money::format($totals['overdue'])" :href="route('business.accounts.index', ['view' => 'overdue'])" hint="Geciken bayileri listele" />
        <x-kpi label="Bu hafta ödenecek" :value="Money::format($totals['due_soon'])" hint="Son ödeme günü 7 gün içinde" />
    </div>

    <x-card :padding="false">
        <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-line p-4">
            <x-field name="q" label="Ara" :value="$filters['q'] ?? ''" placeholder="Bayi adı veya cari kodu" class="min-w-60 flex-1" />
            <x-field name="view" label="Göster" :options="['debt' => 'Borcu olanlar', 'overdue' => 'Vadesi geçenler', 'all' => 'Tümü']" :value="$view" />
            <x-button variant="secondary" icon="search">Filtrele</x-button>
        </form>
        @if ($accounts->isEmpty())
            <x-empty-state icon="check-circle" title="{{ $view === 'overdue' ? 'Vadesi geçmiş borç yok' : ($view === 'debt' ? 'Borcu olan bayi yok' : 'Gösterilecek cari hesap yok') }}" />
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr>
                        <th>Bayi</th>
                        <th class="hidden text-right md:table-cell">Toplam sipariş</th>
                        <th class="hidden text-right md:table-cell">Toplam ödeme</th>
                        <th class="text-right">Durum</th>
                    </tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($accounts as $account)
                            @php
                                $a = $aging[$account->id];
                                $s = $sums[$account->id] ?? ['debit' => '0.00', 'credit' => '0.00'];
                                $sign = bccomp((string) $account->balance, '0', 2);
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('business.accounts.show', $account) }}" class="font-medium hover:text-primary">{{ $account->relationship->dealer->legal_name }}</a>
                                    <p class="text-xs text-muted">{{ $account->code }}</p>
                                </td>
                                <td class="hidden text-right tabular-nums md:table-cell">{{ Money::format($s['debit']) }}</td>
                                <td class="hidden text-right tabular-nums text-emerald-700 md:table-cell">{{ Money::format($s['credit']) }}</td>
                                <td class="text-right">
                                    @if ($sign > 0)
                                        <span class="font-semibold tabular-nums">{{ Money::format($account->balance) }} borçlu</span>
                                        @if (bccomp($a['overdue'], '0', 2) > 0)
                                            <span class="block text-xs font-semibold text-danger">{{ Money::format($a['overdue']) }} vadesi geçti</span>
                                        @elseif (bccomp($a['due_soon'], '0', 2) > 0)
                                            <span class="block text-xs text-amber-700">{{ Money::format($a['due_soon']) }} bu hafta</span>
                                        @endif
                                    @elseif ($sign < 0)
                                        <x-badge tone="info">{{ Money::format(ltrim((string) $account->balance, '-')) }} fazla ödeme</x-badge>
                                    @else
                                        <x-badge tone="success">Hesap kapalı</x-badge>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
</x-layouts.panel>
