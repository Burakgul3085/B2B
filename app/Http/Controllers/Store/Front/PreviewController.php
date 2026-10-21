<?php

namespace App\Http\Controllers\Store\Front;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureStoreOpen;
use Illuminate\Http\RedirectResponse;

/**
 * Kapalı mağazayı önizleme. Bağlantı mağaza panelinde imzalı ve süreli üretilir (bkz. SettingsController);
 * açılınca bu tarayıcıya 2 saatlik önizleme çerezi verilir.
 */
class PreviewController extends Controller
{
    public function start(): RedirectResponse
    {
        return redirect()->route('store.home')->withCookie(cookie(EnsureStoreOpen::PREVIEW_COOKIE, '1', 120, httpOnly: true, sameSite: 'lax'));
    }

    public function end(): RedirectResponse
    {
        return redirect()->route('store.home')->withCookie(cookie()->forget(EnsureStoreOpen::PREVIEW_COOKIE));
    }
}
