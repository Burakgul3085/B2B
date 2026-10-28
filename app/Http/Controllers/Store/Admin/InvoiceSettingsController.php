<?php

namespace App\Http\Controllers\Store\Admin;

use App\Domain\Store\Invoicing\EInvoiceService;
use App\Domain\Store\Invoicing\EInvoiceSettings;
use App\Domain\Store\Invoicing\Providers\UyumsoftProvider;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreSetting;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Ayarlar → Fatura (yalnızca süper admin): Uyumsoft bağlantısı ve fatura varsayılanları. */
class InvoiceSettingsController extends Controller
{
    public function edit(): View
    {
        return view('store.admin.invoices.settings', [
            's' => StoreSetting::values(),
            'hasPassword' => filled(StoreSetting::get('uyumsoft_password')),
            'urls' => ['test' => UyumsoftProvider::TEST_URL, 'live' => UyumsoftProvider::LIVE_URL],
        ]);
    }

    public function update(Request $request, EInvoiceSettings $settings): RedirectResponse
    {
        $settings->save($request->all());

        return back()->with('status', 'Fatura ayarları kaydedildi.');
    }

    public function test(EInvoiceService $invoices): RedirectResponse
    {
        try {
            return back()->with('status', $invoices->provider()->test());
        } catch (DomainException $e) {
            return back()->withErrors(['test' => $e->getMessage()]);
        }
    }
}
