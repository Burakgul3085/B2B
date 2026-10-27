@php
    use App\Domain\Store\Reports\ReportCell as CellFormatter;
    use App\Domain\Store\Reports\StoreReportBranding as ReportBranding;
    $totalCards = collect($columns)->filter(fn ($c, $k) => isset($totals[$k]))->take(4);
@endphp
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>{{ $report->title() }} · {{ $company }}</title>
    <style>
        @page { margin: 16mm 13mm 17mm 13mm; }
        * { font-family: "DejaVu Sans", sans-serif; }
        body { font-size: 8.4px; color: #0f172a; margin: 0; line-height: 1.35; }

        /* Her sayfada: üstte işletme rengi şerit, altta künye (sayfa numarası PHP ile eklenir) */
        .stripe { position: fixed; top: -16mm; left: -13mm; right: -13mm; height: 5px; }
        .stripe td { height: 5px; padding: 0; }
        .footer { position: fixed; bottom: -11mm; left: 0; right: 0; height: 8mm; border-top: 0.6px solid #cbd5e1; padding-top: 5px;
                  font-size: 6.8px; color: #64748b; }
        .footer td { padding: 0; vertical-align: top; }

        /* Antet */
        .letterhead { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .letterhead td { vertical-align: top; padding: 0; }
        .monogram { border-collapse: collapse; }
        .monogram td { width: 40px; height: 40px; background: {{ $brand }}; color: #fff; font-size: 19px; font-weight: bold;
                       text-align: center; vertical-align: middle; border-radius: 6px; padding: 0; }
        .company { font-size: 12.5px; font-weight: bold; color: {{ $brand }}; margin: 1px 0 3px; }
        .contact { font-size: 7.2px; color: #64748b; line-height: 1.5; }
        .doc-kind { font-size: 7px; letter-spacing: 2.2px; color: #94a3b8; font-weight: bold; }
        .doc-title { font-size: 17px; font-weight: bold; color: #0f172a; margin: 3px 0 2px; }
        .doc-desc { font-size: 7.5px; color: #64748b; }
        .rule { height: 2px; background: {{ $brand }}; margin: 0 0 10px; }

        /* Bilgi şeridi */
        .info { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 10px; background: #f8fafc;
                border: 0.6px solid #e2e8f0; border-radius: 5px; }
        .info td { padding: 6px 9px; vertical-align: top; border-right: 0.6px solid #e2e8f0; }
        .info td:last-child { border-right: none; }
        .info .k { font-size: 6.5px; color: #64748b; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 2px; }
        .info .v { font-size: 8.6px; font-weight: bold; color: #0f172a; }
        .info .filters { font-weight: normal; font-size: 8px; }
        .info .flabel { color: #64748b; }
        .info .sep { color: #cbd5e1; padding: 0 6px; }
        .chip b { color: #475569; }

        /* Toplam kartları */
        .cards { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px 12px; }
        .card { border: 0.6px solid #e2e8f0; border-left: 3px solid {{ $brand }}; border-radius: 4px; padding: 6px 9px; background: #fff; }
        .card .k { font-size: 6.8px; color: #64748b; text-transform: uppercase; letter-spacing: 0.6px; }
        .card .v { font-size: 12px; font-weight: bold; color: #0f172a; margin-top: 2px; }

        /* Veri tablosu */
        table.data { width: 100%; border-collapse: collapse; }
        table.data thead { display: table-header-group; }
        table.data th { background: {{ $brand }}; color: #fff; text-align: left; padding: 6px 6px; font-size: 7.4px; font-weight: bold;
                        border-right: 0.6px solid rgba(255,255,255,0.25); }
        table.data td { padding: 4.5px 6px; border-bottom: 0.6px solid #e8edf3; vertical-align: top; }
        table.data tbody tr.alt td { background: #f8fafc; }
        table.data tr { page-break-inside: avoid; }
        table.data tr.total td { background: #eef2f7; font-weight: bold; border-top: 1.4px solid {{ $brand }};
                                 border-bottom: 1.4px solid {{ $brand }}; padding: 6px; }
        table.data .num { text-align: right; white-space: nowrap; }
        table.data .center { text-align: center; white-space: nowrap; }
        .dash { color: #94a3b8; }
        .empty { text-align: center; color: #64748b; padding: 22px 0 !important; font-style: italic; }
        .note { margin-top: 10px; font-size: 6.8px; color: #94a3b8; }
    </style>
</head>
<body>
    <table class="stripe" cellspacing="0" cellpadding="0" style="width: {{ count($columns) > 6 ? 297 : 210 }}mm"><tr>@foreach (ReportBranding::PUZZLE as $color)<td style="background:#{{ $color }}"></td>@endforeach</tr></table>
    <div class="footer">
        <table width="100%"><tr>
            <td style="width:40%">{{ $company }}</td>
            <td style="width:45%; text-align:center">{{ $report->title() }} · {{ $generatedAt }}</td>
            <td style="width:15%"></td>
        </tr></table>
    </div>

    <table class="letterhead">
        <tr>
            <td style="width:52px"><img src="{{ $logo }}" style="width:40px; height:40px"></td>
            <td>
                <div class="company">{{ $company }}</div>
                <div class="contact">@foreach ($contact as $line){{ $line }}@if (! $loop->last)<br>@endif @endforeach</div>
            </td>
            <td style="text-align:right; width:46%">
                <div class="doc-kind">RAPOR</div>
                <div class="doc-title">{{ $report->title() }}</div>
                <div class="doc-desc">{{ $report->description() }}</div>
            </td>
        </tr>
    </table>
    <div class="rule"></div>

    <table class="info">
        <tr>
            <td style="width:16%"><div class="k">Rapor tarihi</div><div class="v">{{ $generatedAt }}</div></td>
            <td style="width:12%"><div class="k">Kayıt sayısı</div><div class="v">{{ number_format($count, 0, ',', '.') }}</div></td>
            <td style="width:18%"><div class="k">Hazırlayan</div><div class="v">{{ $preparedBy }}</div></td>
            <td>
                <div class="k">Filtreler</div>
                <div class="v filters">
                    @forelse ($filterSummary as $label => $value)
                        <span class="flabel">{{ $label }}:</span> {{ $value }}@if (! $loop->last)<span class="sep">|</span>@endif
                    @empty
                        <span style="font-weight:normal; color:#64748b">Filtre uygulanmadı (tüm kayıtlar)</span>
                    @endforelse
                </div>
            </td>
        </tr>
    </table>

    @if ($totalCards->isNotEmpty() && $count > 0)
        <table class="cards">
            <tr>
                @foreach ($totalCards as $key => $col)
                    <td style="width:{{ floor(100 / max(3, $totalCards->count())) }}%"><div class="card">
                        <div class="k">{{ str_contains(mb_strtolower($col['label']), 'toplam') ? $col['label'] : 'Toplam '.mb_strtolower($col['label']) }}</div>
                        <div class="v">{{ CellFormatter::display($col['type'], $totals[$key]) }}</div>
                    </div></td>
                @endforeach
                @for ($i = $totalCards->count(); $i < 3; $i++)<td></td>@endfor
            </tr>
        </table>
    @endif

    <table class="data">
        <thead>
            <tr>
                @foreach ($columns as $col)
                    <th @class(['num' => CellFormatter::isNumeric($col['type']), 'center' => in_array($col['type'], ['date', 'datetime'], true)])>{{ $col['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @php $i = 0; @endphp
            @foreach ($rows as $row)
                <tr @class(['alt' => $i++ % 2 === 1])>
                    @foreach ($columns as $key => $col)
                        @php $value = $row->{$key} ?? null; @endphp
                        <td @class(['num' => CellFormatter::isNumeric($col['type']), 'center' => in_array($col['type'], ['date', 'datetime'], true)])>
                            @if ($value === null || $value === '')<span class="dash">—</span>@else{{ CellFormatter::display($col['type'], $value) }}@endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
            @if ($i === 0)
                <tr><td colspan="{{ count($columns) }}" class="empty">Seçilen filtrelerle kayıt bulunamadı.</td></tr>
            @elseif ($totals)
                <tr class="total">
                    @foreach ($columns as $key => $col)
                        <td @class(['num' => isset($totals[$key])])>{{ isset($totals[$key]) ? CellFormatter::display($col['type'], $totals[$key]) : ($loop->first ? 'TOPLAM' : '') }}</td>
                    @endforeach
                </tr>
            @endif
        </tbody>
    </table>

    <div class="note">{{ ReportBranding::NOTE }}</div>
</body>
</html>
