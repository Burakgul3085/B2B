<?php

namespace App\Domain\Store\Payments;

use App\Domain\Audit\AuditLogger;
use App\Domain\Store\Sms\SmsGateway;
use App\Domain\Support\Decimal;
use App\Models\Store\District;
use App\Models\Store\Province;
use App\Models\Store\StoreSetting;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Ödeme ve teslimat ayarları (mağaza yönetimi). Gizli anahtarlar (iyzico, Netgsm şifresi) şifreli saklanır,
 * ekrana hiç geri yazılmaz; boş bırakılan gizli alan mevcut değeri korur.
 * Canlı ortamda gerçek SMS (Netgsm) yapılandırılmadan kapıda ödeme açılamaz (doğrulama kodu gönderilemez).
 */
class PaymentSettings
{
    public function __construct(private AuditLogger $audit, private SmsGateway $sms) {}

    /** @throws ValidationException */
    public function save(array $input): void
    {
        $data = Validator::make($input, [
            'shipping_cargo_fee' => ['nullable', 'string', 'max:20'],
            'shipping_free_threshold' => ['nullable', 'string', 'max:20'],
            'local_delivery_fee' => ['nullable', 'string', 'max:20'],
            'local_districts' => ['nullable', 'array'],
            'local_districts.*' => ['integer'],
            'cod_min' => ['nullable', 'string', 'max:20'],
            'cod_max' => ['nullable', 'string', 'max:20'],
            'cod_fee' => ['nullable', 'string', 'max:20'],
            'cod_max_open' => ['required', 'integer', 'min:1', 'max:10'],
            'return_days' => ['required', 'integer', 'min:14', 'max:90'],
            'return_address' => ['nullable', 'string', 'max:300'],
            'iyzico_mode' => ['required', 'in:sandbox,live'],
            'iyzico_api_key' => ['nullable', 'string', 'max:200'],
            'iyzico_secret_key' => ['nullable', 'string', 'max:200'],
            'sms_driver' => ['required', 'in:log,netgsm'],
            'netgsm_usercode' => ['nullable', 'string', 'max:40'],
            'netgsm_password' => ['nullable', 'string', 'max:100'],
            'netgsm_header' => ['nullable', 'string', 'max:20'],
        ], [], [
            'shipping_cargo_fee' => 'kargo ücreti', 'shipping_free_threshold' => 'ücretsiz kargo sınırı', 'local_delivery_fee' => 'mağaza teslimatı ücreti',
            'cod_min' => 'en düşük tutar', 'cod_max' => 'en yüksek tutar', 'cod_fee' => 'kapıda ödeme bedeli', 'cod_max_open' => 'açık sipariş sınırı',
            'return_days' => 'iade süresi', 'return_address' => 'iade adresi',
        ])->validate();

        $errors = [];
        $money = [];
        foreach (['shipping_cargo_fee', 'shipping_free_threshold', 'local_delivery_fee', 'cod_min', 'cod_max', 'cod_fee'] as $key) {
            $raw = trim((string) ($data[$key] ?? ''));
            if ($raw === '') {
                $money[$key] = in_array($key, ['shipping_cargo_fee', 'local_delivery_fee', 'cod_fee'], true) ? '0.00' : null;

                continue;
            }
            $value = Decimal::parse($raw, 2);
            if ($value === null || bccomp($value, '0', 2) < 0) {
                $errors[$key] = 'Geçerli bir tutar girin (ör. 49,90).';
            }
            $money[$key] = $value;
        }

        $eskisehir = District::where('province_id', Province::ESKISEHIR)->pluck('id')->all();
        $districts = array_values(array_intersect(array_map('intval', $data['local_districts'] ?? []), $eskisehir));
        $flags = [];
        foreach (['local_delivery_enabled', 'cod_enabled', 'cod_first_order_approval', 'card_enabled'] as $key) {
            $flags[$key] = filter_var($input[$key] ?? false, FILTER_VALIDATE_BOOL) ? '1' : '0';
        }
        if ($flags['local_delivery_enabled'] === '1' && $districts === []) {
            $errors['local_districts'] = 'Mağaza teslimatı için en az bir ilçe seçin.';
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $old = StoreSetting::values();
        StoreSetting::put($money + $flags + [
            // Tüm ilçeler seçiliyse boş bırakılır (yeni ilçe eklenirse kendiliğinden kapsar).
            'local_districts' => count($districts) === count($eskisehir) ? null : implode(',', $districts),
            'cod_max_open' => (string) $data['cod_max_open'],
            'return_days' => (string) $data['return_days'],
            'return_address' => trim((string) ($data['return_address'] ?? '')) ?: null,
            'iyzico_mode' => $data['iyzico_mode'],
            'sms_driver' => $data['sms_driver'],
            'netgsm_usercode' => trim((string) ($data['netgsm_usercode'] ?? '')) ?: null,
            'netgsm_header' => trim((string) ($data['netgsm_header'] ?? '')) ?: null,
        ]);
        foreach (['iyzico_api_key', 'iyzico_secret_key', 'netgsm_password'] as $secret) {
            if (filled($data[$secret] ?? null)) {
                StoreSetting::putSecret($secret, trim($data[$secret]));
            }
        }

        // Canlıda doğrulama SMS'i gidemiyorsa kapıda ödeme açık kalamaz.
        if (StoreSetting::flag('cod_enabled') && app()->isProduction() && ! $this->sms->isLive()) {
            StoreSetting::put(['cod_enabled' => '0']);
            throw ValidationException::withMessages(['cod_enabled' => 'Kapıda ödeme için önce Netgsm SMS bilgilerini girin; doğrulama kodu gönderilemiyor.']);
        }

        // İşlem geçmişine değişen ayarların adları yazılır; gizli anahtarların değerleri hiç yazılmaz.
        $new = StoreSetting::values();
        $changed = array_values(array_filter(array_keys($new), fn ($k) => ! in_array($k, StoreSetting::SECRETS, true) && (string) $new[$k] !== (string) ($old[$k] ?? '')));
        $renewed = array_values(array_filter(StoreSetting::SECRETS, fn ($k) => filled($data[$k] ?? null)));
        $this->audit->log('store.payment_settings_updated', null, [], ['changed' => $changed, 'secrets_renewed' => $renewed]);
    }

    public function removeSecret(string $key): void
    {
        if (in_array($key, StoreSetting::SECRETS, true)) {
            StoreSetting::putSecret($key, null);
            $this->audit->log('store.payment_secret_removed', null, [], ['key' => $key]);
        }
    }
}
