<?php

namespace App\Models\Store;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Mağaza ayarları (anahtar → değer). Okumalar önbelleklenir; kayıtta önbellek temizlenir.
 * Tanımlı anahtarlar ve varsayılanları DEFAULTS'tadır; başka anahtar yazılamaz.
 */
class StoreSetting extends Model
{
    public const DEFAULTS = [
        'is_open' => '0',            // mağaza ziyaretçilere açık mı
        'announcement' => null,      // üst duyuru şeridi
        'meta_title' => null,        // ana sayfa SEO başlığı
        'meta_description' => null,  // ana sayfa SEO açıklaması
        'footer_about' => null,
        'footer' => null,              // alt bilgi ayarları (JSON, App\Domain\Store\Content\StoreFooter)
        'phone' => null,
        'whatsapp' => null,
        'email' => null,
        'address' => null,
        'instagram' => null,
        'facebook' => null,
        'payment_logos_path' => null, // alt bilgideki ödeme logo bandı (iyzico'nun verdiği görsel)

        // Satıcı bilgileri (yasal metinler, iletişim sayfası, fatura)
        'company_title' => null,
        'company_tax_office' => null,
        'company_tax_number' => null,
        'company_mersis' => null,

        // Teslimat (KDV dahil ücretler)
        'shipping_cargo_fee' => '0.00',
        'shipping_free_threshold' => null,   // bu tutar ve üzeri kargo ücretsiz
        'local_delivery_enabled' => '1',     // Eskişehir'de kendi aracımızla teslimat
        'local_delivery_fee' => '0.00',
        'local_districts' => null,           // virgülle ilçe kimlikleri; boşsa Eskişehir'in tüm ilçeleri

        // Kapıda ödeme (yalnızca kendi teslimatımızla, Eskişehir)
        'cod_enabled' => '0',
        'cod_min' => null,
        'cod_max' => '3000.00',
        'cod_fee' => '0.00',
        'cod_max_open' => '1',               // aynı telefonda teslim edilmemiş en fazla kapıda ödemeli sipariş
        'cod_first_order_approval' => '1',   // ilk siparişte yönetici teyidi

        // Yönetici bildirimleri (e-posta). Liste boşsa etkin tüm mağaza yöneticilerine gider.
        'notify_emails' => null,
        'notify_orders' => '1',
        'notify_returns' => '1',
        'notify_low_stock' => '1',
        'notify_messages' => '1',          // iletişim formu mesajları

        // İade (cayma hakkı). Süre yasal en az 14 gündür.
        'return_days' => '14',
        'return_address' => null,            // boşsa mağaza adresi kullanılır

        // Kartla ödeme (iyzico). Anahtarlar şifreli saklanır.
        'card_enabled' => '0',
        'iyzico_mode' => 'sandbox',
        'iyzico_api_key' => null,
        'iyzico_secret_key' => null,

        // Havale / EFT. Hesaplar satır satır: "Banka | Hesap sahibi | IBAN". Süresinde ödenmeyen sipariş iptal edilir.
        'transfer_enabled' => '0',
        'transfer_accounts' => null,
        'transfer_days' => '3',

        // SMS (Netgsm). log: geliştirmede kod günlüğe yazılır, SMS gönderilmez.
        'sms_driver' => 'log',
        'netgsm_usercode' => null,
        'netgsm_password' => null,
        'netgsm_header' => null,
    ];

    /** Veritabanında şifreli tutulan anahtarlar. */
    public const SECRETS = ['iyzico_api_key', 'iyzico_secret_key', 'netgsm_password'];

    private const CACHE_KEY = 'store.settings';

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value'];

    /** @return array<string, ?string> */
    public static function values(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => array_merge(
            self::DEFAULTS,
            static::query()->whereIn('key', array_keys(self::DEFAULTS))->pluck('value', 'key')->all(),
        ));
    }

    public static function get(string $key): ?string
    {
        return self::values()[$key] ?? null;
    }

    public static function isOpen(): bool
    {
        return self::get('is_open') === '1';
    }

    public static function flag(string $key): bool
    {
        return self::get($key) === '1';
    }

    /** Şifreli anahtarın çözülmüş değeri (yoksa veya çözülemezse null). */
    public static function secret(string $key): ?string
    {
        $value = self::get($key);
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return null;
        }
    }

    /** Şifreli anahtarı kaydeder; boş değer anahtarı siler. */
    public static function putSecret(string $key, ?string $plain): void
    {
        self::put([$key => $plain === null || $plain === '' ? null : Crypt::encryptString($plain)]);
    }

    /** @param  array<string, ?string>  $values */
    public static function put(array $values): void
    {
        foreach (array_intersect_key($values, self::DEFAULTS) as $key => $value) {
            static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
        Cache::forget(self::CACHE_KEY);
    }
}
