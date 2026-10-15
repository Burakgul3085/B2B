<?php

namespace Tests\Feature\Pricing;

use App\Domain\Pricing\PriceResolver;
use App\Models\Business;
use App\Models\Category;
use App\Models\DealerGroup;
use App\Models\DealerProductPrice;
use App\Models\DealerRelationship;
use App\Models\DiscountRule;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\TaxRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCatalog;
use Tests\TestCase;

/** docs/03-is-kurallari.md §4 — fiyat önceliği ve indirim kuralları. */
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
    }

    public function test_group_price_list_overrides_dealer_price_only_while_current(): void
    {
        $product = $this->makeProduct($this->business, ['dealer_price' => '8']);

        $this->inTenant($this->business, function () use ($product) {
            $list = PriceList::create(['name' => 'A', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
            PriceListItem::create(['price_list_id' => $list->id, 'product_id' => $product->id, 'price' => '7.50']);
            $group = DealerGroup::create(['name' => 'A Grubu', 'price_list_id' => $list->id]);
            $this->rel->update(['dealer_group_id' => $group->id]);
        });

        $this->assertSame(['7.5000', 'price_list'], [$this->price($product, '2026-06-01')->unitPrice, $this->price($product, '2026-06-01')->baseSource]);
        $this->assertSame('dealer_price', $this->price($product, '2027-01-02')->baseSource);
    }

    public function test_dealer_special_price_wins_and_gets_no_discount(): void
    {
        $product = $this->makeProduct($this->business, ['dealer_price' => '8']);

        $this->inTenant($this->business, function () use ($product) {
            DealerProductPrice::create(['dealer_relationship_id' => $this->rel->id, 'product_id' => $product->id, 'price' => '6']);
            DiscountRule::create(['name' => 'Büyük indirim', 'scope' => 'product', 'product_id' => $product->id, 'target' => 'all', 'percent' => '50']);
        });

        $resolved = $this->price($product);
        $this->assertSame('dealer_special', $resolved->baseSource);
        $this->assertSame('6.0000', $resolved->unitPrice);
        $this->assertFalse($resolved->hasDiscount());
    }

    public function test_dealer_special_price_respects_its_dates(): void
    {
        $product = $this->makeProduct($this->business, ['dealer_price' => '8']);
        $this->inTenant($this->business, fn () => DealerProductPrice::create([
            'dealer_relationship_id' => $this->rel->id, 'product_id' => $product->id, 'price' => '6', 'starts_on' => '2026-11-01',
        ]));

        $this->assertSame('dealer_price', $this->price($product, '2026-10-31')->baseSource);
        $this->assertSame('dealer_special', $this->price($product, '2026-11-01')->baseSource);
    }

    public function test_product_discount_beats_category_discount_and_they_do_not_stack(): void
    {
        [$product, $category] = $this->inTenant($this->business, function () {
            $category = Category::create(['name' => 'Defter']);

            return [$this->makeProduct($this->business, ['dealer_price' => '100', 'category_id' => $category->id]), $category];
        });

        $this->inTenant($this->business, function () use ($product, $category) {
            DiscountRule::create(['name' => 'Kategori %20', 'scope' => 'category', 'category_id' => $category->id, 'target' => 'all', 'percent' => '20']);
            DiscountRule::create(['name' => 'Ürün %5', 'scope' => 'product', 'product_id' => $product->id, 'target' => 'all', 'percent' => '5']);
        });

        $resolved = $this->price($product);
        $this->assertSame('5.00', $resolved->discountPercent);
        $this->assertSame('Ürün %5', $resolved->discountName);
        $this->assertSame('95.0000', $resolved->unitPrice);
    }

    public function test_subcategory_rule_beats_parent_and_dealer_target_beats_all(): void
    {
        [$product, $parent, $child] = $this->inTenant($this->business, function () {
            $parent = Category::create(['name' => 'Kalem']);
            $child = Category::create(['name' => 'Jel Kalem', 'parent_id' => $parent->id]);

            return [$this->makeProduct($this->business, ['dealer_price' => '100', 'category_id' => $child->id]), $parent, $child];
        });

        $this->inTenant($this->business, function () use ($parent, $child) {
            DiscountRule::create(['name' => 'Ana %30', 'scope' => 'category', 'category_id' => $parent->id, 'target' => 'dealer', 'dealer_relationship_id' => $this->rel->id, 'percent' => '30']);
            DiscountRule::create(['name' => 'Alt herkese %10', 'scope' => 'category', 'category_id' => $child->id, 'target' => 'all', 'percent' => '10']);
            DiscountRule::create(['name' => 'Alt bayiye %12', 'scope' => 'category', 'category_id' => $child->id, 'target' => 'dealer', 'dealer_relationship_id' => $this->rel->id, 'percent' => '12']);
        });

        $this->assertSame('Alt bayiye %12', $this->price($product)->discountName);
    }

    public function test_rules_for_other_dealers_groups_inactive_or_expired_rules_are_ignored(): void
    {
        $product = $this->makeProduct($this->business, ['dealer_price' => '100']);
        $otherRel = $this->relate($this->business, $this->makeDealer());

        $this->inTenant($this->business, function () use ($product, $otherRel) {
            $group = DealerGroup::create(['name' => 'Başka grup']);
            DiscountRule::create(['name' => 'Başka bayi', 'scope' => 'product', 'product_id' => $product->id, 'target' => 'dealer', 'dealer_relationship_id' => $otherRel->id, 'percent' => '40']);
            DiscountRule::create(['name' => 'Başka grup', 'scope' => 'product', 'product_id' => $product->id, 'target' => 'group', 'dealer_group_id' => $group->id, 'percent' => '40']);
            DiscountRule::create(['name' => 'Pasif', 'scope' => 'product', 'product_id' => $product->id, 'target' => 'all', 'percent' => '40', 'is_active' => false]);
            DiscountRule::create(['name' => 'Bitti', 'scope' => 'product', 'product_id' => $product->id, 'target' => 'all', 'percent' => '40', 'ends_on' => '2026-01-01']);
        });

        $this->assertFalse($this->price($product, '2026-10-16')->hasDiscount());
    }

    public function test_tax_inclusive_prices_are_converted_to_net_with_half_up_rounding(): void
    {
        $product = $this->makeProduct($this->business, [
            'list_price' => '12.00', 'dealer_price' => '10.00', 'price_includes_tax' => true,
            'tax_rate_id' => $this->inTenant($this->business, fn () => TaxRate::where('rate', '20.00')->value('id')),
        ]);

        $resolved = $this->price($product);
        $this->assertSame('8.3333', $resolved->unitPrice);   // 10 / 1.20
        $this->assertSame('10.0000', $resolved->listPrice);  // 12 / 1.20
        $this->assertSame('10.0000', $resolved->unitPriceWithTax());
    }

    public function test_percentage_discount_rounding(): void
    {
        $product = $this->makeProduct($this->business, ['dealer_price' => '7.25']);
        $this->inTenant($this->business, fn () => DiscountRule::create([
            'name' => '%7,5', 'scope' => 'product', 'product_id' => $product->id, 'target' => 'all', 'percent' => '7.50',
        ]));

        // 7,25 × 0,925 = 6,70625 → 6,7063 (yarım yukarı)
        $this->assertSame('6.7063', $this->price($product)->unitPrice);
    }

    public function test_resolve_many_matches_single_resolution(): void
    {
        $products = collect([
            $this->makeProduct($this->business, ['dealer_price' => '3']),
            $this->makeProduct($this->business, ['dealer_price' => null, 'list_price' => '4']),
        ]);
        $this->inTenant($this->business, fn () => DiscountRule::create([
            'name' => 'Ürün', 'scope' => 'product', 'product_id' => $products[0]->id, 'target' => 'all', 'percent' => '10',
        ]));

        $many = $this->inTenant($this->business, fn () => app(PriceResolver::class)->resolveMany($this->rel->fresh(), $products));

        foreach ($products as $product) {
            $this->assertEquals($this->price($product), $many[$product->id]);
        }
    }
}
