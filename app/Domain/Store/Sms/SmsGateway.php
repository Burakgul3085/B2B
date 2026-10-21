<?php

namespace App\Domain\Store\Sms;

use App\Models\Store\StoreSetting;
use App\Models\Store\StoreSmsMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * SMS gönderimi. Sürücü mağaza ayarlarından seçilir:
 *  - log:    SMS gönderilmez; mesaj uygulama günlüğüne yazılır (geliştirme ve test).
 *  - netgsm: Netgsm HTTP GET servisi (api.netgsm.com.tr/sms/send/get). Yanıt "00 …", "01 …" veya "02 …" ise başarılıdır.
 *            Netgsm hesabı açıldığında kullanıcı kodu, şifre ve onaylı mesaj başlığı panelden girilir.
 * Her gönderim kaydedilir; doğrulama kodları kayıtta maskelenir.
 */
class SmsGateway
{
    public const NETGSM_URL = 'https://api.netgsm.com.tr/sms/send/get';

    public function driver(): string
    {
        return StoreSetting::get('sms_driver') === 'netgsm' ? 'netgsm' : 'log';
    }

    /** Canlıda gerçek SMS gidebiliyor mu? (Kapıda ödemenin açılabilmesi için gerekli.) */
    public function isLive(): bool
    {
        return $this->driver() === 'netgsm' && filled(StoreSetting::get('netgsm_usercode'))
            && filled(StoreSetting::secret('netgsm_password')) && filled(StoreSetting::get('netgsm_header'));
    }

    /** @param  string  $maskedBody  kayda yazılacak hâli (kodlar gizlenmiş) */
    public function send(string $phone, string $body, string $purpose, ?string $maskedBody = null): bool
    {
        $driver = $this->driver();
        $ok = false;
        $ref = null;
        $error = null;

        if ($driver === 'log') {
            Log::info("[sms:{$purpose}] {$phone}: {$body}");
            $ok = true;
        } else {
            try {
                $response = Http::timeout(15)->get(self::NETGSM_URL, [
                    'usercode' => StoreSetting::get('netgsm_usercode'),
                    'password' => StoreSetting::secret('netgsm_password'),
                    'gsmno' => '90'.substr($phone, 1), // 05xx… → 905xx…
                    'message' => $body,
                    'msgheader' => StoreSetting::get('netgsm_header'),
                    'dil' => 'TR',
                ]);
                $answer = trim($response->body());
                $ok = $response->successful() && preg_match('/^0[012]\b/', $answer) === 1;
                $ref = $ok ? trim(substr($answer, 2)) ?: null : null;
                $error = $ok ? null : 'Netgsm yanıtı: '.mb_substr($answer, 0, 40);
            } catch (Throwable) {
                $error = 'Netgsm\'e bağlanılamadı.';
            }
        }

        StoreSmsMessage::create([
            'driver' => $driver, 'phone' => $phone, 'purpose' => $purpose, 'body' => mb_substr($maskedBody ?? $body, 0, 500),
            'status' => $ok ? 'sent' : 'failed', 'provider_ref' => $ref ? mb_substr($ref, 0, 60) : null, 'error' => $error,
        ]);

        return $ok;
    }
}
