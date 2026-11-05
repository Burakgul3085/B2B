<?php

namespace App\Domain\Store\Print;

use DomainException;

/**
 * Basılacak sayfa sayısı özel teklif eşiğine ulaştı: fiyat verilmez, sepete eklenemez, sipariş verilemez.
 * Müşteriye mağazanın iletişim bilgileriyle birlikte yönetimde yazılan metin gösterilir.
 */
final class SpecialQuoteRequired extends DomainException
{
    public function __construct(public readonly int $pages, public readonly int $threshold, string $text)
    {
        parent::__construct($text);
    }
}
