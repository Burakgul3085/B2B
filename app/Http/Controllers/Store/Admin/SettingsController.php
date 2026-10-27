<?php

namespace App\Http\Controllers\Store\Admin;

use App\Domain\Store\Mail\StoreMailer;
use App\Domain\Store\Storefront\LaunchChecklist;
use App\Domain\Store\Storefront\StorefrontAdmin;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/** Mağaza ayarları: iletişim, duyuru, ana sayfa SEO'su, mağazayı açma/kapama ve önizleme bağlantısı. */
class SettingsController extends Controller
{
    private const FIELDS = ['announcement', 'meta_title', 'meta_description', 'new_product_days', 'phone', 'phone_label', 'phone2', 'phone2_label', 'whatsapp', 'email', 'address', 'instagram', 'facebook', 'x', 'youtube', 'tiktok', 'linkedin', 'pinterest',
        'mail_host', 'mail_port', 'mail_encryption', 'mail_username', 'mail_password', 'remove_mail_password', 'mail_from_address', 'mail_from_name',
        'company_title', 'company_tax_office', 'company_tax_number', 'company_mersis', 'company_esnaf_no', 'notify_emails', 'notify_orders', 'notify_returns', 'notify_low_stock', 'notify_messages'];

    public function __construct(private StorefrontAdmin $storefront) {}

    public function edit(): View
    {
        return view('store.admin.storefront.settings', [
            'settings' => StoreSetting::values(),
            'previewUrl' => self::previewUrl(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->storefront->saveSettings($request->only(self::FIELDS));
        try {
            if ($request->hasFile('payment_logos')) {
                $request->validate(['payment_logos' => ['image', 'mimes:png,jpg,jpeg,webp', 'max:2048']], [], ['payment_logos' => 'ödeme logo bandı']);
                $this->storefront->setPaymentLogos($request->file('payment_logos'));
            } elseif ($request->boolean('remove_payment_logos')) {
                $this->storefront->setPaymentLogos(null);
            }
        } catch (\DomainException $e) {
            return back()->withErrors(['payment_logos' => $e->getMessage()]);
        }

        return back()->with('status', 'Mağaza ayarları kaydedildi.');
    }

    /** Mağaza e-posta hesabını dener: oturumdaki yöneticiye deneme e-postası. */
    public function testMail(Request $request, StoreMailer $mailer): RedirectResponse
    {
        $to = $request->user('store_admin')->email;
        $ok = $mailer->send($to, 'Deneme e-postası — '.config('store.name'), [
            'title' => 'E-posta ayarları çalışıyor',
            'intro' => 'Bu deneme e-postası mağazanızın e-posta hesabıyla gönderildi. Sipariş, bildirim ve e-bülten e-postaları bu hesapla gidecek.',
        ]);

        return $ok ? back()->with('status', "Deneme e-postası {$to} adresine gönderildi.")
            : back()->withErrors(['mail_host' => 'E-posta gönderilemedi. Sunucu, port, şifreleme, kullanıcı adı ve parolayı kontrol edin.']);
    }

    public function open(Request $request): RedirectResponse
    {
        $open = $request->boolean('open');
        $this->storefront->setOpen($open);

        return back()->with('status', $open ? 'Mağaza ziyaretçilere açıldı.' : 'Mağaza ziyaretçilere kapatıldı; "yakında" sayfası gösteriliyor.');
    }

    /** Yayın hazırlığı ve iyzico başvuru kontrol listesi. */
    public function launch(LaunchChecklist $checklist): View
    {
        return view('store.admin.storefront.launch', ['groups' => $checklist->groups()]);
    }

    /** Kapalı mağazayı gezmek için 15 dakika geçerli, imzalı bağlantı. */
    public static function previewUrl(): string
    {
        return URL::temporarySignedRoute('store.preview', now()->addMinutes(15));
    }
}
