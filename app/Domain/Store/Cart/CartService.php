<?php

namespace App\Domain\Store\Cart;

use App\Domain\Store\Inventory\StoreStockService;
use App\Models\Store\StoreCart;
use App\Models\Store\StoreCartItem;
use App\Models\Store\StoreCustomer;
use App\Models\Store\StoreProductVariant;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Mağaza sepeti. Sepette yalnızca varyant ve adet tutulur; fiyat, KDV ve satılabilir stok her gösterimde
 * sunucuda yeniden hesaplanır (tarayıcıdan gelen fiyata güvenilmez). Ekleme ve adet değişikliği satılabilir
 * stokla sınırlıdır. Stok ayırma sepette yapılmaz; ödeme adımında yapılır (M6).
 */
class CartService
{
    public const MAX_QUANTITY = 999;

    public function __construct(private StoreStockService $stock) {}

    public function forCustomer(StoreCustomer $customer): StoreCart
    {
        return StoreCart::firstOrCreate(['customer_id' => $customer->id]);
    }

    public function findGuest(?string $token): ?StoreCart
    {
        return $token && strlen($token) === 40 ? StoreCart::where('token', $token)->whereNull('customer_id')->first() : null;
    }

    public function createGuest(): StoreCart
    {
        return StoreCart::create(['token' => Str::random(40)]);
    }

    /** @throws DomainException */
    public function add(StoreCart $cart, int $variantId, int $quantity): StoreCartItem
    {
        if ($quantity < 1 || $quantity > self::MAX_QUANTITY) {
            throw new DomainException('Geçerli bir adet girin.');
        }

        return DB::transaction(function () use ($cart, $variantId, $quantity) {
            $variant = $this->sellable($variantId);
            $item = StoreCartItem::where('cart_id', $cart->id)->where('variant_id', $variant->id)->lockForUpdate()->first();
            $this->assertAvailable($variant, ($item?->quantity ?? 0) + $quantity, $item?->quantity ?? 0);

            $item ??= new StoreCartItem(['cart_id' => $cart->id, 'variant_id' => $variant->id, 'quantity' => 0]);
            $item->quantity += $quantity;
            $item->save();
            $cart->touch();

            return $item;
        });
    }

    /** Adedi değiştirir; 0 ürünü çıkarır. @throws DomainException */
    public function update(StoreCart $cart, int $itemId, int $quantity): void
    {
        $item = StoreCartItem::where('cart_id', $cart->id)->findOrFail($itemId);
        if ($quantity <= 0) {
            $item->delete();

            return;
        }
        if ($quantity > self::MAX_QUANTITY) {
            throw new DomainException('Geçerli bir adet girin.');
        }

        $variant = $this->sellable($item->variant_id);
        $this->assertAvailable($variant, $quantity, 0);
        $item->update(['quantity' => $quantity]);
        $cart->touch();
    }

    public function remove(StoreCart $cart, int $itemId): void
    {
        StoreCartItem::where('cart_id', $cart->id)->whereKey($itemId)->delete();
    }

    /** Girişte misafir sepetini müşterinin sepetine katar (adetler satılabilir stokla sınırlanır) ve misafir sepetini siler. */
    public function merge(?StoreCart $guest, StoreCustomer $customer): void
    {
        if (! $guest || $guest->customer_id !== null) {
            return;
        }

        DB::transaction(function () use ($guest, $customer) {
            $cart = $this->forCustomer($customer);
            foreach ($guest->items()->get() as $item) {
                $existing = StoreCartItem::where('cart_id', $cart->id)->where('variant_id', $item->variant_id)->first();
                $wanted = ($existing?->quantity ?? 0) + $item->quantity;
                $available = $this->stock->available([$item->variant_id])[$item->variant_id] ?? 0;
                $qty = max(1, min($wanted, $available, self::MAX_QUANTITY));
                StoreCartItem::updateOrCreate(['cart_id' => $cart->id, 'variant_id' => $item->variant_id], ['quantity' => $qty]);
            }
            $guest->delete();
        });
    }

    /** Sepetteki toplam ürün adedi (başlıktaki rozet). */
    public function count(?StoreCart $cart): int
    {
        return $cart ? (int) $cart->items()->sum('quantity') : 0;
    }

    /** Sepetin güncel fiyat ve stokla hesaplanmış özeti. */
    public function summary(?StoreCart $cart): CartSummary
    {
        if (! $cart) {
            return new CartSummary([]);
        }

        $items = $cart->items()->with(['variant.product.primaryImage'])->get();
        $available = $this->stock->available($items->pluck('variant_id')->all());

        return new CartSummary($items->map(fn (StoreCartItem $item) => new CartLine($item, $available[$item->variant_id] ?? 0))->all());
    }

    /** @throws DomainException */
    private function sellable(int $variantId): StoreProductVariant
    {
        $variant = StoreProductVariant::with('product')->find($variantId);
        if (! $variant || ! $variant->is_active || ! $variant->product->is_published) {
            throw new DomainException('Bu ürün şu anda satışta değil.');
        }

        return $variant;
    }

    /** @throws DomainException */
    private function assertAvailable(StoreProductVariant $variant, int $wanted, int $alreadyInCart): void
    {
        $available = $this->stock->available([$variant->id])[$variant->id] ?? 0;
        if ($available <= 0) {
            throw new DomainException('Bu ürün tükendi.');
        }
        if ($wanted > $available) {
            $more = max(0, $available - $alreadyInCart);
            throw new DomainException($more > 0
                ? "Bu üründen stokta {$available} adet var; sepetinize en fazla {$more} adet daha ekleyebilirsiniz."
                : "Bu üründen stokta {$available} adet var ve hepsi sepetinizde.");
        }
    }
}
