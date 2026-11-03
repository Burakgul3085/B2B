<?php

namespace App\Domain\Catalog;

use App\Models\ProductImage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * B2B ürün görseli bağlantısı. Görsel, oturum açmadan imzalı bağlantıyla sunulur (routes/web.php "product-image"):
 * bir katalog sayfasındaki onlarca görsel isteği oturum okuyup yazmaz ve PHP işçilerini meşgul etmez.
 * Bağlantı yalnızca görseli görmeye yetkili kullanıcıya, yetkisi denetlenmiş sayfada üretilir; imza görsel numarasını
 * ve boyutu kapsar, değiştirilen bağlantı 403 döner. Süre haftalık yuvarlanır (en az 7, en çok 14 gün): aynı hafta
 * içinde adres değişmez, tarayıcı önbelleği işe yarar; süresi dolan bağlantı, sayfa yenilenince yenisiyle değişir.
 */
class ProductImageUrl
{
    /** Kart ve liste görselleri için genişlik (retina ekranda da net). */
    public const CARD = 640;

    /** Küçük önizleme (tablo satırı, sepet, sipariş kalemi). */
    public const THUMB = 320;

    /** Ürün sayfası büyük görseli. */
    public const LARGE = 1000;

    public static function for(ProductImage $image, ?int $width = self::CARD): string
    {
        return URL::temporarySignedRoute('product-image', self::expires(), array_filter([
            'image' => $image->id,
            'w' => $width,
        ]));
    }

    public static function expires(): Carbon
    {
        return now()->startOfWeek()->addWeeks(2);
    }
}
