<?php

namespace App\Domain\Store\Payments;

use App\Domain\Support\Payments\IyzicoApi;
use App\Models\Store\StoreSetting;
use DomainException;

/**
 * Mağazanın iyzico istemcisi: anahtarlar mağaza ayarlarında şifreli saklanır, ortam (sandbox/canlı) panelden seçilir.
 * İmza ve istekler ortak IyzicoApi'dedir (B2B de kendi anahtarlarıyla aynı bağlantıyı kullanır).
 */
class IyzicoClient
{
    public const SANDBOX_URL = IyzicoApi::SANDBOX_URL;

    public const LIVE_URL = IyzicoApi::LIVE_URL;

    public function isConfigured(): bool
    {
        return $this->api()->isConfigured();
    }

    public function baseUrl(): string
    {
        return $this->api()->baseUrl();
    }

    /**
     * @return array{token: string, paymentPageUrl: string, raw: array}
     *
     * @throws DomainException
     */
    public function initializeCheckoutForm(array $request): array
    {
        return $this->api()->initializeCheckoutForm($request);
    }

    /** @throws DomainException */
    public function retrieveCheckoutForm(string $token, string $conversationId): array
    {
        return $this->api()->retrieveCheckoutForm($token, $conversationId);
    }

    /** @throws DomainException */
    public function refund(string $transactionId, string $amount, string $conversationId, ?string $ip): array
    {
        return $this->api()->refund($transactionId, $amount, $conversationId, $ip);
    }

    private function api(): IyzicoApi
    {
        return new IyzicoApi(StoreSetting::secret('iyzico_api_key'), StoreSetting::secret('iyzico_secret_key'), (string) StoreSetting::get('iyzico_mode'));
    }
}
