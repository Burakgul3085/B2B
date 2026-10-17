@php
    /*
     * Kurumsal e-posta düzeni. E-posta istemcileri için tablo tabanlı ve satır içi stillidir.
     * Logo e-postaya gömülüdür (cid); önizlemede genel logo adresi kullanılır.
     */
    $accent = $business->accent_color && preg_match('/^#[0-9A-Fa-f]{6}$/', $business->accent_color) ? $business->accent_color : '#172554';
    $logoSrc = $logo_src ?? ($business->logo_path ? route('public.logo', $business->slug) : null);
    $logoHeight = $logo_height ?? 48;
    $logoWidth = $logo_width ?? null;
    $logoIsDefault = $logo_is_default ?? ! $business->logo_path;
    $eyebrow = $eyebrow ?? null;
    $note = $note ?? null;
    $details = $details ?? [];
    $preheader = \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', $intro), 110);
    $address = trim(implode(', ', array_filter([$business->address, $business->district, $business->city])));
    $font = "'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif";
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

            {{-- Başlık: logo ve işletme adı --}}
            <tr><td style="background:#ffffff;border-radius:16px 16px 0 0;border:1px solid #e2e8f0;border-bottom:0;" class="px">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="padding:26px 36px 22px;" class="px">
                            <table role="presentation" cellpadding="0" cellspacing="0"><tr>
                                @if ($logoSrc)
                                    <td valign="middle" style="padding-right:{{ $logoIsDefault ? '14px' : '0' }};">
                                        <img src="{{ $logoSrc }}" alt="{{ $business->display_name }}" height="{{ $logoHeight }}" @if ($logoWidth) width="{{ $logoWidth }}" @endif
                                            style="display:block;height:{{ $logoHeight }}px;@if ($logoWidth) width:{{ $logoWidth }}px;@endif max-width:260px;border:0;outline:none;">
                                    </td>
                                @endif
                                @if ($logoIsDefault || ! $logoSrc)
                                    <td valign="middle" style="font-family:{{ $font }};">
                                        <div style="font-size:19px;font-weight:700;color:#0f172a;line-height:1.2;">{{ $business->display_name }}</div>
                                        <div style="font-size:12px;color:#64748b;margin-top:3px;">Bayi sipariş platformu</div>
                                    </td>
                                @endif
                            </tr></table>
                        </td>
                    </tr>
                </table>
            </td></tr>

            {{-- Yapboz renklerinde ince şerit --}}
            <tr><td style="font-size:0;line-height:0;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
                    <td height="4" style="background:#f97316;height:4px;font-size:0;line-height:0;">&nbsp;</td>
                    <td height="4" style="background:#0ea5e9;height:4px;font-size:0;line-height:0;">&nbsp;</td>
                    <td height="4" style="background:#22c55e;height:4px;font-size:0;line-height:0;">&nbsp;</td>
                    <td height="4" style="background:#facc15;height:4px;font-size:0;line-height:0;">&nbsp;</td>
                </tr></table>
            </td></tr>

            {{-- İçerik --}}
            <tr><td style="background:#ffffff;border:1px solid #e2e8f0;border-top:0;border-bottom:0;padding:34px 36px 8px;" class="px">
                @if ($eyebrow)
                    <div style="display:inline-block;background:#f1f5f9;color:{{ $accent }};font-size:11px;font-weight:700;letter-spacing:1.4px;text-transform:uppercase;padding:6px 11px;border-radius:999px;margin-bottom:16px;">{{ $eyebrow }}</div>
                @endif
                <h1 class="h1" style="margin:0 0 16px;font-size:25px;line-height:1.3;font-weight:700;color:#0f172a;letter-spacing:-0.3px;">{{ $subject }}</h1>
                <div style="font-size:15px;line-height:1.75;color:#334155;white-space:pre-line;">{{ $intro }}</div>

                @if (! empty($details))
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:26px 0 4px;border:1px solid #e2e8f0;border-radius:12px;border-collapse:separate;overflow:hidden;">
                        <tr><td colspan="2" style="background:#f8fafc;padding:11px 18px;font-size:11px;font-weight:700;letter-spacing:1.2px;text-transform:uppercase;color:#64748b;border-bottom:1px solid #e2e8f0;">Özet</td></tr>
                        @foreach ($details as $label => $value)
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
                            <a href="{{ $action_url }}" style="display:inline-block;padding:15px 30px;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:10px;">{{ $action_label ?: 'Panele git' }} &rarr;</a>
                        </td></tr>
                    </table>
                    <p style="margin:12px 0 0;font-size:12px;line-height:1.6;color:#94a3b8;word-break:break-all;">Düğme çalışmazsa bu adresi tarayıcınıza yapıştırın:<br><a href="{{ $action_url }}" style="color:#64748b;">{{ $action_url }}</a></p>
                @endif

                @if ($note)
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:26px 0 0;">
                        <tr><td style="background:#f8fafc;border-left:4px solid {{ $accent }};border-radius:0 10px 10px 0;padding:14px 18px;font-size:13px;line-height:1.6;color:#475569;">
                            <strong style="color:#0f172a;">Bilgi:</strong> {{ $note }}
                        </td></tr>
                    </table>
                @endif
            </td></tr>

            {{-- Yardım --}}
            <tr><td style="background:#ffffff;border:1px solid #e2e8f0;border-top:0;border-radius:0 0 16px 16px;padding:26px 36px 30px;" class="px">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-top:1px solid #e2e8f0;">
                    <tr><td style="padding-top:22px;font-size:13px;line-height:1.7;color:#475569;">
                        <strong style="color:#0f172a;font-size:14px;">Yardıma mı ihtiyacınız var?</strong><br>
                        Bu e-postayı yanıtlayarak bize doğrudan ulaşabilirsiniz.
                        @if ($business->phone)<br>Telefon: <a href="tel:{{ preg_replace('/[^0-9+]/', '', $business->phone) }}" style="color:{{ $accent }};text-decoration:none;font-weight:600;">{{ $business->phone }}</a>@endif
                        @if ($business->email)<br>E-posta: <a href="mailto:{{ $business->email }}" style="color:{{ $accent }};text-decoration:none;font-weight:600;">{{ $business->email }}</a>@endif
                    </td></tr>
                </table>
            </td></tr>

            {{-- Alt bilgi --}}
            <tr><td align="center" style="padding:24px 24px 8px;font-size:12px;line-height:1.7;color:#94a3b8;">
                <strong style="color:#64748b;">{{ $business->legal_name ?: $business->display_name }}</strong>
                @if ($address)<br>{{ $address }}@endif
                @if ($business->tax_office && $business->tax_number)<br>{{ $business->tax_office }} V.D. · {{ $business->tax_number }}@endif
                <br><br>
                Bu e-posta {{ $business->display_name }} bayi sipariş sistemi tarafından gönderilmiştir.<br>
                Güvenliğiniz için parolanızı kimseyle paylaşmayın; {{ $business->display_name }} sizden asla parolanızı istemez.
                <br><br>© {{ now()->year }} {{ $business->display_name }}
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
