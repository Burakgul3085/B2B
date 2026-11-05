<?php

namespace App\Domain\Store\PaymentCodes;

use App\Domain\Audit\AuditLogger;
use App\Models\Store\StoreSetting;
use Illuminate\Validation\ValidationException;

/**
 * Özel ödeme kodu ve "Aradığınızı bulamadınız mı?" kutusu ayarları (Mağaza yönetimi → Ödeme Kodları).
 * store_settings.payment_codes anahtarında JSON olarak tutulur.
 *  - enabled: kodla ödeme açık mı (kapalıyken arama kodu tanımaz, sepetteki kod satırları sipariş edilemez)
 *  - item_name: sepette, siparişte ve faturada kalemin adı (ör. "Özel ödeme: 500 sayfa fotokopi")
 *  - box_*: ürün listelerinin altındaki ve sonuçsuz aramadaki "bize ulaşın" kutusu
 */
class PaymentCodeSettings
{
    public const DEFAULTS = [
        'enabled' => true,
        'item_name' => 'Özel ödeme',
        'box_enabled' => true,
        'box_title' => 'Aradığınızı bulamadınız mı?',
        'box_text' => 'Bazı ürünlerimiz fiziki mağazamızda olup sitemizde henüz yer almayabilir. Bize ulaşın; ürün bizdeyse size bir ödeme kodu verelim, kodu arama kutusuna yazıp güvenle ödeyin.',
        'code_hint' => 'Size verdiğimiz ödeme kodunu buraya yazın.',
    ];

    public function __construct(private AuditLogger $audit) {}

    public function all(): array
    {
        $saved = json_decode((string) StoreSetting::get('payment_codes'), true);
        $all = array_replace(self::DEFAULTS, is_array($saved) ? array_intersect_key($saved, self::DEFAULTS) : []);
        foreach (['item_name', 'box_title', 'box_text', 'code_hint'] as $key) {
            $all[$key] = trim((string) $all[$key]) ?: self::DEFAULTS[$key];
        }
        $all['enabled'] = (bool) $all['enabled'];
        $all['box_enabled'] = (bool) $all['box_enabled'];

        return $all;
    }

    public function get(string $key): mixed
    {
        return $this->all()[$key];
    }

    public function enabled(): bool
    {
        return $this->get('enabled');
    }

    /** @throws ValidationException */
    public function save(array $input): void
    {
        $errors = [];
        $text = function (string $key, int $max) use ($input, &$errors): string {
            $value = trim((string) ($input[$key] ?? ''));
            if ($value === '') {
                $errors[$key] = 'Bu alan boş bırakılamaz.';
            } elseif (mb_strlen($value) > $max) {
                $errors[$key] = "En fazla {$max} karakter yazın.";
            }

            return $value;
        };
        $data = [
            'enabled' => filter_var($input['enabled'] ?? false, FILTER_VALIDATE_BOOL),
            'item_name' => $text('item_name', 60),
            'box_enabled' => filter_var($input['box_enabled'] ?? false, FILTER_VALIDATE_BOOL),
            'box_title' => $text('box_title', 80),
            'box_text' => $text('box_text', 400),
            'code_hint' => $text('code_hint', 120),
        ];
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $old = $this->all();
        StoreSetting::put(['payment_codes' => json_encode($data)]);
        $this->audit->log('store.payment_code_settings_updated', null, ['enabled' => $old['enabled']], ['enabled' => $data['enabled']]);
    }
}
