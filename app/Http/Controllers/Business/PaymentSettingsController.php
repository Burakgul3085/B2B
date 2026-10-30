<?php

namespace App\Http\Controllers\Business;

use App\Domain\Payments\PaymentSettings;
use App\Domain\Support\Payments\Iban;
use App\Domain\Support\Payments\TurkishBanks;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Ödeme ayarları: ödeme sistemi, kartla ödeme (iyzico), havale hesapları ve teslimat ücreti. */
class PaymentSettingsController extends Controller
{
    public function edit(PaymentSettings $payments): View
    {
        $s = $payments->settings();

        return view('business.payment-settings', [
            's' => $s,
            'hasKeys' => ['iyzico_api_key' => $payments->hasSecret('iyzico_api_key'), 'iyzico_secret_key' => $payments->hasSecret('iyzico_secret_key')],
            'installments' => $payments->installments(),
            'bankRows' => array_map(fn ($a) => ['bank' => $a['bank'], 'holder' => $a['holder'], 'iban' => Iban::format($a['iban'])], $payments->accounts()),
            'banks' => TurkishBanks::forBrowser(),
        ]);
    }

    public function update(Request $request, PaymentSettings $payments): RedirectResponse
    {
        $payments->save($request->all());

        return back()->with('status', 'Ödeme ayarları kaydedildi.');
    }

    public function removeKeys(PaymentSettings $payments): RedirectResponse
    {
        $payments->removeKeys();

        return back()->with('status', 'iyzico anahtarları silindi; kartla ödeme kapatıldı.');
    }
}
