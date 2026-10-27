{{-- Giriş doğrulama kodu kutusu (emails.business ve emails.store): büyük, aralıklı rakamlar; kolay okunur ve kopyalanır. --}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:26px 0 6px;">
    <tr><td align="center" style="background:#f8fafc;border:1px dashed {{ $accent }};border-radius:14px;padding:22px 12px;">
        <div style="font-size:11px;font-weight:700;letter-spacing:1.4px;text-transform:uppercase;color:#64748b;margin-bottom:10px;">Doğrulama kodunuz</div>
        <div style="font-family:'Courier New',Courier,monospace;font-size:38px;line-height:1;font-weight:700;letter-spacing:14px;color:{{ $accent }};padding-left:14px;">{{ $code }}</div>
        <div style="font-size:12px;color:#64748b;margin-top:12px;">{{ $code_hint ?? '10 dakika geçerlidir.' }}</div>
    </td></tr>
</table>
