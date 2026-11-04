<?php

namespace App\Domain\Pricing;

use App\Domain\Support\Decimal;

/**
 * Bir ürünün belirli bir bayi için hesaplanmış fiyatı. Tüm tutarlar KDV HARİÇ (net), 4 ondalık.
 */
final class ResolvedPrice
{
    public const SOURCES = [
        'dealer_sale' => 'İndirimli bayi fiyatı',
        'dealer_price' => 'Bayi fiyatı',
        'list_price' => 'Bayi fiyatı',
    ];

    public function __construct(
        public readonly int $productId,
        public readonly string $listPrice,       // genel liste fiyatı (net) — bilgi amaçlı
        public readonly string $basePrice,       // siparişe yazılan birim fiyat (net)
        public readonly string $baseSource,      // SOURCES anahtarı
        public readonly string $discountPercent, // satır indirimi (0.00; indirimli fiyat zaten birim fiyattadır)
        public readonly ?string $discountName,
        public readonly string $unitPrice,       // indirimli birim fiyat (net)
        public readonly string $taxRate,         // ör. 20.00
        public readonly ?string $wasPrice = null, // indirimli fiyatta üstü çizili indirimsiz fiyat (net)
    ) {}

    public function unitPriceWithTax(): string
    {
        return self::withTax($this->unitPrice, $this->taxRate);
    }

    public function wasPriceWithTax(): ?string
    {
        return $this->wasPrice === null ? null : self::withTax($this->wasPrice, $this->taxRate);
    }

    public function sourceLabel(): string
    {
        return self::SOURCES[$this->baseSource];
    }

    /** İndirimli bayi fiyatı uygulanıyor mu (üstü çizili fiyat ve rozet gösterilir)? */
    public function hasDiscount(): bool
    {
        return $this->wasPrice !== null;
    }

    /** Rozet için yuvarlanmış indirim yüzdesi (ör. "20"); yalnızca gösterim, hesapta kullanılmaz. */
    public function savingPercent(): string
    {
        if ($this->wasPrice === null || bccomp($this->wasPrice, '0', 4) <= 0) {
            return '0';
        }

        return Decimal::round(bcmul(bcsub('1', bcdiv($this->unitPrice, $this->wasPrice, 10), 10), '100', 10), 0);
    }

    private static function withTax(string $net, string $rate): string
    {
        return Decimal::round(bcmul($net, bcadd('1', bcdiv($rate, '100', 6), 6), 10), 4);
    }
}
