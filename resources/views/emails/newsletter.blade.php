{{-- E-bülten e-postası: $campaign, $body (Markdown'dan güvenli HTML), $imageUrl, $unsubscribeUrl, $settings. Stiller e-posta istemcileri için satır içi. --}}
@php
    $name = config('store.name');
    $socials = \App\Domain\Store\Content\SocialLinks::filled();
@endphp
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $campaign->subject }}</title>
</head>
<body style="margin:0;padding:0;background:#eef1f7;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
@if ($campaign->preheader)
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">{{ $campaign->preheader }}</div>
@endif
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef1f7;padding:28px 12px;">
    <tr><td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:16px;overflow:hidden;">
            <tr><td style="background:#0b1631;padding:22px 28px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
                    <td style="font-size:20px;font-weight:bold;color:#ffffff;">
                        <a href="{{ route('store.home') }}" style="color:#ffffff;text-decoration:none;">{{ $name }}</a>
                    </td>
                    <td align="right" style="white-space:nowrap;">
                        <span style="display:inline-block;width:10px;height:10px;border-radius:3px;background:#f97316;"></span>
                        <span style="display:inline-block;width:10px;height:10px;border-radius:3px;background:#22c55e;"></span>
                        <span style="display:inline-block;width:10px;height:10px;border-radius:3px;background:#3b82f6;"></span>
                        <span style="display:inline-block;width:10px;height:10px;border-radius:3px;background:#facc15;"></span>
                    </td>
                </tr></table>
            </td></tr>
            @if ($imageUrl)
                <tr><td><a href="{{ $campaign->button_url ?: route('store.home') }}"><img src="{{ $imageUrl }}" alt="" width="600" style="display:block;width:100%;height:auto;border:0;"></a></td></tr>
            @endif
            <tr><td style="padding:30px 28px 8px;">
                <h1 style="margin:0 0 16px;font-size:24px;line-height:1.3;color:#0b1631;">{{ $campaign->subject }}</h1>
                <div style="font-size:15px;line-height:1.7;color:#334155;">{!! $body !!}</div>
            </td></tr>
            @if ($campaign->button_url)
                <tr><td style="padding:12px 28px 30px;">
                    <a href="{{ $campaign->button_url }}" style="display:inline-block;background:#f97316;color:#ffffff;text-decoration:none;padding:14px 26px;border-radius:10px;font-weight:bold;font-size:15px;">{{ $campaign->button_label ?: 'Alışverişe başla' }}</a>
                </td></tr>
            @endif
            <tr><td style="padding:22px 28px;background:#f8fafc;border-top:1px solid #e2e8f0;font-size:12px;line-height:1.6;color:#64748b;">
                <strong style="color:#0f172a;">{{ $settings['company_title'] ?: $name }}</strong><br>
                @if ($settings['address']){{ $settings['address'] }}<br>@endif
                @if ($settings['phone']){{ $settings['phone'] }}@endif @if ($settings['email']) · <a href="mailto:{{ $settings['email'] }}" style="color:#64748b;">{{ $settings['email'] }}</a>@endif
                @if ($socials)
                    <br>@foreach ($socials as $social)<a href="{{ $social['url'] }}" style="color:#1e3a8a;text-decoration:none;">{{ $social['label'] }}</a>@if (! $loop->last) · @endif @endforeach
                @endif
                <br><br>Bu e-postayı {{ $name }} e-bültenine abone olduğunuz için aldınız.
                <a href="{{ $unsubscribeUrl }}" style="color:#1e3a8a;">Abonelikten çık</a>
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
