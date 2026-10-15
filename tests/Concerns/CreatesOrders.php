<?php

namespace Tests\Concerns;

use App\Domain\Inventory\StockService;
use App\Domain\Ordering\CartService;
use App\Domain\Ordering\OrderService;
use App\Models\Business;
use App\Models\CurrentAccount;
use App\Models\DealerRelationship;
use App\Models\DealerUser;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Str;

trait CreatesOrders
{
    use CreatesCatalog;

    /** Cari hesabı ve varsayılan adresi olan, sipariş verebilir bayi ilişkisi. */
    protected function orderingDealer(Business $business, ?DealerUser $user = null): DealerRelationship
    {
        $user ??= $this->makeDealer();
        $rel = $this->relate($business, $user);

        $this->inTenant($business, function () use ($rel) {
            CurrentAccount::create(['dealer_relationship_id' => $rel->id, 'code' => $rel->dealer_code]);
            $rel->addresses()->create([
                'title' => 'Merkez', 'recipient_name' => 'Teslim Alan', 'phone' => '0212 000 00 00',
                'city' => 'İstanbul', 'district' => 'Kadıköy', 'address_line' => 'Moda Cd. 1', 'is_default' => true,
            ]);
        });

        return $rel->fresh();
    }

    protected function stockUp(Business $business, Product $product, string $qty): void
    {
        $this->inTenant($business, fn () => app(StockService::class)->adjust($product->fresh('unit'), $this->defaultWarehouse($business), 'increase', $qty));
    }

    /** Sepete ekleyip servis üzerinden sipariş oluşturur. @param array<int, string> $lines product_id => qty */
    protected function placeOrder(Business $business, DealerRelationship $rel, array $lines): Order
    {
        return $this->inTenant($business, function () use ($rel, $lines) {
            foreach ($lines as $productId => $qty) {
                app(CartService::class)->add($rel, $productId, $qty);
            }
            $addressId = $rel->addresses()->value('id');

            return app(OrderService::class)->submit($rel, $addressId, null, (string) Str::uuid())->fresh('items');
        });
    }

    protected function balance(Business $business, DealerRelationship $rel): string
    {
        return (string) $this->inTenant($business, fn () => CurrentAccount::where('dealer_relationship_id', $rel->id)->value('balance'));
    }

    protected function stockQty(Business $business, Product $product): string
    {
        return $this->inTenant($business, fn () => app(StockService::class)->available($product->id, $this->defaultWarehouse($business)->id));
    }
}
