<?php

namespace Tests\Feature\Dealers;

use App\Http\Middleware\SetDealerContext;
use App\Models\DealerAddress;
use App\Models\DealerGroup;
use App\Models\DealerProductPrice;
use App\Models\DealerRelationship;
use App\Models\DiscountRule;
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

    public function test_retired_pricing_routes_are_gone_and_group_field_is_ignored(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->relate($business, $this->makeDealer());
        $group = $this->inTenant($business, fn () => DealerGroup::create(['name' => 'Eski grup']));
        $this->actingAs($business->user, 'business');

        foreach (['business.pricing', 'business.price-lists.show', 'business.discounts.store', 'business.dealer-groups.index', 'business.dealers.prices.store'] as $name) {
            $this->assertFalse(app('router')->has($name), $name);
        }
        $this->post("/toptanci/bayiler/{$rel->id}/fiyatlar", ['product_id' => $this->makeProduct($business)->id, 'price' => '1'])->assertNotFound();
        $this->post('/toptanci/indirimler', ['name' => 'X'])->assertNotFound();

        // Bayi koşulları kaydedilirken gönderilen grup alanı yazılmaz.
        $term = (int) $this->inTenant($business, fn () => $rel->fresh()->payment_term_days);
        $this->put(route('business.dealers.update', $rel), ['dealer_code' => $rel->dealer_code, 'payment_term_days' => '45', 'dealer_group_id' => $group->id])
            ->assertSessionHasNoErrors();
        $rel->refresh();
        // Sonra öde kodla kapalıyken (b2b.pay_later_enabled) vade günü de panelden değişmez.
        $this->assertSame([$term, null], [(int) $rel->payment_term_days, $rel->dealer_group_id]);
        $this->assertSame(0, $this->inTenant($business, fn () => DealerProductPrice::count() + PriceListItem::count() + DiscountRule::count()));
    }
}
