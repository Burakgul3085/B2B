{{-- Mağaza e-postası alt kısmı: yardım (telefon, WhatsApp, e-posta) ve satıcı künyesi. $footerNote: e-postanın neden gönderildiği; $unsubscribeUrl: e-bültende abonelikten çıkma bağlantısı. --}}
@php
    $phoneLink = fn ($phone) => 'tel:'.preg_replace('/[^0-9+]/', '', $phone);
    $whatsapp = \App\Domain\Store\Storefront\ProductPage::whatsapp($settings['whatsapp']);
    $socials = \App\Domain\Store\Content\SocialLinks::filled();
@endphp
<tr><td style="background:#ffffff;border:1px solid #e2e8f0;border-top:0;border-radius:0 0 16px 16px;padding:26px 36px 30px;" class="px">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-top:1px solid #e2e8f0;">
        <tr><td style="padding-top:22px;font-size:13px;line-height:1.7;color:#475569;">
            <strong style="color:#0f172a;font-size:14px;">Yardıma mı ihtiyacınız var?</strong><br>
            Sorularınız için <a href="{{ route('store.contact') }}" style="color:{{ $accent }};text-decoration:none;font-weight:600;">iletişim sayfamızdan</a> bize ulaşabilirsiniz.
            @if ($settings['phone'])<br>{{ $settings['phone_label'] ?: 'Telefon' }}: <a href="{{ $phoneLink($settings['phone']) }}" style="color:{{ $accent }};text-decoration:none;font-weight:600;">{{ $settings['phone'] }}</a>@endif
            @if ($whatsapp)<br>WhatsApp: <a href="https://wa.me/{{ $whatsapp }}" style="color:{{ $accent }};text-decoration:none;font-weight:600;">{{ $settings['whatsapp'] }}</a>@endif
            @if ($settings['email'])<br>E-posta: <a href="mailto:{{ $settings['email'] }}" style="color:{{ $accent }};text-decoration:none;font-weight:600;">{{ $settings['email'] }}</a>@endif
        </td></tr>
    </table>
</td></tr>

<tr><td align="center" style="padding:24px 24px 8px;font-size:12px;line-height:1.7;color:#94a3b8;">
    <strong style="color:#64748b;">{{ $settings['company_title'] ?: $storeName }}</strong>
    @if ($settings['address'])<br>{{ $settings['address'] }}@endif
    @if ($settings['company_tax_office'] && $settings['company_tax_number'])<br>{{ $settings['company_tax_office'] }} V.D. · {{ $settings['company_tax_number'] }}@endif
    @if ($settings['company_mersis'])<br>MERSİS: {{ $settings['company_mersis'] }}@elseif ($settings['company_esnaf_no'] ?? null)<br>Esnaf ve Sanatkârlar Sicil No: {{ $settings['company_esnaf_no'] }}@endif
    @if ($socials)
        <br>@foreach ($socials as $social)<a href="{{ $social['url'] }}" style="color:#64748b;text-decoration:none;font-weight:600;">{{ $social['label'] }}</a>@if (! $loop->last) · @endif @endforeach
    @endif
    <br><br>
    {{ $footerNote }}
    @isset($unsubscribeUrl) <a href="{{ $unsubscribeUrl }}" style="color:#64748b;">Abonelikten çık</a>@endisset
    <br>
    Güvenliğiniz için kart bilgilerinizi ve parolanızı kimseyle paylaşmayın; {{ $storeName }} bunları sizden e-postayla asla istemez.
    <br><br>© {{ now()->year }} {{ $storeName }}
</td></tr>
