<?php

namespace App\Domain\Store\Orders;

use App\Domain\Support\Decimal;
use App\Models\Store\StoreCodBlock;
use App\Models\Store\StoreOrder;
use App\Models\Store\StoreSetting;

/**
 * Kapıda ödeme uygunluğu (docs/13-magaza-mimarisi.md §8.4). Yalnızca mağaza teslimatıyla (Eskişehir'in seçili
 * ilçeleri) kullanılabilir; tutar sınırları, açık sipariş sınırı ve teslim almayanlar listesi denetlenir.
 * Kurallar her istekte sunucuda yeniden denetlenir; ekranda gizlemek yeterli sayılmaz.
 */
class CodPolicy
{
    public function __construct(private DeliveryRules $delivery) {}

    /**
     * Uygun değilse kullanıcıya gösterilecek gerekçeyi, uygunsa null döndürür.
     *
     * @param  string|null  $addressKey  teslimat adresinin anahtarı (addressKey); engel listesi telefona ve adrese bakar
     */
    public function refusal(string $deliveryMethod, int $provinceId, int $districtId, string $total, string $phone, ?string $addressKey = null): ?string
    {
        if (! StoreSetting::flag('cod_enabled')) {
            return 'Kapıda ödeme şu anda kullanılamıyor.';
        }
        if ($deliveryMethod !== 'local' || ! $this->delivery->isLocal($provinceId, $districtId)) {
            return 'Kapıda ödeme yalnızca Eskişehir içinde mağaza teslimatında geçerlidir.';
        }

        $min = Decimal::parse(StoreSetting::get('cod_min'), 2);
        $max = Decimal::parse(StoreSetting::get('cod_max'), 2);
        if ($min !== null && bccomp($total, $min, 2) < 0) {
            return 'Kapıda ödeme için sipariş tutarı en az '.number_format((float) $min, 2, ',', '.').' ₺ olmalıdır.';
        }
        if ($max !== null && bccomp($max, '0', 2) > 0 && bccomp($total, $max, 2) > 0) {
            return 'Kapıda ödeme '.number_format((float) $max, 2, ',', '.').' ₺ ve altındaki siparişlerde geçerlidir.';
        }

        if (StoreCodBlock::where('phone', $phone)->exists()) {
            return 'Bu telefon numarasıyla kapıda ödeme kullanılamıyor. Kartla ödeyebilirsiniz.';
        }
        if ($addressKey && StoreCodBlock::where('address_key', $addressKey)->exists()) {
            return 'Bu teslimat adresine kapıda ödeme kullanılamıyor. Kartla ödeyebilirsiniz.';
        }

        $maxOpen = max(1, (int) (StoreSetting::get('cod_max_open') ?? 1));
        if (StoreOrder::where('payment_method', 'cod')->where('phone', $phone)->whereIn('status', StoreOrder::OPEN)->count() >= $maxOpen) {
            return 'Teslim edilmemiş kapıda ödemeli siparişiniz var. Yeni siparişte kartla ödeyebilirsiniz.';
        }

        return null;
    }

    /**
     * Adresin karşılaştırma anahtarı: ilçe + mahalle + açık adres; Türkçe karakterler sadeleştirilir, "Mah./Mahallesi",
     * "Cad.", "Sok./Sk.", "No", "D./Daire", "Kat", "Apt." gibi yazım farkları ve noktalama yok sayılır.
     */
    public static function addressKey(int $districtId, ?string $neighborhood, string $address): string
    {
        $text = mb_strtolower(strtr(trim(($neighborhood ?? '').' '.$address), ['I' => 'ı', 'İ' => 'i']), 'UTF-8');
        $text = strtr($text, ['ı' => 'i', 'ş' => 's', 'ğ' => 'g', 'ü' => 'u', 'ö' => 'o', 'ç' => 'c', 'â' => 'a', 'î' => 'i', 'û' => 'u']);
        $text = preg_replace('/[^a-z0-9]+/', ' ', $text);
        $noise = ['mahallesi', 'mahalle', 'mah', 'mh', 'caddesi', 'cadde', 'cad', 'cd', 'sokagi', 'sokak', 'sok', 'sk', 'bulvari', 'bulvar', 'bulv', 'blv',
            'no', 'numara', 'nolu', 'daire', 'd', 'kat', 'k', 'apartmani', 'apartman', 'apt', 'ap', 'blok', 'site', 'sitesi'];
        $words = array_filter(explode(' ', $text), fn ($w) => $w !== '' && ! in_array($w, $noise, true));

        return hash('sha256', $districtId.'|'.implode('', $words));
    }

    /** İlk kapıda ödemeli siparişte (ve ayar açıksa) yönetici teyidi istenir. */
    public function needsApproval(string $phone): bool
    {
        return StoreSetting::flag('cod_first_order_approval')
            && ! StoreOrder::where('payment_method', 'cod')->where('phone', $phone)->where('status', 'delivered')->exists();
    }

    public function fee(): string
    {
        return Decimal::parse(StoreSetting::get('cod_fee'), 2) ?? '0.00';
    }
}
