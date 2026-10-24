<?php

namespace App\Http\Controllers\Store\Admin;

use App\Domain\Store\Content\StoreFooter;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Vitrin → Alt Bilgi: mağazanın alt bilgisi (güven şeridi, sütun başlıkları, gösterilecek bölümler). */
class FooterController extends Controller
{
    private const FIELDS = ['footer_about', 'tagline', 'strip_enabled', 'strip', 'columns', 'store_title',
        'show_map', 'show_hours', 'show_b2b', 'whatsapp_button', 'bottom_note', 'manager_name', 'marquee_enabled', 'marquee_color', 'marquee_speed', 'marquee_items', 'topbar_enabled', 'topbar_items'];

    public function edit(): View
    {
        return view('store.admin.storefront.footer', ['footer' => StoreFooter::config(), 'settings' => StoreSetting::values(), 'icons' => StoreFooter::ICONS]);
    }

    public function update(Request $request, StoreFooter $footer): RedirectResponse
    {
        $footer->save($request->only(self::FIELDS));

        return back()->with('status', 'Üst ve alt bilgi kaydedildi.');
    }
}
