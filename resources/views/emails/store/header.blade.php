{{-- Mağaza e-postası başlığı (B2B e-postalarıyla aynı düzen): logo, mağaza adı, alt satır ve yapboz renklerinde şerit. --}}
<tr><td style="background:#ffffff;border-radius:16px 16px 0 0;border:1px solid #e2e8f0;border-bottom:0;" class="px">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td style="padding:26px 36px 22px;" class="px">
                <a href="{{ route('store.home') }}" style="text-decoration:none;">
                    <table role="presentation" cellpadding="0" cellspacing="0"><tr>
                        @if ($logoSrc)
                            <td valign="middle" style="padding-right:14px;">
                                <img src="{{ $logoSrc }}" alt="{{ $storeName }}" width="{{ $logoHeight }}" height="{{ $logoHeight }}"
                                    style="display:block;width:{{ $logoHeight }}px;height:{{ $logoHeight }}px;border:0;outline:none;">
                            </td>
                        @endif
                        <td valign="middle" style="font-family:{{ $font }};">
                            <div style="font-size:19px;font-weight:700;color:#0f172a;line-height:1.2;">{{ $storeName }}</div>
                            <div style="font-size:12px;color:#64748b;margin-top:3px;">{{ $tagline }}</div>
                        </td>
                    </tr></table>
                </a>
            </td>
        </tr>
    </table>
</td></tr>

<tr><td style="font-size:0;line-height:0;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
        <td height="4" style="background:#f97316;height:4px;font-size:0;line-height:0;">&nbsp;</td>
        <td height="4" style="background:#0ea5e9;height:4px;font-size:0;line-height:0;">&nbsp;</td>
        <td height="4" style="background:#22c55e;height:4px;font-size:0;line-height:0;">&nbsp;</td>
        <td height="4" style="background:#facc15;height:4px;font-size:0;line-height:0;">&nbsp;</td>
    </tr></table>
</td></tr>
