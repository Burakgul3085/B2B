<?php

namespace App\Http\Controllers\Business;

use App\Domain\Invoicing\InvoiceSettings;
use App\Domain\Invoicing\Providers\InvoiceRejected;
use App\Domain\Invoicing\Providers\InvoiceUncertain;
use App\Http\Controllers\Controller;
use App\Models\InvoiceSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Toptancı → Ayarlar → Fatura Ayarları (bayi faturaları; mağazanın fatura ayarlarından ayrıdır). */
class InvoiceSettingsController extends Controller
{
    public function edit(InvoiceSettings $settings): View
    {
        return view('business.invoices.settings', [
            's' => $settings->record(),
            'hasPassword' => $settings->hasPassword(),
            'seller' => $settings->seller(),
            'sellerProblem' => $settings->sellerProblem(),
            'drivers' => InvoiceSetting::DRIVERS,
        ]);
    }

    public function update(Request $request, InvoiceSettings $settings): RedirectResponse
    {
        $settings->save($request->only([
            'driver', 'uyumsoft_mode', 'uyumsoft_username', 'uyumsoft_password', 'sender_id', 'seller_name', 'tax_office',
            'series_efatura', 'series_earsiv', 'delivery_vat', 'auto_issue', 'notify_dealer',
        ]));

        return back()->with('status', 'Fatura ayarları kaydedildi.');
    }

    public function test(InvoiceSettings $settings): RedirectResponse
    {
        try {
            return back()->with('status', $settings->provider()->test());
        } catch (InvoiceRejected|InvoiceUncertain $e) {
            return back()->withErrors(['invoice' => 'Bağlantı kurulamadı: '.$e->getMessage()]);
        }
    }
}
