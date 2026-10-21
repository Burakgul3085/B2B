<?php

namespace App\Http\Controllers\Store\Admin;

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
    private const FIELDS = ['announcement', 'meta_title', 'meta_description', 'footer_about', 'phone', 'whatsapp', 'email', 'address', 'instagram', 'facebook',
        'company_title', 'company_tax_office', 'company_tax_number', 'company_mersis'];

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

        return back()->with('status', 'Mağaza ayarları kaydedildi.');
    }

    public function open(Request $request): RedirectResponse
    {
        $open = $request->boolean('open');
        $this->storefront->setOpen($open);

        return back()->with('status', $open ? 'Mağaza ziyaretçilere açıldı.' : 'Mağaza ziyaretçilere kapatıldı; "yakında" sayfası gösteriliyor.');
    }

    /** Kapalı mağazayı gezmek için 15 dakika geçerli, imzalı bağlantı. */
    public static function previewUrl(): string
    {
        return URL::temporarySignedRoute('store.preview', now()->addMinutes(15));
    }
}
