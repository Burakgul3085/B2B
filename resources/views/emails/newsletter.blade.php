{{-- E-bülten e-postası: $campaign, $body (Markdown'dan güvenli HTML), $imageUrl, $unsubscribeUrl, $logo_src, $logo_height. Başlık ve alt bilgi diğer mağaza e-postalarıyla (emails.store) ortaktır. Stiller e-posta istemcileri için satır içi. --}}
@php
    $accent = '#172554';
    $font = "'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif";
    $storeName = config('store.name');
    $settings = \App\Models\Store\StoreSetting::values();
    $logoSrc = $logo_src ?? null;
    $logoHeight = $logo_height ?? 48;
    $tagline = 'Online kırtasiye mağazası';
    $footerNote = "Bu e-postayı {$storeName} e-bültenine abone olduğunuz için aldınız.";
@endphp
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light">
    <title>{{ $campaign->subject }}</title>
    <style>
        @media (max-width: 620px) {
            .px { padding-left: 22px !important; padding-right: 22px !important; }
            .h1 { font-size: 22px !important; }
            .btn a { display: block !important; text-align: center !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background:#eef2f7;font-family:{{ $font }};color:#0f172a;-webkit-font-smoothing:antialiased;">
@if ($campaign->preheader)
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:#eef2f7;">{{ $campaign->preheader }}&#8203;&nbsp;&#8203;&nbsp;&#8203;&nbsp;</div>
@endif
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef2f7;">
    <tr><td align="center" style="padding:32px 12px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:620px;">

            @include('emails.store.header')

            @if ($imageUrl)
                <tr><td style="background:#ffffff;border:1px solid #e2e8f0;border-top:0;border-bottom:0;font-size:0;line-height:0;">
                    <a href="{{ $campaign->button_url ?: route('store.home') }}"><img src="{{ $imageUrl }}" alt="" width="618" style="display:block;width:100%;height:auto;border:0;"></a>
                </td></tr>
            @endif

            <tr><td style="background:#ffffff;border:1px solid #e2e8f0;border-top:0;border-bottom:0;padding:34px 36px 8px;" class="px">
                <h1 class="h1" style="margin:0 0 16px;font-size:25px;line-height:1.3;font-weight:700;color:#0f172a;letter-spacing:-0.3px;">{{ $campaign->subject }}</h1>
                <div style="font-size:15px;line-height:1.75;color:#334155;">{!! $body !!}</div>

                @if ($campaign->button_url)
                    <table role="presentation" cellpadding="0" cellspacing="0" class="btn" style="margin:28px 0 6px;">
                        <tr><td style="border-radius:10px;background:#f97316;box-shadow:0 6px 16px rgba(15,23,42,0.18);">
                            <a href="{{ $campaign->button_url }}" style="display:inline-block;padding:15px 30px;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:10px;">{{ $campaign->button_label ?: 'Alışverişe başla' }} &rarr;</a>
                        </td></tr>
                    </table>
                @endif
            </td></tr>

            @include('emails.store.footer')
        </table>
    </td></tr>
</table>
</body>
</html>
