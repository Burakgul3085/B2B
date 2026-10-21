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
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;">
            <tr><td style="background:#172554;color:#ffffff;padding:18px 24px;border-radius:12px 12px 0 0;font-size:16px;font-weight:bold;">{{ $brand ?? config('app.name') }}</td></tr>
            <tr><td style="padding:28px 24px;">
                <h1 style="margin:0 0 12px;font-size:20px;">{{ $title }}</h1>
                <p style="margin:0 0 20px;font-size:15px;line-height:1.6;color:#334155;">{{ $intro }}</p>
                @if (! empty($rows))
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px;border-collapse:collapse;font-size:14px;">
                        @foreach ($rows as [$label, $value])
                            <tr>
                                <td style="padding:8px 0;border-bottom:1px solid #e2e8f0;color:#334155;">{{ $label }}</td>
                                <td style="padding:8px 0;border-bottom:1px solid #e2e8f0;text-align:right;white-space:nowrap;font-weight:bold;">{{ $value }}</td>
                            </tr>
                        @endforeach
                    </table>
                @endif
                @isset($action_url)
                    <p style="margin:0 0 20px;"><a href="{{ $action_url }}" style="display:inline-block;background:#1e3a8a;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:bold;font-size:15px;">{{ $action_label ?? 'Devam et' }}</a></p>
                    <p style="margin:0 0 20px;font-size:12px;color:#64748b;word-break:break-all;">Buton çalışmazsa bu adresi tarayıcınıza yapıştırın:<br>{{ $action_url }}</p>
                @endisset
                @isset($outro)<p style="margin:0;font-size:13px;color:#64748b;">{{ $outro }}</p>@endisset
            </td></tr>
            <tr><td style="padding:16px 24px;border-top:1px solid #e2e8f0;font-size:12px;color:#94a3b8;">Bu e-posta otomatik gönderilmiştir. Lütfen yanıtlamayın.</td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
