<?php

namespace App\Domain\Pricing;

use App\Domain\Support\Decimal;

/**
 * Bir ürünün belirli bir bayi için hesaplanmış fiyatı. Tüm tutarlar KDV HARİÇ (net), 4 ondalık.
 */
final class ResolvedPrice
{
    public const SOURCES = [
        'dealer_special' => 'Bayiye özel fiyat',
        'price_list' => 'Grup fiyat listesi',
        'dealer_price' => 'Standart bayi fiyatı',
        'list_price' => 'Liste fiyatı',
    ];

    public function __construct(
        public readonly int $productId,
        public readonly string $listPrice,       // genel liste fiyatı (net) — bilgi amaçlı
        public readonly string $basePrice,       // indirim öncesi birim fiyat (net)
        public readonly string $baseSource,      // SOURCES anahtarı
        public readonly string $discountPercent, // 0.00 – 99.99
        public readonly ?string $discountName,
        public readonly string $unitPrice,       // indirimli birim fiyat (net)
        public readonly string $taxRate,         // ör. 20.00
    ) {}

    public function unitPriceWithTax(): string
    {
        return Decimal::round(bcmul($this->unitPrice, bcadd('1', bcdiv($this->taxRate, '100', 6), 6), 10), 4);
    }

    public function sourceLabel(): string
    {
        return self::SOURCES[$this->baseSource];
    }

    public function hasDiscount(): bool
    {
        return bccomp($this->discountPercent, '0', 2) > 0;
    }
}
