<?php

namespace App\Domain\Inventory;

use DomainException;

class InsufficientStock extends DomainException
{
    public function __construct(
        public readonly int $productId,
        public readonly string $productName,
        public readonly string $available,
        public readonly string $requested,
    ) {
        parent::__construct(sprintf(
            '"%s" için yeterli stok yok. Mevcut: %s, istenen: %s.',
            $productName, Quantity::format($available), Quantity::format($requested),
        ));
    }
}
