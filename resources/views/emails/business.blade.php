@php
    $accent = $business->accent_color && preg_match('/^#[0-9A-Fa-f]{6}$/', $business->accent_color) ? $business->accent_color : '#172554';
@endphp
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject }}</title>
</head>
<body style="margin:0;padding:0;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;padding:24px 12px;">
    <tr><td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;">
            <tr><td style="background:{{ $accent }};padding:18px 24px;border-radius:12px 12px 0 0;">
                @if ($business->logo_path)
                    <img src="{{ route('public.logo', $business->slug) }}" alt="{{ $business->display_name }}" height="36" style="display:block;height:36px;width:auto;background:#ffffff;border-radius:6px;padding:4px;">
                @else
                    <span style="color:#ffffff;font-size:17px;font-weight:bold;">{{ $business->display_name }}</span>
                @endif
            </td></tr>
            <tr><td style="padding:28px 24px;">
                <h1 style="margin:0 0 14px;font-size:20px;line-height:1.35;">{{ $subject }}</h1>
                <p style="margin:0 0 20px;font-size:15px;line-height:1.6;color:#334155;white-space:pre-line;">{{ $intro }}</p>
                @if (! empty($details))
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px;border:1px solid #e2e8f0;border-radius:8px;">
                        @foreach ($details as $label => $value)
                            <tr>
                                <td style="padding:8px 12px;font-size:13px;color:#64748b;border-bottom:1px solid #f1f5f9;">{{ $label }}</td>
                                <td style="padding:8px 12px;font-size:13px;font-weight:bold;text-align:right;border-bottom:1px solid #f1f5f9;">{{ $value }}</td>
                            </tr>
                        @endforeach
                    </table>
                @endif
                @if (! empty($action_url))
                    <p style="margin:0 0 16px;"><a href="{{ $action_url }}" style="display:inline-block;background:#1e3a8a;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:bold;font-size:15px;">{{ $action_label ?: 'Panele git' }}</a></p>
                    <p style="margin:0;font-size:12px;color:#64748b;word-break:break-all;">Buton çalışmazsa bu adresi tarayıcınıza yapıştırın:<br>{{ $action_url }}</p>
                @endif
            </td></tr>
            <tr><td style="padding:16px 24px;border-top:1px solid #e2e8f0;font-size:12px;line-height:1.6;color:#94a3b8;">
                {{ $business->legal_name }}@if ($business->phone) · {{ $business->phone }}@endif @if ($business->email) · {{ $business->email }}@endif<br>
                Bu e-posta {{ $business->display_name }} bayi sipariş sistemi tarafından gönderilmiştir. Parolanızı kimseyle paylaşmayın; {{ $business->display_name }} sizden asla parolanızı istemez.
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
