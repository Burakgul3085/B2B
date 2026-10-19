<?php

namespace App\Domain\Ordering;

use App\Models\DealerRelationship;
use App\Models\Order;
use DomainException;

/**
 * "Tekrar sipariş ver": geçmiş siparişin kalemlerini istenen miktarlarla sepete ekler. Fiyat ve stok sepette
 * güncel kurallarla yeniden hesaplanır; artık sipariş verilemeyen ürünler atlanır ve nedeniyle bildirilir.
 */
class ReorderService
{
    public function __construct(private CartService $cart) {}

    /** @return array{added: int, skipped: list<string>} */
    public function reorder(DealerRelationship $relationship, Order $order): array
    {
        $added = 0;
        $skipped = [];

        foreach ($order->items()->orderBy('id')->get() as $item) {
            try {
                $this->cart->add($relationship, $item->product_id, (string) $item->quantity_requested);
                $added++;
            } catch (DomainException $e) {
                $skipped[] = $item->name.': '.$e->getMessage();
            }
        }

        return ['added' => $added, 'skipped' => $skipped];
    }
}
