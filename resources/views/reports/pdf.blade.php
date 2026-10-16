@php use App\Domain\Reporting\CellFormatter; @endphp
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>{{ $report->title() }}</title>
    <style>
        * { font-family: "DejaVu Sans", sans-serif; }
        body { font-size: 9px; color: #0f172a; margin: 0; }
        .head { border-bottom: 2px solid #1e3a8a; padding-bottom: 8px; margin-bottom: 10px; }
        .head td { vertical-align: middle; }
        h1 { font-size: 15px; margin: 0 0 2px; }
        .muted { color: #64748b; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #f1f5f9; text-align: left; padding: 5px 6px; font-size: 8.5px; border-bottom: 1px solid #cbd5e1; }
        table.data td { padding: 4px 6px; border-bottom: 1px solid #e2e8f0; }
        .num { text-align: right; white-space: nowrap; }
        tfoot td { font-weight: bold; border-top: 2px solid #1e3a8a; background: #f8fafc; }
        .filters { margin: 0 0 10px; }
        .filters span { display: inline-block; margin-right: 12px; }
        .footer { position: fixed; bottom: -10px; left: 0; right: 0; font-size: 7.5px; color: #94a3b8; }
    </style>
</head>
<body>
    <table class="head" width="100%">
        <tr>
            <td>
                <h1>{{ $report->title() }}</h1>
                <div class="muted">{{ $business->legal_name }}</div>
            </td>
            <td style="text-align:right">
                @if ($logo)<img src="{{ $logo }}" style="height:36px"><br>@endif
                <span class="muted">Rapor tarihi: {{ now()->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}</span>
            </td>
        </tr>
    </table>

    @if ($filterSummary)
        <div class="filters muted">@foreach ($filterSummary as $label => $value)<span><b>{{ $label }}:</b> {{ $value }}</span>@endforeach</div>
    @endif

    <table class="data">
        <thead><tr>@foreach ($columns as $col)<th @class(['num' => CellFormatter::isNumeric($col['type'])])>{{ $col['label'] }}</th>@endforeach</tr></thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>@foreach ($columns as $key => $col)<td @class(['num' => CellFormatter::isNumeric($col['type'])])>{{ CellFormatter::display($col['type'], $row->{$key} ?? null) }}</td>@endforeach</tr>
            @endforeach
        </tbody>
        @if ($totals)
            <tfoot><tr>
                @foreach ($columns as $key => $col)
                    <td @class(['num' => isset($totals[$key])])>{{ isset($totals[$key]) ? CellFormatter::display($col['type'], $totals[$key]) : ($loop->first ? 'TOPLAM' : '') }}</td>
                @endforeach
            </tr></tfoot>
        @endif
    </table>

    <div class="footer">{{ $business->display_name }} · Bu rapor ticari takip içindir; resmî muhasebe kaydı değildir.</div>
</body>
</html>
