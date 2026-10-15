<?php

namespace Tests\Feature\Dealers;

use App\Http\Middleware\SetDealerContext;
use App\Models\Category;
use App\Models\DealerAddress;
use App\Models\DealerGroup;
use App\Models\DealerProductPrice;
use App\Models\DealerRelationship;
use App\Models\DiscountRule;
use App\Models\PriceList;
use App\Models\PriceListItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesCatalog;
use Tests\TestCase;

class DealerAddressAndPricingAccessTest extends TestCase
{
    use CreatesCatalog, RefreshDatabase;

    private function address(array $overrides = []): array
    {
        return $overrides + ['title' => 'Depo', 'recipient_name' => 'Ali Veli', 'phone' => '0212 123 45 67', 'city' => 'İstanbul', 'district' => 'Şişli', 'address_line' => 'Halaskargazi Cd. 10'];
    }

    public function test_dealer_manages_addresses_and_single_default(): void
    {
        $business = $this->makeBusiness();
        $dealer = $this->makeDealer();
        $rel = $this->relate($business, $dealer);
        $this->actingAs($dealer, 'dealer');

        $this->post(route('dealer.addresses.store'), $this->address(['title' => 'Birinci']))->assertSessionHasNoErrors()->assertRedirect();
        $this->post(route('dealer.addresses.store'), $this->address(['title' => 'İkinci', 'is_default' => '1']))->assertSessionHasNoErrors()->assertRedirect();

        $addresses = $this->inTenant($business, fn () => DealerAddress::where('dealer_relationship_id', $rel->id)->orderBy('id')->get());
        $this->assertFalse($addresses[0]->is_default);
        $this->assertTrue($addresses[1]->is_default);

        $this->delete(route('dealer.addresses.destroy', $addresses[1]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($addresses[0]->fresh()->is_default);
        $this->assertNotNull($addresses[1]->fresh()->archived_at);
        $this->get(route('dealer.addresses.index'))->assertOk()->assertSee('Birinci')->assertDontSee('İkinci');
    }

    public function test_dealer_cannot_edit_addresses_when_not_allowed_but_can_pick_default(): void
    {
        $business = $this->makeBusiness();
        $dealer = $this->makeDealer();
        $rel = $this->relate($business, $dealer);
        $rel->forceFill(['can_edit_addresses' => false])->saveQuietly();
        [$first, $second] = $this->inTenant($business, fn () => [
            $rel->addresses()->create($this->address(['title' => 'A', 'is_default' => true])),
            $rel->addresses()->create($this->address(['title' => 'B'])),
        ]);
        $this->actingAs($dealer, 'dealer');

        $this->post(route('dealer.addresses.store'), $this->address())->assertForbidden();
        $this->put(route('dealer.addresses.update', $first), $this->address(['title' => 'Değişti']))->assertForbidden();
        $this->delete(route('dealer.addresses.destroy', $first))->assertForbidden();

        $this->put(route('dealer.addresses.default', $second))->assertRedirect();
        $this->assertTrue($second->fresh()->is_default);
    }

    public function test_dealer_cannot_touch_addresses_of_other_dealers_or_wholesalers(): void
    {
        $a = $this->makeBusiness();
        $b = $this->makeBusiness();
        $dealer = $this->makeDealer();
        $this->relate($a, $dealer);
        $otherRelSameBusiness = $this->relate($a, $this->makeDealer());
        $otherRelOtherBusiness = $this->relate($b, $this->makeDealer());

        $foreign1 = $this->inTenant($a, fn () => $otherRelSameBusiness->addresses()->create($this->address()));
        $foreign2 = $this->inTenant($b, fn () => $otherRelOtherBusiness->addresses()->create($this->address()));

        $this->actingAs($dealer, 'dealer');
        $this->put(route('dealer.addresses.update', $foreign1), $this->address(['title' => 'X']))->assertNotFound();
        $this->put(route('dealer.addresses.default', $foreign1))->assertNotFound();
        $this->delete(route('dealer.addresses.destroy', $foreign2))->assertNotFound();
        $this->assertSame('Depo', $foreign1->fresh()->title);
    }

    public function test_addresses_are_separate_per_wholesaler(): void
    {
        $a = $this->makeBusiness();
        $b = $this->makeBusiness();
        $dealer = $this->makeDealer();
        $relA = $this->relate($a, $dealer);
        $this->relate($b, $dealer);
        $this->inTenant($a, fn () => $relA->addresses()->create($this->address(['title' => 'Sadece A için'])));

        $this->actingAs($dealer, 'dealer')->withSession([SetDealerContext::SESSION_KEY => $relA->id])
            ->get(route('dealer.addresses.index'))->assertSee('Sadece A için');

        $this->flushSession();
        $relB = $this->inTenant($b, fn () => DealerRelationship::where('dealer_id', $dealer->dealer_id)->sole());
        $this->actingAs($dealer, 'dealer')->withSession([SetDealerContext::SESSION_KEY => $relB->id])
            ->get(route('dealer.addresses.index'))->assertDontSee('Sadece A için');
    }

    public function test_pricing_inputs_reject_records_of_another_business(): void
    {
        $a = $this->makeBusiness();
        $b = $this->makeBusiness();
        $relA = $this->relate($a, $this->makeDealer());
        $foreignProduct = $this->makeProduct($b);
        $foreignCategory = $this->inTenant($b, fn () => Category::create(['name' => 'B kategorisi']));
        $foreignGroup = $this->inTenant($b, fn () => DealerGroup::create(['name' => 'B grubu']));
        $foreignList = $this->inTenant($b, fn () => PriceList::create(['name' => 'B listesi']));
        $ownList = $this->inTenant($a, fn () => PriceList::create(['name' => 'A listesi']));

        $this->actingAs($a->user, 'business');

        $this->post(route('business.dealers.prices.store', $relA), ['product_id' => $foreignProduct->id, 'price' => '1'])->assertSessionHasErrors('product_id');
        $this->post(route('business.price-lists.items.store', $ownList), ['product_id' => $foreignProduct->id, 'price' => '1'])->assertSessionHasErrors('product_id');
        $this->post(route('business.discounts.store'), ['name' => 'X', 'scope' => 'category', 'category_id' => $foreignCategory->id, 'target' => 'all', 'percent' => '5'])->assertSessionHasErrors('category_id');
        $this->post(route('business.discounts.store'), ['name' => 'X', 'scope' => 'product', 'product_id' => $this->makeProduct($a)->id, 'target' => 'group', 'dealer_group_id' => $foreignGroup->id, 'percent' => '5'])->assertSessionHasErrors('dealer_group_id');
        $this->post(route('business.dealer-groups.store'), ['name' => 'Grup', 'price_list_id' => $foreignList->id])->assertSessionHasErrors('price_list_id');
        $this->get(route('business.price-lists.show', $foreignList))->assertNotFound();

        $this->assertSame(0, $this->inTenant($a, fn () => DealerProductPrice::count() + PriceListItem::count() + DiscountRule::count()));
    }

    public function test_discount_percent_must_be_between_0_and_100(): void
    {
        $business = $this->makeBusiness();
        $product = $this->makeProduct($business);
        $this->actingAs($business->user, 'business');

        foreach (['0', '100', '-5', 'abc'] as $bad) {
            $this->post(route('business.discounts.store'), ['name' => 'X', 'scope' => 'product', 'product_id' => $product->id, 'target' => 'all', 'percent' => $bad])
                ->assertSessionHasErrors('percent');
        }
    }

    public function test_price_list_fill_uses_dealer_price_with_percentage(): void
    {
        $business = $this->makeBusiness();
        $p1 = $this->makeProduct($business, ['dealer_price' => '10.0000']);
        $p2 = $this->makeProduct($business, ['dealer_price' => null, 'list_price' => '20.0000']);
        $list = $this->inTenant($business, fn () => PriceList::create(['name' => 'Kampanya']));

        $this->actingAs($business->user, 'business')
            ->post(route('business.price-lists.fill', $list), ['percent' => '-10'])->assertSessionHasNoErrors()->assertRedirect();

        $prices = $this->inTenant($business, fn () => PriceListItem::where('price_list_id', $list->id)->pluck('price', 'product_id'));
        $this->assertSame('9.0000', (string) $prices[$p1->id]);
        $this->assertSame('18.0000', (string) $prices[$p2->id]);
    }
}
