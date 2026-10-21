<?php

namespace App\Domain\Store\Payments;

use App\Models\Store\StoreSetting;
use DomainException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * iyzico REST istemcisi (Ödeme Formu / Checkout Form). İstekler IYZWSv2 imzasıyla gönderilir:
 *   imza   = HMAC-SHA256(rastgeleAnahtar + uriYolu + istekGövdesi, gizliAnahtar)  (hex)
 *   başlık = "IYZWSv2 " + base64("apiKey:{apiKey}&randomKey:{rastgele}&signature:{imza}")
 * Anahtarlar mağaza ayarlarında şifreli saklanır; ortam (sandbox/canlı) panelden seçilir.
 * Kart bilgisi hiçbir zaman sistemimize gelmez: müşteri kart bilgisini iyzico'nun sayfasına girer.
 */
class IyzicoClient
{
    public const SANDBOX_URL = 'https://sandbox-api.iyzipay.com';

    public const LIVE_URL = 'https://api.iyzipay.com';

    public function isConfigured(): bool
    {
        return filled(StoreSetting::secret('iyzico_api_key')) && filled(StoreSetting::secret('iyzico_secret_key'));
    }

    public function baseUrl(): string
    {
        return StoreSetting::get('iyzico_mode') === 'live' ? self::LIVE_URL : self::SANDBOX_URL;
    }

    /**
     * Ödeme formunu başlatır.
     *
     * @return array{token: string, paymentPageUrl: string, raw: array}
     *
     * @throws DomainException
     */
    public function initializeCheckoutForm(array $request): array
    {
        $response = $this->post('/payment/iyzipos/checkoutform/initialize/auth/ecom', $request);
        if (($response['status'] ?? null) !== 'success' || empty($response['token']) || empty($response['paymentPageUrl'])) {
            throw new DomainException('Ödeme başlatılamadı: '.($response['errorMessage'] ?? 'bilinmeyen hata'));
        }

        return ['token' => $response['token'], 'paymentPageUrl' => $response['paymentPageUrl'], 'raw' => $response];
    }

    /** Ödeme formunun sonucunu iyzico'dan sorgular (tarayıcıdan gelen bilgiye güvenilmez). @throws DomainException */
    public function retrieveCheckoutForm(string $token, string $conversationId): array
    {
        return $this->post('/payment/iyzipos/checkoutform/auth/ecom/detail', [
            'locale' => 'tr', 'conversationId' => $conversationId, 'token' => $token,
        ]);
    }

    /**
     * Kalem bazında para iadesi (iyzico "refund"). Ödemedeki her sepet kalemi ayrı işlem kimliğiyle iade edilir.
     *
     * @return array iyzico yanıtı (status: success | failure)
     *
     * @throws DomainException bağlantı hatası
     */
    public function refund(string $transactionId, string $amount, string $conversationId, ?string $ip): array
    {
        return $this->post('/payment/refund', [
            'locale' => 'tr', 'conversationId' => $conversationId, 'paymentTransactionId' => $transactionId,
            'price' => $amount, 'currency' => 'TRY', 'ip' => $ip ?? '127.0.0.1',
        ]);
    }

    /** @throws DomainException */
    private function post(string $path, array $body): array
    {
        if (! $this->isConfigured()) {
            throw new DomainException('Kartla ödeme yapılandırılmamış.');
        }

        $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $random = now()->getTimestampMs().Str::random(8);
        $signature = hash_hmac('sha256', $random.$path.$json, (string) StoreSetting::secret('iyzico_secret_key'));
        $auth = base64_encode('apiKey:'.StoreSetting::secret('iyzico_api_key').'&randomKey:'.$random.'&signature:'.$signature);

        try {
            $response = Http::timeout(30)->withHeaders([
                'Authorization' => 'IYZWSv2 '.$auth,
                'x-iyzi-rnd' => $random,
                'Accept' => 'application/json',
            ])->withBody($json, 'application/json')->post($this->baseUrl().$path);
        } catch (Throwable) {
            throw new DomainException('Ödeme sağlayıcısına bağlanılamadı. Lütfen tekrar deneyin.');
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new DomainException('Ödeme sağlayıcısından geçersiz yanıt alındı.');
        }

        return $data;
    }
}
