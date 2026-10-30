<?php

namespace App\Domain\Payments;

use App\Domain\Audit\AuditLogger;
use App\Domain\Support\Decimal;
use App\Domain\Support\Payments\BankAccounts;
use App\Domain\Support\Payments\IyzicoApi;
use App\Domain\Tenancy\TenantContext;
use App\Models\BusinessSetting;
use App\Models\DealerRelationship;
use App\Models\Order;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

/**
 * Toptancının B2B ödeme ayarları (Toptancı → Ayarlar → Ödeme): ödeme sistemi açık/kapalı, kartla ödeme (iyzico
 * anahtarları şifreli), taksitler, havale hesapları ve isteğe bağlı teslimat ücreti.
 *
 * Ödeme sistemi kapalıyken siparişler eskisi gibi ödemesiz (cari) verilir. Açıkken bayi sepette yöntem seçer:
 * kart ve havale herkese; açık hesap ve teslimde ödeme yalnızca bayi kartında izin verilen bayilere.
 */
class PaymentSettings
{
    public const MAX_ACCOUNTS = 5;

    public const INSTALLMENTS = [1, 2, 3, 6, 9, 12];

    public function __construct(private TenantContext $tenant, private AuditLogger $audit) {}

    public function settings(): BusinessSetting
    {
        return BusinessSetting::where('business_id', $this->tenant->businessId())->firstOrFail();
    }

    public function enabled(): bool
    {
        return $this->settings()->payments_enabled;
    }

    public function iyzico(): IyzicoApi
    {
        $s = $this->settings();

        return new IyzicoApi($this->decrypt($s->iyzico_api_key), $this->decrypt($s->iyzico_secret_key), (string) $s->iyzico_mode);
    }

    public function cardAvailable(): bool
    {
        return $this->settings()->card_enabled && $this->iyzico()->isConfigured();
    }

    public function transferAvailable(): bool
    {
        return $this->settings()->transfer_enabled && $this->accounts() !== [];
    }

    /** @return list<array{bank: string, holder: string, iban: string, logo: ?string}> */
    public function accounts(): array
    {
        return BankAccounts::present($this->settings()->transfer_accounts ?? []);
    }

    /** @return list<int> */
    public function installments(): array
    {
        $chosen = array_map('intval', explode(',', (string) $this->settings()->installments));

        return array_values(array_intersect(self::INSTALLMENTS, $chosen)) ?: [1];
    }

    /**
     * Bayinin seçebileceği ödeme yöntemleri; ödeme sistemi kapalıysa boş (eski davranış: ödemesiz sipariş).
     *
     * @return array<string, string> yöntem => etiket
     */
    public function methodsFor(DealerRelationship $relationship): array
    {
        if (! $this->enabled()) {
            return [];
        }

        return array_intersect_key(Order::PAYMENT_METHODS, array_filter([
            'card' => $this->cardAvailable(),
            'transfer' => $this->transferAvailable(),
            'open_account' => $relationship->allow_open_account,
            'on_delivery' => $relationship->allow_on_delivery,
        ]));
    }

    /** KDV dahil sipariş toplamına göre teslimat ücreti (eşik verildiyse eşik ve üstünde ücretsiz). */
    public function deliveryFee(string $grandTotal): string
    {
        $s = $this->settings();
        $fee = (string) $s->delivery_fee;
        if (bccomp($fee, '0', 2) <= 0 || ($s->delivery_free_over !== null && bccomp($grandTotal, (string) $s->delivery_free_over, 2) >= 0)) {
            return '0.00';
        }

        return bcadd($fee, '0', 2);
    }

    public function hasSecret(string $key): bool
    {
        return filled($this->settings()->{$key});
    }

    /**
     * Ayar formunu doğrulayıp kaydeder. Boş bırakılan anahtar alanı kayıtlı anahtarı değiştirmez.
     *
     * @throws ValidationException
     */
    public function save(array $input): void
    {
        $s = $this->settings();
        $errors = [];
        $money = function (mixed $value, string $key, bool $nullable = false) use (&$errors): ?string {
            $value = is_scalar($value) || $value === null ? $value : '-';
            if ($nullable && blank($value)) {
                return null;
            }
            $parsed = Decimal::parse($value ?? '0', 2);
            if ($parsed === null || bccomp($parsed, '0', 2) < 0) {
                $errors[$key] = 'Geçerli bir tutar girin (ör. 150,00).';
            }

            return $parsed;
        };

        $accounts = BankAccounts::fromRows((array) ($input['transfer_accounts'] ?? []), 'transfer_accounts', self::MAX_ACCOUNTS);
        $errors += $accounts['errors'];
        $installments = array_values(array_intersect(self::INSTALLMENTS, array_map(fn ($n) => is_scalar($n) ? (int) $n : 0, (array) ($input['installments'] ?? [1]))));

        $data = [
            'payments_enabled' => filter_var($input['payments_enabled'] ?? false, FILTER_VALIDATE_BOOL),
            'card_enabled' => filter_var($input['card_enabled'] ?? false, FILTER_VALIDATE_BOOL),
            'iyzico_mode' => ($input['iyzico_mode'] ?? null) === 'live' ? 'live' : 'sandbox',
            'installments' => implode(',', $installments ?: [1]),
            'transfer_enabled' => filter_var($input['transfer_enabled'] ?? false, FILTER_VALIDATE_BOOL),
            'transfer_accounts' => $accounts['accounts'],
            'delivery_fee' => $money($input['delivery_fee'] ?? '0', 'delivery_fee'),
            'delivery_free_over' => $money($input['delivery_free_over'] ?? null, 'delivery_free_over', true),
        ];
        foreach (['iyzico_api_key', 'iyzico_secret_key'] as $key) {
            $plain = is_string($input[$key] ?? null) ? trim($input[$key]) : '';
            if ($plain !== '') {
                $data[$key] = Crypt::encryptString(mb_substr($plain, 0, 200));
            }
        }

        $hasKeys = filled($data['iyzico_api_key'] ?? $s->iyzico_api_key) && filled($data['iyzico_secret_key'] ?? $s->iyzico_secret_key);
        if ($data['card_enabled'] && ! $hasKeys) {
            $errors['card_enabled'] = 'Kartla ödemeyi açmak için iyzico API anahtarı ve gizli anahtarı girin.';
        }
        if ($data['transfer_enabled'] && $data['transfer_accounts'] === [] && ! isset($errors['transfer_accounts'])) {
            $errors['transfer_accounts'] = 'Havaleyi açmak için en az bir hesap girin.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $old = $s->only(['payments_enabled', 'card_enabled', 'iyzico_mode', 'installments', 'transfer_enabled', 'delivery_fee', 'delivery_free_over']);
        $s->update($data);
        $this->audit->log('business.payment_settings_updated', $s, $old, array_diff_key($data, array_flip(['iyzico_api_key', 'iyzico_secret_key', 'transfer_accounts']))
            + ['iyzico_keys_changed' => isset($data['iyzico_api_key']) || isset($data['iyzico_secret_key'])]);
    }

    public function removeKeys(): void
    {
        $this->settings()->update(['iyzico_api_key' => null, 'iyzico_secret_key' => null, 'card_enabled' => false]);
        $this->audit->log('business.payment_keys_removed', $this->settings());
    }

    private function decrypt(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }
        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return null;
        }
    }
}
