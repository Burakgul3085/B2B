@php
    use App\Domain\Support\Money;
    $qty = fn ($v) => str_replace('.', ',', rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.'));
    $rate = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ',');
@endphp
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->invoice_no ?: 'Fatura önizleme' }} · {{ $seller }}</title>
    <style>
        @page { margin: 15mm 13mm; }
        * { font-family: "DejaVu Sans", sans-serif; }
        body { font-size: 8.6px; color: #0f172a; margin: 0; line-height: 1.4; }
        .watermark { position: fixed; top: 42%; left: -10mm; right: -10mm; text-align: center; font-size: 34px; font-weight: bold;
                     color: rgba(220, 38, 38, 0.13); transform: rotate(-24deg); }
        table { border-collapse: collapse; }
        .head { width: 100%; margin-bottom: 12px; }
        .head td { vertical-align: top; padding: 0; }
        .company { font-size: 12.5px; font-weight: bold; color: {{ $brand }}; margin-bottom: 3px; }
        .muted { color: #64748b; }
        .small { font-size: 7.4px; }
        .doc { text-align: right; }
        .doc .kind { font-size: 7px; letter-spacing: 2px; color: #94a3b8; font-weight: bold; }
        .doc .title { font-size: 17px; font-weight: bold; margin: 2px 0; }
        .rule { height: 2px; background: {{ $brand }}; margin: 0 0 10px; }
        .boxes { width: 100%; margin-bottom: 12px; }
        .boxes td { width: 50%; vertical-align: top; padding: 0; }
        .box { border: 0.6px solid #e2e8f0; border-radius: 5px; padding: 8px 10px; background: #f8fafc; }
        .box .k { font-size: 6.6px; color: #64748b; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 3px; }
        .box .v { font-weight: bold; font-size: 9.4px; }
        .meta td { padding: 1.5px 8px 1.5px 0; }
        table.lines { width: 100%; }
        table.lines th { background: {{ $brand }}; color: #fff; text-align: left; padding: 6px; font-size: 7.4px; }
        table.lines td { padding: 5px 6px; border-bottom: 0.6px solid #e8edf3; vertical-align: top; }
        .r, table.lines th.r { text-align: right; }
        .totals { width: 46%; margin-left: 54%; margin-top: 10px; }
        .totals td { padding: 3px 6px; }
        .totals .grand td { border-top: 1.2px solid {{ $brand }}; font-size: 11px; font-weight: bold; padding-top: 5px; }
        .note { margin-top: 14px; border-top: 0.6px solid #e2e8f0; padding-top: 6px; }
    </style>
</head>
<body>
    @if ($watermark)<div class="watermark">{{ $watermark }}</div>@endif

    <table class="head">
        <tr>
            <td>
                @if ($logo)<img src="{{ $logo }}" alt="" style="height: 34px; margin-bottom: 5px;">@endif
                <div class="company">{{ $seller }}</div>
                @foreach ($sellerLines as $line)<div class="small muted">{{ $line }}</div>@endforeach
            </td>
            <td class="doc">
                <div class="kind">{{ ['e_arsiv' => 'E-ARŞİV', 'e_fatura' => 'E-FATURA'][$invoice->document_type] ?? 'E-ARŞİV / E-FATURA' }}</div>
                <div class="title">{{ $invoice->kind === 'refund' ? 'İADE FATURASI' : 'FATURA' }}</div>
                <table class="meta" style="margin-left: auto;">
                    <tr><td class="muted">Fatura no</td><td><b>{{ $invoice->invoice_no ?: '—' }}</b></td></tr>
                    <tr><td class="muted">Fatura tarihi</td><td>{{ $invoice->issue_date->format('d.m.Y') }}</td></tr>
                    <tr><td class="muted">Sipariş no</td><td>{{ $invoice->order->order_no }}</td></tr>
                    @if ($invoice->ettn)<tr><td class="muted">ETTN</td><td class="small">{{ $invoice->ettn }}</td></tr>@endif
                </table>
            </td>
        </tr>
    </table>
    <div class="rule"></div>

    <table class="boxes">
        <tr>
            <td style="padding-right: 6px;">
                <div class="box">
                    <div class="k">Sayın</div>
                    <div class="v">{{ $invoice->buyer_name }}</div>
                    <div>{{ $invoice->buyer_address }}</div>
                    @unless (mb_stripos($invoice->buyer_address, $invoice->buyer_city) !== false)<div>{{ trim(($invoice->buyer_district ? $invoice->buyer_district.' / ' : '').$invoice->buyer_city) }}</div>@endunless
                    <div class="small muted" style="margin-top: 3px;">
                        {{ $invoice->buyer_type === 'corporate' ? 'VKN: '.$invoice->buyer_tax_number.($invoice->buyer_tax_office ? ' · '.$invoice->buyer_tax_office : '') : 'TCKN: '.($invoice->buyer_tax_number ?: '11111111111') }}
                        @if ($invoice->buyer_email) · {{ $invoice->buyer_email }}@endif
                    </div>
                </div>
            </td>
            <td style="padding-left: 6px;">
                <div class="box">
                    <div class="k">Ödeme ve teslimat</div>
                    <div>{{ \App\Models\Store\StoreOrder::PAYMENT_METHODS[$invoice->order->payment_method] ?? $invoice->order->payment_method }}</div>
                    <div>{{ \App\Models\Store\StoreOrder::DELIVERY_METHODS[$invoice->order->delivery_method] ?? $invoice->order->delivery_method }}</div>
                    <div class="small muted" style="margin-top: 3px;">{{ config('store.display_domain') }} üzerinden internet satışı</div>
                </div>
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead><tr><th style="width: 4%;">#</th><th>Mal / hizmet</th><th class="r">Miktar</th><th class="r">Birim fiyat</th><th class="r">İndirim</th><th class="r">KDV</th><th class="r">KDV tutarı</th><th class="r">Tutar</th></tr></thead>
        <tbody>
            @foreach ($invoice->lines as $i => $line)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $line->name }}@if ($line->sku)<div class="small muted">{{ $line->sku }}</div>@endif</td>
                    <td class="r">{{ $qty($line->quantity) }} Adet</td>
                    <td class="r">{{ Money::format($line->unit_price) }}</td>
                    <td class="r">{{ bccomp((string) $line->discount, '0', 2) > 0 ? Money::format($line->discount) : '—' }}</td>
                    <td class="r">%{{ $rate($line->vat_rate) }}</td>
                    <td class="r">{{ Money::format($line->vat_amount) }}</td>
                    <td class="r">{{ Money::format($line->line_total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td class="muted">Mal / hizmet toplamı (KDV hariç)</td><td class="r">{{ Money::format($totals['subtotal']) }}</td></tr>
        @foreach ($totals['by_rate'] as $r => $sum)
            <tr><td class="muted">Hesaplanan KDV (%{{ $rate($r) }})</td><td class="r">{{ Money::format($sum['vat']) }}</td></tr>
        @endforeach
        <tr class="grand"><td>Ödenecek tutar</td><td class="r">{{ Money::format($totals['total']) }}</td></tr>
    </table>

    <div class="note small muted">
        @if ($invoice->note){{ $invoice->note }}<br>@endif
        Birim fiyatlar KDV dahildir.
    </div>
</body>
</html>
