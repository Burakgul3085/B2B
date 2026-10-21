<?php

namespace App\Domain\Store\Inventory;

use DomainException;

/** Satılabilir stok yetmedi. $available: varyant id => satılabilir adet (kullanıcıya "en fazla X" demek için). */
class InsufficientStoreStock extends DomainException
{
    /** @param  array<int, int>  $available */
    public function __construct(public readonly array $available)
    {
        parent::__construct('Stok yetersiz.');
    }
}
