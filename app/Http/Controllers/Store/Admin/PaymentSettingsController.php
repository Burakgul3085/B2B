<?php

namespace App\Http\Controllers\Store\Admin;

use App\Domain\Store\Payments\BankTransfer;
use App\Domain\Store\Payments\IyzicoClient;
use App\Domain\Store\Payments\PaymentSettings;
use App\Domain\Store\Sms\SmsGateway;
use App\Domain\Support\Payments\TurkishBanks;
use App\Http\Controllers\Controller;
use App\Models\Store\District;
use App\Models\Store\Province;
use App\Models\Store\StoreCodBlock;
use App\Models\Store\StoreSetting;
use App\Models\Store\StoreSmsMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Ödeme ve Teslimat: kargo/mağaza teslimatı ücretleri, kapıda ödeme kuralları, iyzico ve Netgsm ayarları. */
class PaymentSettingsController extends Controller
{
    public function edit(IyzicoClient $iyzico, SmsGateway $sms): View
    {
        $selected = array_filter(array_map('intval', explode(',', (string) StoreSetting::get('local_districts'))));

        return view('store.admin.storefront.payments', [
            'settings' => StoreSetting::values(),
            'districts' => District::where('province_id', Province::ESKISEHIR)->orderBy('name')->pluck('name', 'id'),
            'selectedDistricts' => $selected ?: District::where('province_id', Province::ESKISEHIR)->pluck('id')->all(),
            'secrets' => collect(StoreSetting::SECRETS)->mapWithKeys(fn ($k) => [$k => filled(StoreSetting::get($k))])->all(),
            'bankRows' => array_map(fn ($a) => ['bank' => $a['bank'], 'holder' => $a['holder'], 'iban' => BankTransfer::formatIban($a['iban'])], BankTransfer::accounts()),
            'banks' => TurkishBanks::forBrowser(),
            'iyzicoReady' => $iyzico->isConfigured(),
            'smsLive' => $sms->isLive(),
            'blocklist' => StoreCodBlock::latest('id')->limit(50)->get(),
            'smsLog' => StoreSmsMessage::latest('id')->limit(10)->get(),
        ]);
    }

    public function update(Request $request, PaymentSettings $settings): RedirectResponse
    {
        $settings->save($request->all());

        return back()->with('status', 'Ödeme ve teslimat ayarları kaydedildi.');
    }

    public function removeSecret(string $key, PaymentSettings $settings): RedirectResponse
    {
        $settings->removeSecret($key);

        return back()->with('status', 'Kayıtlı anahtar silindi.');
    }
}
