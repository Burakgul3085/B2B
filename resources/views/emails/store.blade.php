@php
    /*
     * Mağaza e-postası (sipariş, bildirim, stok, e-bülten onayı): B2B e-postalarıyla (emails.business) aynı kurumsal düzen.
     * E-posta istemcileri için tablo tabanlı ve satır içi stillidir. Logo e-postaya gömülüdür (cid); önizlemede data: adresi.
     * İçerik: $title, $intro, $rows (list<[etiket, değer]>), $action_url/$action_label, $eyebrow, $tagline, $outro, $documents.
     */
    $accent = '#172554';
    $font = "'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif";
    $storeName = config('store.name');
    $settings = \App\Models\Store\StoreSetting::values();
    $logoSrc = $logo_src ?? null;
    $logoHeight = $logo_height ?? 48;
    $tagline = $tagline ?? 'Online kırtasiye mağazası';
    $eyebrow = $eyebrow ?? null;
    $rows = $rows ?? [];
    $title = $title ?? $subject;
    $preheader = \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', $intro), 110);
    $footerNote = $footer_note ?? "Bu e-posta {$storeName} tarafından gönderilmiştir.";
@endphp
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light">
    <title>{{ $subject }}</title>
    <style>
        @media (max-width: 620px) {
            .px { padding-left: 22px !important; padding-right: 22px !important; }
            .h1 { font-size: 22px !important; }
            .btn a { display: block !important; text-align: center !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background:#eef2f7;font-family:{{ $font }};color:#0f172a;-webkit-font-smoothing:antialiased;">
{{-- Gelen kutusunda konunun yanında görünen önizleme metni --}}
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:#eef2f7;">{{ $preheader }}&#8203;&nbsp;&#8203;&nbsp;&#8203;&nbsp;</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef2f7;">
    <tr><td align="center" style="padding:32px 12px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:620px;">

            @include('emails.store.header')

            {{-- İçerik --}}
            <tr><td style="background:#ffffff;border:1px solid #e2e8f0;border-top:0;border-bottom:0;padding:34px 36px 8px;" class="px">
                @if ($eyebrow)
                    <div style="display:inline-block;background:#f1f5f9;color:{{ $accent }};font-size:11px;font-weight:700;letter-spacing:1.4px;text-transform:uppercase;padding:6px 11px;border-radius:999px;margin-bottom:16px;">{{ $eyebrow }}</div>
                @endif
                <h1 class="h1" style="margin:0 0 16px;font-size:25px;line-height:1.3;font-weight:700;color:#0f172a;letter-spacing:-0.3px;">{{ $title }}</h1>
                <div style="font-size:15px;line-height:1.75;color:#334155;white-space:pre-line;">{{ $intro }}</div>
                @if (! empty($code))@include('emails.partials.code', ['code' => $code, 'accent' => $accent])@endif

                @if (! empty($rows))
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:26px 0 4px;border:1px solid #e2e8f0;border-radius:12px;border-collapse:separate;overflow:hidden;">
                        <tr><td colspan="2" style="background:#f8fafc;padding:11px 18px;font-size:11px;font-weight:700;letter-spacing:1.2px;text-transform:uppercase;color:#64748b;border-bottom:1px solid #e2e8f0;">Özet</td></tr>
                        @foreach ($rows as [$label, $value])
                            <tr>
                                <td style="padding:12px 18px;font-size:14px;color:#64748b;@unless ($loop->last) border-bottom:1px solid #f1f5f9; @endunless">{{ $label }}</td>
                                <td style="padding:12px 18px;font-size:14px;font-weight:700;color:#0f172a;text-align:right;@unless ($loop->last) border-bottom:1px solid #f1f5f9; @endunless">{{ $value }}</td>
                            </tr>
                        @endforeach
                    </table>
                @endif

                @if (! empty($action_url))
                    <table role="presentation" cellpadding="0" cellspacing="0" class="btn" style="margin:28px 0 6px;">
                        <tr><td style="border-radius:10px;background:{{ $accent }};box-shadow:0 6px 16px rgba(15,23,42,0.18);">
                            <a href="{{ $action_url }}" style="display:inline-block;padding:15px 30px;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:10px;">{{ $action_label ?? 'Devam et' }} &rarr;</a>
                        </td></tr>
                    </table>
                    <p style="margin:12px 0 0;font-size:12px;line-height:1.6;color:#94a3b8;word-break:break-all;">Düğme çalışmazsa bu adresi tarayıcınıza yapıştırın:<br><a href="{{ $action_url }}" style="color:#64748b;">{{ $action_url }}</a></p>
                @endif

                @if (! empty($outro))
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:26px 0 0;">
                        <tr><td style="background:#f8fafc;border-left:4px solid {{ $accent }};border-radius:0 10px 10px 0;padding:14px 18px;font-size:13px;line-height:1.6;color:#475569;">
                            <strong style="color:#0f172a;">Bilgi:</strong> {{ $outro }}
                        </td></tr>
                    </table>
                @endif

                {{-- Ek belgeler (ör. onaylanan Mesafeli Satış Sözleşmesi): HTML, sunucuda Markdown'dan üretilir; kullanıcı HTML'i içermez. --}}
                @foreach ($documents ?? [] as $doc)
                    <h2 style="margin:30px 0 8px;padding-top:22px;border-top:1px solid #e2e8f0;font-size:16px;color:#0f172a;">{{ $doc['title'] }}</h2>
                    <div style="font-size:13px;line-height:1.6;color:#334155;">{!! $doc['html'] !!}</div>
                @endforeach
            </td></tr>

            @include('emails.store.footer')
        </table>
    </td></tr>
</table>
</body>
</html>
