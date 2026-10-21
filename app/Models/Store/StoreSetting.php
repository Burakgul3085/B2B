<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

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
        'phone' => null,
        'whatsapp' => null,
        'email' => null,
        'address' => null,
        'instagram' => null,
        'facebook' => null,
    ];

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

    /** @param  array<string, ?string>  $values */
    public static function put(array $values): void
    {
        foreach (array_intersect_key($values, self::DEFAULTS) as $key => $value) {
            static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
        Cache::forget(self::CACHE_KEY);
    }
}
