<?php

namespace App\Domain\Ordering;

use App\Domain\Inventory\Quantity;
use App\Domain\Inventory\StockService;
use App\Domain\Pricing\PriceResolver;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\DealerRelationship;
use App\Models\Product;
use DomainException;
use Illuminate\Database\Eloquent\Builder;

/**
 * Bayi sepeti. Sepette ürün bulunması stok ayırmaz (rezervasyon yoktur).
 * Fiyat ve stok her görüntülemede güncel kurallarla yeniden hesaplanır.
 */
class CartService
{
    public const MAX_LINES = 200;

    public function __construct(private PriceResolver $prices, private StockService $stock) {}

    /** Bayinin görebileceği ve sipariş verebileceği ürünler. */
    public static function orderableProducts(): Builder
    {
        return Product::query()->notArchived()->where('is_active', true)->where('visible_to_dealers', true);
    }

    public function cart(DealerRelationship $relationship): Cart
    {
        return Cart::firstOrCreate(['dealer_relationship_id' => $relationship->id]);
    }

    /** Miktarı sepetteki mevcut miktara ekler. */
    public function add(DealerRelationship $relationship, int $productId, string $qty): CartItem
    {
        $product = self::orderableProducts()->with('unit')->find($productId)
            ?? throw new DomainException('Ürün sipariş için uygun değil.');

        $cart = $this->cart($relationship);
        $item = CartItem::where('cart_id', $cart->id)->where('product_id', $product->id)->first();
        $newQty = bcadd((string) ($item?->quantity ?? '0'), $qty, 3);

        if ($error = QuantityRules::violation($product, $newQty)) {
            throw new DomainException($error);
        }
        if (! $item && CartItem::where('cart_id', $cart->id)->count() >= self::MAX_LINES) {
            throw new DomainException('Sepette en fazla '.self::MAX_LINES.' farklı ürün olabilir.');
        }

        return CartItem::updateOrCreate(['cart_id' => $cart->id, 'product_id' => $product->id], ['quantity' => $newQty]);
    }

    public function update(CartItem $item, string $qty): void
    {
        $product = $item->product()->with('unit')->first();
        if ($error = QuantityRules::violation($product, $qty)) {
            throw new DomainException($error);
        }
        $item->update(['quantity' => $qty]);
    }

    /**
     * Sepet özeti: satırlar, güncel fiyat, kurallara uygunluk ve stok durumu.
     *
     * @return array{lines: array<int, array>, totals: array, has_issues: bool, warehouse_id: int}
     */
    public function summary(DealerRelationship $relationship, int $warehouseId): array
    {
        $cart = $this->cart($relationship);
        $items = $cart->items()->with(['product.unit', 'product.taxRate', 'product.primaryImage'])->get();

        $orderable = self::orderableProducts()->whereIn('id', $items->pluck('product_id'))->pluck('id')->flip();
        $resolved = $this->prices->resolveMany($relationship, $items->pluck('product')->filter(fn ($p) => $orderable->has($p->id)));

        $lines = [];
        $hasIssues = false;

        foreach ($items as $item) {
            $product = $item->product;
            $qty = (string) $item->quantity;
            $issue = null;
            $price = $resolved[$product->id] ?? null;

            if (! $orderable->has($product->id) || ! $price) {
                $issue = 'Bu ürün artık satışta değil; sepetten çıkarın.';
            } elseif ($rule = QuantityRules::violation($product, $qty)) {
                $issue = $rule;
            } else {
                $available = $this->stock->available($product->id, $warehouseId);
                if (bccomp($available, $qty, 3) < 0) {
                    $issue = bccomp($available, '0', 3) <= 0
                        ? 'Stokta yok. Miktarı çıkarın veya ürün talebi oluşturun.'
                        : 'Stok yetersiz (mevcut: '.Quantity::format($available).' '.$product->unit->name.').';
                }
            }

            $amounts = $price ? LineCalculator::line($price->basePrice, $qty, $price->discountPercent, $price->taxRate) : null;
            $hasIssues = $hasIssues || $issue !== null;

            $lines[] = ['item' => $item, 'product' => $product, 'price' => $price, 'amounts' => $amounts, 'issue' => $issue];
        }

        return [
            'lines' => $lines,
            'totals' => LineCalculator::totals(array_filter(array_column($lines, 'amounts'))),
            'has_issues' => $hasIssues,
            'warehouse_id' => $warehouseId,
        ];
    }

    public function count(DealerRelationship $relationship): int
    {
        return CartItem::whereHas('cart', fn ($q) => $q->where('dealer_relationship_id', $relationship->id))->count();
    }
}
