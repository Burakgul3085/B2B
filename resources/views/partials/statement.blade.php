{{-- Cari ekstre: $statement, $from, $to, $action (filtre formu hedefi), $orderRoute (sipariş linki için rota adı),
     $forDealer (bayi görünümü: sade dil). Bakiye işaretsiz yazılır, yanında B (borç) / A (alacak) etiketi bulunur. --}}
@php
    use App\Domain\Support\Money;
    use App\Models\CurrentAccountMovement;
    use Illuminate\Support\Carbon;

    $forDealer ??= false;
    $labels = $forDealer
        ? ['collection' => 'Ödeme', 'adjustment_debit' => 'Düzeltme (borç)', 'adjustment_credit' => 'Düzeltme (alacak)', 'reversal' => 'İptal kaydı'] + CurrentAccountMovement::TYPES
        : CurrentAccountMovement::TYPES;
    $presets = [
        'Bu ay' => [now()->startOfMonth(), now()],
        'Son 3 ay' => [now()->subMonths(3)->startOfMonth(), now()],
        'Bu yıl' => [now()->startOfYear(), now()],
    ];
@endphp
<div class="flex flex-wrap items-end justify-between gap-3 border-b border-line p-4 print:hidden">
    <form method="GET" action="{{ $action }}" class="flex flex-wrap items-end gap-3">
        <x-field name="from" label="Başlangıç" type="date" :value="$from" />
        <x-field name="to" label="Bitiş" type="date" :value="$to" />
        <x-button variant="secondary">Göster</x-button>
    </form>
    <div class="flex flex-wrap items-center gap-1.5">
        @foreach ($presets as $label => [$pFrom, $pTo])
            @php $active = $from === $pFrom->toDateString() && $to === $pTo->toDateString(); @endphp
            <a href="{{ $action }}?{{ http_build_query(['from' => $pFrom->toDateString(), 'to' => $pTo->toDateString()]) }}" @class([
                'rounded-full px-3 py-1.5 text-xs font-semibold transition',
                'bg-primary text-white' => $active,
                'bg-slate-100 text-ink hover:bg-slate-200' => ! $active,
            ])>{{ $label }}</a>
        @endforeach
        <button type="button" data-print class="ml-1 inline-flex items-center gap-1.5 rounded-full border border-line px-3 py-1.5 text-xs font-semibold text-ink hover:bg-slate-50">
            <x-icon name="file" class="size-3.5" />Yazdır
        </button>
    </div>
</div>
<p class="hidden px-1 pb-3 text-sm print:block">Dönem: {{ Carbon::parse($from)->format('d.m.Y') }} – {{ Carbon::parse($to)->format('d.m.Y') }}</p>
<div class="overflow-x-auto">
    <table class="table-base">
        <thead>
            <tr>
                <th>Tarih</th>
                <th>İşlem</th>
                <th class="hidden md:table-cell">{{ $forDealer ? 'Son ödeme günü' : 'Vade' }}</th>
                <th class="text-right">Borç @if ($forDealer)<span class="block text-[10px] font-medium normal-case tracking-normal text-muted">Alımlarınız</span>@endif</th>
                <th class="text-right">Alacak @if ($forDealer)<span class="block text-[10px] font-medium normal-case tracking-normal text-muted">Ödeme ve iadeler</span>@endif</th>
                <th class="text-right">Bakiye</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-line">
            <tr class="bg-slate-50/60">
                <td class="text-muted tabular-nums">{{ Carbon::parse($from)->format('d.m.Y') }}</td>
                <td class="font-medium" colspan="4">Devreden bakiye @if ($forDealer)<span class="block text-xs font-normal text-muted">Bu tarihten önceki hareketlerin toplamı</span>@endif</td>
                <td class="text-right font-medium tabular-nums">@include('partials._balance', ['amount' => $statement['opening']])</td>
            </tr>
            @forelse ($statement['rows'] as $row)
                @php
                    $m = $row['movement'];
                    $note = $forDealer ? preg_replace(['/^Tahsilat — /u', '/^Sipariş (?=SIP-)/u'], '', (string) $m->description) : $m->description;
                @endphp
                <tr>
                    <td class="whitespace-nowrap text-muted tabular-nums">{{ $m->movement_date->format('d.m.Y') }}</td>
                    <td>
                        <span class="font-medium">{{ $labels[$m->type] ?? $m->type }}</span>
                        <p class="text-xs text-muted">
                            @if ($m->order_id && $orderRoute)<a href="{{ route($orderRoute, $m->order_id) }}" class="text-primary hover:underline">{{ $note }}</a>@else{{ $note }}@endif
                        </p>
                    </td>
                    <td class="hidden text-muted tabular-nums md:table-cell">{{ $m->due_date?->format('d.m.Y') ?? '—' }}</td>
                    <td class="text-right tabular-nums">{{ (float) $m->debit > 0 ? Money::format($m->debit) : '' }}</td>
                    <td class="text-right tabular-nums text-emerald-700">{{ (float) $m->credit > 0 ? Money::format($m->credit) : '' }}</td>
                    <td class="text-right font-medium tabular-nums">@include('partials._balance', ['amount' => $row['balance']])</td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-6 text-center text-sm text-muted">Bu tarih aralığında hareket yok.</td></tr>
            @endforelse
        </tbody>
        <tfoot class="border-t-2 border-line text-sm font-semibold">
            <tr>
                <td colspan="3" class="px-4 py-3">{{ $forDealer ? 'Dönem toplamı / dönem sonu bakiyeniz' : 'Dönem toplamı / kapanış bakiyesi' }}</td>
                <td class="px-4 py-3 text-right tabular-nums">{{ Money::format($statement['debit_total']) }}</td>
                <td class="px-4 py-3 text-right tabular-nums text-emerald-700">{{ Money::format($statement['credit_total']) }}</td>
                <td class="px-4 py-3 text-right tabular-nums">@include('partials._balance', ['amount' => $statement['closing']])</td>
            </tr>
        </tfoot>
    </table>
</div>
<p class="flex flex-wrap gap-x-5 gap-y-1 border-t border-line px-4 py-3 text-xs text-muted">
    <span><b class="font-semibold text-ink">B</b> = {{ $forDealer ? 'Borçlusunuz (toptancınıza ödenecek tutar)' : 'Bayi borçlu' }}</span>
    <span><b class="font-semibold text-emerald-700">A</b> = {{ $forDealer ? 'Alacaklısınız (fazla ödeme veya iade)' : 'Bayi alacaklı' }}</span>
</p>
