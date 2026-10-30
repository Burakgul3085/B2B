<?php

namespace App\Domain\Support\Payments;

use DomainException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * iyzico REST bağlantısı (Ödeme Formu / Checkout Form); mağaza ve B2B kendi anahtarlarıyla kullanır.
 * İstekler IYZWSv2 imzasıyla gönderilir:
 *   imza   = HMAC-SHA256(rastgeleAnahtar + uriYolu + istekGövdesi, gizliAnahtar)  (hex)
 *   başlık = "IYZWSv2 " + base64("apiKey:{apiKey}&randomKey:{rastgele}&signature:{imza}")
 * Kart bilgisi hiçbir zaman sistemimize gelmez: ödeyen kart bilgisini iyzico'nun sayfasına girer.
 */
final class IyzicoApi
{
    public const SANDBOX_URL = 'https://sandbox-api.iyzipay.com';

    public const LIVE_URL = 'https://api.iyzipay.com';

    /** Son yanıtın ham gövdesi (imza doğrulamasında fiyatlar yazıldığı gibi kullanılır). */
    private string $lastBody = '';

    public function __construct(private ?string $apiKey, private ?string $secretKey, private string $mode = 'sandbox') {}

    public function mode(): string
    {
        return $this->mode === 'live' ? 'live' : 'sandbox';
    }

    /**
     * Ödeme formu sonucunun iyzico imzası: HMAC-SHA256(paymentStatus:paymentId:currency:basketId:conversationId:paidPrice:price:token,
     * gizli anahtar). Yanıt zaten HTTPS ile doğrudan iyzico'dan alınır; imza ek bir doğrulamadır.
     *
     * @return bool|null null: yanıtta imza yok (doğrulanamadı)
     */
    public function checkoutSignatureValid(array $result, string $token): ?bool
    {
        if (empty($result['signature']) || ! is_string($result['signature'])) {
            return null;
        }
        $parts = [
            $result['paymentStatus'] ?? '', $result['paymentId'] ?? '', $result['currency'] ?? '', $result['basketId'] ?? '',
            $result['conversationId'] ?? '', $this->rawNumber('paidPrice') ?? ($result['paidPrice'] ?? ''), $this->rawNumber('price') ?? ($result['price'] ?? ''), $token,
        ];
        $expected = hash_hmac('sha256', implode(':', array_map('strval', $parts)), (string) $this->secretKey);

        return hash_equals($expected, strtolower($result['signature']));
    }

    /** Ham yanıttaki sayı, yazıldığı biçimde (ör. "6.0"); JSON'a çevrilince biçim kaybolmasın. */
    private function rawNumber(string $key): ?string
    {
        return preg_match('/"'.preg_quote($key, '/').'"\s*:\s*"?(-?[0-9]+(?:\.[0-9]+)?)"?/', $this->lastBody, $m) ? $m[1] : null;
    }

    public function isConfigured(): bool
    {
        return filled($this->apiKey) && filled($this->secretKey);
    }

    public function baseUrl(): string
    {
        return $this->mode === 'live' ? self::LIVE_URL : self::SANDBOX_URL;
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

    /** @throws DomainException|IyzicoUnreachable */
    private function post(string $path, array $body): array
    {
        if (! $this->isConfigured()) {
            throw new DomainException('Kartla ödeme yapılandırılmamış.');
        }

        $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $random = now()->getTimestampMs().Str::random(8);
        $signature = hash_hmac('sha256', $random.$path.$json, (string) $this->secretKey);
        $auth = base64_encode('apiKey:'.$this->apiKey.'&randomKey:'.$random.'&signature:'.$signature);

        try {
            $response = Http::timeout(30)->withHeaders([
                'Authorization' => 'IYZWSv2 '.$auth,
                'x-iyzi-rnd' => $random,
                'Accept' => 'application/json',
            ])->withBody($json, 'application/json')->post($this->baseUrl().$path);
        } catch (Throwable) {
            throw new IyzicoUnreachable('Ödeme sağlayıcısına bağlanılamadı. Lütfen tekrar deneyin.');
        }

        $this->lastBody = (string) $response->body();
        $data = json_decode($this->lastBody, true);
        if (! is_array($data)) {
            throw new IyzicoUnreachable('Ödeme sağlayıcısından geçersiz yanıt alındı.');
        }

        return $data;
    }
}
