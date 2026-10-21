<?php

namespace App\Domain\Store\Sms;

use App\Models\Store\StoreOtpCode;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * SMS doğrulama kodu (docs/13-magaza-mimarisi.md §8.4):
 *  - 6 hane, 3 dakika geçerli, en fazla 5 deneme; kod veritabanında yalnızca özet (HMAC) olarak tutulur.
 *  - Aynı telefona 60 saniyede bir, günde en fazla 5 kod; aynı IP'den saatte en fazla 10 kod (SMS maliyet saldırısına karşı).
 *  - Yeni kod gönderilince önceki kodlar geçersiz olur.
 */
class OtpService
{
    public const TTL_SECONDS = 180;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_SECONDS = 60;

    public const DAILY_PER_PHONE = 5;

    public const HOURLY_PER_IP = 10;

    public function __construct(private SmsGateway $sms) {}

    /** @throws DomainException */
    public function send(string $phone, string $purpose, ?string $ip): void
    {
        $last = StoreOtpCode::where('phone', $phone)->where('purpose', $purpose)->latest('id')->first();
        if ($last && $last->created_at->gt(now()->subSeconds(self::RESEND_SECONDS))) {
            $wait = self::RESEND_SECONDS - (int) $last->created_at->diffInSeconds(now());
            throw new DomainException("Yeni kod için lütfen {$wait} saniye bekleyin.");
        }
        if (StoreOtpCode::where('phone', $phone)->where('created_at', '>', now()->subDay())->count() >= self::DAILY_PER_PHONE) {
            throw new DomainException('Bu telefon numarasına bugün çok fazla kod gönderildi. Lütfen yarın tekrar deneyin veya kartla ödeyin.');
        }
        if ($ip && StoreOtpCode::where('ip', $ip)->where('created_at', '>', now()->subHour())->count() >= self::HOURLY_PER_IP) {
            throw new DomainException('Çok fazla doğrulama isteği. Lütfen bir süre sonra tekrar deneyin.');
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        DB::transaction(function () use ($phone, $purpose, $code, $ip) {
            StoreOtpCode::where('phone', $phone)->where('purpose', $purpose)->whereNull('consumed_at')->update(['consumed_at' => now()]);
            StoreOtpCode::create([
                'phone' => $phone, 'purpose' => $purpose, 'code_hash' => $this->hash($phone, $code),
                'ip' => $ip, 'expires_at' => now()->addSeconds(self::TTL_SECONDS),
            ]);
        });

        $store = config('store.name');
        $sent = $this->sms->send($phone, "{$store} sipariş doğrulama kodunuz: {$code}. Kodu kimseyle paylaşmayın.", "otp:{$purpose}",
            "{$store} sipariş doğrulama kodunuz: ******. Kodu kimseyle paylaşmayın.");
        if (! $sent) {
            throw new DomainException('Doğrulama kodu gönderilemedi. Lütfen biraz sonra tekrar deneyin.');
        }
    }

    /** Kodu doğrular ve tüketir. @throws DomainException */
    public function verify(string $phone, string $purpose, string $code): void
    {
        // Hata, transaction tamamlandıktan sonra fırlatılır: hatalı deneme sayacı geri alınmamalı.
        $error = DB::transaction(function () use ($phone, $purpose, $code) {
            $otp = StoreOtpCode::where('phone', $phone)->where('purpose', $purpose)->whereNull('consumed_at')
                ->latest('id')->lockForUpdate()->first();

            if (! $otp || $otp->expires_at->isPast()) {
                return 'Kodun süresi doldu. Yeni kod isteyin.';
            }
            if ($otp->attempts >= self::MAX_ATTEMPTS) {
                return 'Çok fazla hatalı deneme. Yeni kod isteyin.';
            }
            if (! hash_equals($otp->code_hash, $this->hash($phone, preg_replace('/\D/', '', $code)))) {
                $otp->increment('attempts');
                $left = self::MAX_ATTEMPTS - $otp->attempts;

                return $left > 0 ? "Kod hatalı. {$left} deneme hakkınız kaldı." : 'Çok fazla hatalı deneme. Yeni kod isteyin.';
            }

            $otp->forceFill(['consumed_at' => now()])->save();

            return null;
        });

        if ($error !== null) {
            throw new DomainException($error);
        }
    }

    private function hash(string $phone, string $code): string
    {
        return hash_hmac('sha256', "{$phone}|{$code}", (string) config('app.key'));
    }
}
