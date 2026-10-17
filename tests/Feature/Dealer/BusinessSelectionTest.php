<?php

namespace Tests\Feature\Dealer;

use App\Http\Middleware\SetDealerContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Bir bayi birden fazla toptancıyla çalışabilir; her ilişki ayrı tutulur. */
class BusinessSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_dealer_with_single_wholesaler_enters_directly(): void
    {
        $business = $this->makeBusiness();
        $dealer = $this->makeDealer();
        $this->relate($business, $dealer);

        $this->actingAs($dealer, 'dealer')->get('/bayi')->assertOk()->assertSee($business->display_name);
    }

    public function test_dealer_with_multiple_wholesalers_must_choose(): void
    {
        $a = $this->makeBusiness();
        $b = $this->makeBusiness();
        $dealer = $this->makeDealer();
        $this->relate($a, $dealer);
        $relB = $this->relate($b, $dealer);

        $this->actingAs($dealer, 'dealer')->get('/bayi')->assertRedirect(route('dealer.select-business'));

        $this->get(route('dealer.select-business'))->assertOk()->assertSee($a->display_name)->assertSee($b->display_name);

        $this->post(route('dealer.select-business.store'), ['relationship_id' => $relB->id])
            ->assertRedirect(route('dealer.home'))
            ->assertSessionHas(SetDealerContext::SESSION_KEY, $relB->id);

        $this->get('/bayi')->assertOk()->assertSee($b->display_name);
    }

    public function test_dealer_cannot_select_relationship_of_another_dealer(): void
    {
        $business = $this->makeBusiness();
        $dealer = $this->makeDealer();
        $this->relate($business, $dealer);
        $this->relate($this->makeBusiness(), $dealer);
        $foreign = $this->relate($business, $this->makeDealer());

        $this->actingAs($dealer, 'dealer')
            ->post(route('dealer.select-business.store'), ['relationship_id' => $foreign->id])
            ->assertNotFound();
    }

    public function test_forged_relationship_id_in_session_is_discarded(): void
    {
        $business = $this->makeBusiness();
        $dealer = $this->makeDealer();
        $this->relate($business, $dealer);
        $foreign = $this->relate($this->makeBusiness(), $this->makeDealer());

        $this->actingAs($dealer, 'dealer')
            ->withSession([SetDealerContext::SESSION_KEY => $foreign->id])
            ->get('/bayi')
            ->assertOk()
            ->assertSee($business->display_name);
    }
}
