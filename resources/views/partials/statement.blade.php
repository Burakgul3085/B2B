{{-- Cari ekstre: $statement, $from, $to, $action (filtre formu hedefi), $orderRoute (sipariş linki için rota adı) --}}
@php use App\Domain\Support\Money; use App\Models\CurrentAccountMovement; @endphp
<form method="GET" action="{{ $action }}" class="flex flex-wrap items-end gap-3 border-b border-line p-4">
    <x-field name="from" label="Başlangıç" type="date" :value="$from" />
    <x-field name="to" label="Bitiş" type="date" :value="$to" />
    <x-button variant="secondary">Göster</x-button>
</form>
<div class="overflow-x-auto">
    <table class="table-base">
        <thead><tr><th>Tarih</th><th>İşlem</th><th class="hidden md:table-cell">Vade</th><th class="text-right">Borç</th><th class="text-right">Alacak</th><th class="text-right">Bakiye</th></tr></thead>
        <tbody class="divide-y divide-line">
            <tr class="bg-slate-50/60">
                <td class="text-muted tabular-nums">{{ \Illuminate\Support\Carbon::parse($from)->format('d.m.Y') }}</td>
                <td class="font-medium" colspan="4">Devreden bakiye</td>
                <td class="text-right font-medium tabular-nums">{{ Money::format($statement['opening']) }}</td>
            </tr>
            @forelse ($statement['rows'] as $row)
                @php $m = $row['movement']; @endphp
                <tr>
                    <td class="whitespace-nowrap text-muted tabular-nums">{{ $m->movement_date->format('d.m.Y') }}</td>
                    <td>
                        <span class="font-medium">{{ CurrentAccountMovement::TYPES[$m->type] }}</span>
                        <p class="text-xs text-muted">
                            @if ($m->order_id && $orderRoute)<a href="{{ route($orderRoute, $m->order_id) }}" class="text-primary hover:underline">{{ $m->description }}</a>@else{{ $m->description }}@endif
                        </p>
                    </td>
                    <td class="hidden text-muted tabular-nums md:table-cell">{{ $m->due_date?->format('d.m.Y') ?? '—' }}</td>
                    <td class="text-right tabular-nums">{{ (float) $m->debit > 0 ? Money::format($m->debit) : '' }}</td>
                    <td class="text-right tabular-nums">{{ (float) $m->credit > 0 ? Money::format($m->credit) : '' }}</td>
                    <td class="text-right font-medium tabular-nums">{{ Money::format($row['balance']) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-6 text-center text-sm text-muted">Bu tarih aralığında hareket yok.</td></tr>
            @endforelse
        </tbody>
        <tfoot class="border-t-2 border-line text-sm font-semibold">
            <tr>
                <td colspan="3" class="px-4 py-3">Dönem toplamı / kapanış bakiyesi</td>
                <td class="px-4 py-3 text-right tabular-nums">{{ Money::format($statement['debit_total']) }}</td>
                <td class="px-4 py-3 text-right tabular-nums">{{ Money::format($statement['credit_total']) }}</td>
                <td class="px-4 py-3 text-right tabular-nums">{{ Money::format($statement['closing']) }}</td>
            </tr>
        </tfoot>
    </table>
</div>
<p class="border-t border-line px-4 py-3 text-xs text-muted">Bakiye pozitifse bayi borçludur. Bu ekstre ticari takip içindir; resmî muhasebe kaydı değildir.</p>
