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
        'footer' => null,            // alt bilgi ayarları (JSON, App\Domain\Store\Content\StoreFooter)
        'home' => null,              // ana sayfa blokları (JSON, App\Domain\Store\Storefront\HomeLayout)
        'new_product_days' => '30',  // "Yeni gelenler" ve "Yeni" rozeti: son kaç günde yayınlanan ürünler
        'phone' => null,
        'phone_label' => null,       // ör. "Dükkân"; boşsa "Telefon"
        'phone2' => null,            // ikinci telefon (isteğe bağlı)
        'phone2_label' => null,      // ör. "Cep"; boşsa "Telefon 2"
        'whatsapp' => null,
        'email' => null,
        'address' => null,
        'instagram' => null,
        'facebook' => null,
        'x' => null,
        'youtube' => null,
        'tiktok' => null,
        'linkedin' => null,
        'pinterest' => null,

        // Mağazanın e-posta hesabı (SMTP). Boşsa sistem e-posta hesabı kullanılır. Parola şifreli saklanır.
        'mail_host' => null,
        'mail_port' => '587',
        'mail_encryption' => 'tls',
        'mail_username' => null,
        'mail_password' => null,
        'mail_from_address' => null,
        'mail_from_name' => null,

        // E-bülten
        'newsletter_enabled' => '1',
        'newsletter_title' => 'Kampanyalardan ilk siz haberdar olun',
        'newsletter_text' => 'Yeni ürünler, indirimler ve size özel fırsatlar e-postanıza gelsin.',
        'newsletter_consent' => 'Kampanya ve duyurular için ticari elektronik ileti almayı kabul ediyorum.',
        'payment_logos_path' => null, // alt bilgideki ödeme logo bandı (iyzico'nun verdiği görsel)
        'print' => null, // JSON: fotokopi ayarları (App\Domain\Store\Print\PrintSettings)
        'legal_reviewed' => null, // JSON {anahtar: sürüm}: yönetimde "gözden geçirildi" işaretlenen yasal metin sürümleri (LegalDocuments)

        // Satıcı bilgileri (yasal metinler, iletişim sayfası, fatura)
        'company_title' => null,
        'company_tax_office' => null,
        'company_tax_number' => null,
        'company_mersis' => null,
        'company_esnaf_no' => null,         // şahıs işletmesi: Esnaf ve Sanatkârlar sicil no (girilirse MERSİS yerine gösterilir)

        // Teslimat (KDV dahil ücretler)
        'shipping_cargo_fee' => '0.00',
        'shipping_free_threshold' => null,   // bu tutar ve üzeri kargo ücretsiz
        'cargo_cutoff' => null,              // SS:DD; hafta içi bu saatten önceki siparişler aynı gün kargoda (boşsa gösterilmez)
        'cargo_delivery_time' => '2–3 iş günü', // kargoya verildikten sonra tahmini teslim süresi (boşsa yasal en geç 30 gün yazılır)
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
        'order_alarm' => '1', // yönetim panelinde sesli yeni sipariş alarmı (NewOrderAlerts)
        'notify_returns' => '1',
        'notify_low_stock' => '1',
        'notify_messages' => '1',          // iletişim formu mesajları

        // İade (cayma hakkı). Süre yasal en az 14 gündür.
        'return_days' => '14',
        'return_address' => null,            // boşsa mağaza adresi kullanılır
        // İade kargosu: sözleşmede "satıcının bildirdiği kargo firmasıyla yapılan iadelerde masraf satıcıya aittir".
        // Anlaşma kodu girilirse müşteri o firmayla ücretsiz gönderir; girilmezse gönderici ödemeli gönderir, ücret iadeye eklenir.
        'return_carrier' => null,
        'return_code' => null,

        // Kartla ödeme (iyzico). Anahtarlar şifreli saklanır.
        'card_enabled' => '0',
        'iyzico_mode' => 'sandbox',
        'iyzico_api_key' => null,
        'iyzico_secret_key' => null,
        // iyzico yanıt imzası: monitor (tutmazsa yönetici uyarılır, ödeme kabul edilir) | enforce (tutmazsa kabul edilmez)
        'iyzico_signature' => 'monitor',
        // iyzico Korumalı Alışveriş rozeti (vitrin): iyzico panelindeki script'in token'ı (herkese açık) ve konumu.
        'iyzico_badge_token' => null,
        'iyzico_badge_position' => 'bottomLeft',

        // Havale / EFT. Hesaplar satır satır: "Banka | Hesap sahibi | IBAN". Süresinde ödenmeyen sipariş iptal edilir.
        'transfer_enabled' => '0',
        'transfer_accounts' => null,
        'transfer_days' => '3',

        // SMS (Netgsm). log: geliştirmede kod günlüğe yazılır, SMS gönderilmez.
        'sms_driver' => 'log',
        'netgsm_usercode' => null,
        'netgsm_password' => null,
        'netgsm_header' => null,

        // e-Fatura / e-Arşiv (Ayarlar → Fatura). deneme: sahte numara ve PDF üretir, hiçbir yere gönderilmez.
        // Fatura otomatik kesilmez; yönetici Faturalar ekranından tek tek gönderir. Şifre şifreli saklanır.
        'invoice_driver' => 'deneme',        // deneme | uyumsoft
        'uyumsoft_mode' => 'test',           // test | live
        'uyumsoft_username' => null,
        'uyumsoft_password' => null,
        'invoice_sender_id' => null,         // gönderici VKN / TCKN (boşsa vergi numarası)
        'invoice_shipping_vat' => '20',      // kargo ve kapıda ödeme bedeli kaleminin KDV oranı
        'invoice_notify' => '1',             // gönderilen faturayı müşteriye e-postayla bildir (varsayılan)
    ];

    /** Veritabanında şifreli tutulan anahtarlar. */
    public const SECRETS = ['iyzico_api_key', 'iyzico_secret_key', 'netgsm_password', 'mail_password', 'uyumsoft_password'];

    private const CACHE_KEY = 'store.settings.saved';

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value'];

    /** @return array<string, ?string> */
    public static function values(): array
    {
        // Önbellekte yalnızca kayıtlı değerler tutulur; varsayılanlar her okumada eklenir. Böylece yeni bir ayar
        // anahtarı eklenip yayınlandığında eski önbellek o anahtarı eksik bırakmaz.
        $saved = Cache::rememberForever(self::CACHE_KEY, fn () => static::query()->pluck('value', 'key')->all());

        return array_merge(self::DEFAULTS, array_intersect_key($saved, self::DEFAULTS));
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
