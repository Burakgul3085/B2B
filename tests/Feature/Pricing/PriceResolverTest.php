<?php

namespace Tests\Feature\Pricing;

use App\Domain\Pricing\PriceResolver;
use App\Models\Business;
use App\Models\DealerProductPrice;
use App\Models\DealerRelationship;
use App\Models\DiscountRule;
use App\Models\Product;
use App\Models\TaxRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCatalog;
use Tests\TestCase;

/** docs/03-is-kurallari.md §4 — tüm bayilere aynı fiyat: indirimli bayi fiyatı → bayi fiyatı → liste fiyatı. */
class PriceResolverTest extends TestCase
{
    use CreatesCatalog, RefreshDatabase;

    private Business $business;

    private DealerRelationship $rel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->business = $this->makeBusiness();
        $this->rel = $this->relate($this->business, $this->makeDealer());
    }

    private function price(Product $product, ?string $date = null)
    {
        return $this->inTenant($this->business, fn () => app(PriceResolver::class)->resolve($this->rel->fresh(), $product->fresh(), $date));
    }

    public function test_falls_back_from_dealer_price_to_list_price(): void
    {
        $withDealer = $this->makeProduct($this->business, ['list_price' => '10', 'dealer_price' => '8']);
        $listOnly = $this->makeProduct($this->business, ['list_price' => '10', 'dealer_price' => null]);

        $this->assertSame(['8.0000', 'dealer_price'], [$this->price($withDealer)->unitPrice, $this->price($withDealer)->baseSource]);
        $this->assertSame(['10.0000', 'list_price'], [$this->price($listOnly)->unitPrice, $this->price($listOnly)->baseSource]);
        $this->assertFalse($this->price($withDealer)->hasDiscount());
    }

    public function test_dealer_sale_price_applies_only_within_its_dates(): void
    {
        $product = $this->makeProduct($this->business, [
            'dealer_price' => '10', 'dealer_sale_price' => '8', 'dealer_sale_starts_on' => '2026-06-01', 'dealer_sale_ends_on' => '2026-06-30',
        ]);

        $sale = $this->price($product, '2026-06-30'); // bitiş günü dahil
        $this->assertSame(['8.0000', '8.0000', 'dealer_sale', '10.0000', '0.00'], [$sale->basePrice, $sale->unitPrice, $sale->baseSource, $sale->wasPrice, $sale->discountPercent]);
        $this->assertTrue($sale->hasDiscount());
        $this->assertSame('20', $sale->savingPercent());

        $this->assertSame(['10.0000', 'dealer_price'], [$this->price($product, '2026-05-31')->unitPrice, $this->price($product, '2026-05-31')->baseSource]);
        $this->assertFalse($this->price($product, '2026-07-01')->hasDiscount());
    }

    public function test_sale_not_below_regular_price_is_ignored(): void
    {
        // Bayi fiyatı sonradan indirimli fiyatın altına çekilirse indirimli fiyat uygulanmaz (bayi daha pahalıya almaz).
        $product = $this->makeProduct($this->business, ['dealer_price' => '10', 'dealer_sale_price' => '9']);
        $this->inTenant($this->business, fn () => $product->update(['dealer_price' => '8']));

        $this->assertSame(['8.0000', 'dealer_price'], [$this->price($product)->unitPrice, $this->price($product)->baseSource]);
        $this->assertSame(0, $this->inTenant($this->business, fn () => Product::onDealerSale('2026-06-01')->count()));
    }

    public function test_retired_special_prices_and_discount_rules_no_longer_apply(): void
    {
        $product = $this->makeProduct($this->business, ['dealer_price' => '8']);
        $this->inTenant($this->business, function () use ($product) {
            DealerProductPrice::create(['dealer_relationship_id' => $this->rel->id, 'product_id' => $product->id, 'price' => '6']);
            DiscountRule::create(['name' => 'Eski kural', 'scope' => 'product', 'product_id' => $product->id, 'target' => 'all', 'percent' => '50']);
        });

        $resolved = $this->price($product);
        $this->assertSame(['8.0000', 'dealer_price'], [$resolved->unitPrice, $resolved->baseSource]);
        $this->assertFalse($resolved->hasDiscount());
    }

    public function test_tax_inclusive_prices_are_converted_to_net_with_half_up_rounding(): void
    {
        $product = $this->makeProduct($this->business, [
            'list_price' => '12.00', 'dealer_price' => '10.00', 'dealer_sale_price' => '9.00', 'price_includes_tax' => true,
            'tax_rate_id' => $this->inTenant($this->business, fn () => TaxRate::where('rate', '20.00')->value('id')),
        ]);

        $resolved = $this->price($product);
        $this->assertSame('7.5000', $resolved->unitPrice);   // 9 / 1.20
        $this->assertSame('8.3333', $resolved->wasPrice);    // 10 / 1.20
        $this->assertSame('10.0000', $resolved->listPrice);  // 12 / 1.20
        $this->assertSame('9.0000', $resolved->unitPriceWithTax());
        $this->assertSame('10.0000', $resolved->wasPriceWithTax());
    }

    public function test_resolve_many_matches_single_resolution(): void
    {
        $products = collect([
            $this->makeProduct($this->business, ['dealer_price' => '3', 'dealer_sale_price' => '2.50']),
            $this->makeProduct($this->business, ['dealer_price' => null, 'list_price' => '4']),
        ]);

        $many = $this->inTenant($this->business, fn () => app(PriceResolver::class)->resolveMany($this->rel->fresh(), $products));

        foreach ($products as $product) {
            $this->assertEquals($this->price($product), $many[$product->id]);
        }
    }
}
