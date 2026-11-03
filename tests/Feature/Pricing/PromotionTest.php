<?php

namespace Tests\Feature\Pricing;

use App\Domain\Ordering\CartService;
use App\Domain\Ordering\OrderService;
use App\Domain\Ordering\ReturnService;
use App\Domain\Pricing\PromotionService;
use App\Models\Cart;
use App\Models\Category;
use App\Models\CurrentAccountMovement;
use App\Models\DealerGroup;
use App\Models\DealerRelationship;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Promotion;
use App\Models\PromotionRedemption;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesOrders;
use Tests\TestCase;

/** docs/03-is-kurallari.md §4.4 — B2B kupon ve kampanyaları. */
class PromotionTest extends TestCase
{
    use CreatesOrders, RefreshDatabase;

    private function promotion($business, array $attributes): Promotion
    {
        return $this->inTenant($business, fn () => Promotion::create($attributes + ['name' => 'Kampanya', 'type' => 'percent', 'value' => '10']));
    }

    private function withCode($business, DealerRelationship $rel, ?string $code): void
    {
        $this->inTenant($business, fn () => app(CartService::class)->setPromotionCode($rel, $code));
    }

    private function order($business, DealerRelationship $rel, array $lines, ?string $code = null): Order
    {
        return $this->inTenant($business, function () use ($rel, $lines, $code) {
            foreach ($lines as $productId => $qty) {
                app(CartService::class)->add($rel, $productId, $qty);
            }
            app(CartService::class)->setPromotionCode($rel, $code);

            return app(OrderService::class)->submit($rel, $rel->addresses()->value('id'), null, (string) Str::uuid())->fresh('items');
        });
    }

    private function approve($business, Order $order, array $qty = []): Order
    {
        return $this->inTenant($business, fn () => app(OrderService::class)->approve($order, $qty, $this->defaultWarehouse($business)->id, [], $business->user->id));
    }

    public function test_percent_coupon_is_applied_on_net_and_vat_is_computed_after_discount(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $a = $this->makeProduct($business, ['dealer_price' => '100.00']);
        $b = $this->makeProduct($business, ['dealer_price' => '50.00']);
        $this->stockUp($business, $a, '10');
        $this->stockUp($business, $b, '10');
        $promo = $this->promotion($business, ['name' => 'Okul', 'code' => 'OKUL10', 'value' => '10']);

        // Küçük harf ve boşlukla girilen kod da tanınır.
        $order = $this->order($business, $rel, [$a->id => '2', $b->id => '1'], ' okul10 ');

        $this->assertSame('250.00', (string) $order->subtotal);
        $this->assertSame('25.00', (string) $order->promotion_discount);
        $this->assertSame('25.00', (string) $order->discount_total);
        $this->assertSame('225.00', (string) $order->net_total);
        $this->assertSame('45.00', (string) $order->vat_total);
        $this->assertSame('270.00', (string) $order->grand_total);
        $this->assertSame(['OKUL10', 'percent', 'Okul'], [$order->promotion_code, $order->promotion_type, $order->promotion_name]);
        $this->assertEqualsCanonicalizing(['20.00', '5.00'], $order->items->map(fn ($i) => (string) $i->order_discount)->all());
        $this->assertSame('225.00', bcadd((string) $order->items->sum('line_net'), '0', 2));
        $this->inTenant($business, function () use ($promo, $order) {
            $this->assertSame(1, $promo->usedCount());
            $this->assertSame('25.00', (string) PromotionRedemption::where('order_id', $order->id)->value('amount'));
            $this->assertNull(Cart::value('promotion_code'), 'Sipariş sonrası kod sepetten temizlenir.');
        });

        // Cari borç indirimli genel toplamdan yazılır.
        $this->approve($business, $order);
        $this->assertSame('270.00', $this->balance($business, $rel));
    }

    public function test_best_automatic_campaign_applies_and_minimum_shows_next_hint(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $product = $this->makeProduct($business, ['dealer_price' => '100.00']);
        $this->stockUp($business, $product, '100');
        $this->promotion($business, ['name' => 'Küçük', 'value' => '3']);
        $this->promotion($business, ['name' => 'Büyük sepet', 'value' => '8', 'min_subtotal' => '1000']);
        $this->promotion($business, ['name' => 'Tutar', 'type' => 'fixed', 'value' => '20']);
        $this->promotion($business, ['name' => 'Pasif', 'value' => '50', 'is_active' => false]);
        $this->promotion($business, ['name' => 'Bitti', 'value' => '50', 'ends_at' => now()->subMinute()]);

        $summary = $this->inTenant($business, function () use ($rel, $product) {
            app(CartService::class)->add($rel, $product->id, '5');

            return app(CartService::class)->summary($rel, $this->defaultWarehouse($rel->business)->id);
        });
        $this->assertSame('Tutar', $summary['promotion']['applied']->promotion->name);
        $this->assertSame('20.00', $summary['promotion']['applied']->amount);
        $this->assertSame('Büyük sepet', $summary['promotion']['next']['promotion']->name);
        $this->assertSame('500.00', $summary['promotion']['next']['missing']);

        $this->actingAs($rel->dealer->user, 'dealer')->get(route('dealer.cart'))->assertOk()
            ->assertSee('Kampanya')->assertSee('Büyük sepet')->assertSee('daha ekleyin');

        $order = $this->order($business, $rel, [$product->id => '6']); // 1100 → %8 = 88
        $this->assertSame('Büyük sepet', $order->promotion_name);
        $this->assertSame('88.00', (string) $order->promotion_discount);
        $this->assertNull($order->promotion_code);
    }

    public function test_coupon_targets_group_or_selected_dealers_and_is_isolated_per_business(): void
    {
        $business = $this->makeBusiness();
        $member = $this->orderingDealer($business);
        $other = $this->orderingDealer($business);
        $product = $this->makeProduct($business, ['dealer_price' => '10.00']);
        $group = $this->inTenant($business, fn () => DealerGroup::create(['name' => 'A Grubu']));
        $this->inTenant($business, fn () => $member->update(['dealer_group_id' => $group->id]));
        $this->promotion($business, ['code' => 'GRUP', 'target' => 'group', 'dealer_group_id' => $group->id]);
        $this->promotion($business, ['code' => 'OZEL', 'target' => 'dealers', 'dealer_ids' => [$member->id]]);

        $foreign = $this->makeBusiness();
        $this->promotion($foreign, ['code' => 'YABANCI']);

        $check = function (DealerRelationship $rel, string $code) use ($business, $product) {
            return $this->inTenant($business, fn () => app(PromotionService::class)->evaluate($rel, [['product' => $product, 'net' => '100.00']], $code));
        };
        $this->assertNotNull($check($member, 'GRUP')['applied']);
        $this->assertNotNull($check($member, 'OZEL')['applied']);
        $this->assertSame('Bu kupon hesabınız için geçerli değil.', $check($other, 'GRUP')['error']);
        $this->assertSame('Bu kupon hesabınız için geçerli değil.', $check($other, 'OZEL')['error']);
        $this->assertSame('Bu kupon kodu geçerli değil.', $check($member, 'YABANCI')['error']);
    }

    public function test_usage_limits_are_enforced_and_rejected_order_releases_the_use(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $product = $this->makeProduct($business, ['dealer_price' => '10.00']);
        $this->stockUp($business, $product, '100');
        $this->promotion($business, ['code' => 'TEK', 'usage_limit' => 5, 'per_dealer_limit' => 1]);

        $first = $this->order($business, $rel, [$product->id => '1'], 'TEK');
        $this->assertSame('1.00', (string) $first->promotion_discount);

        try {
            $this->order($business, $rel, [$product->id => '1'], 'TEK');
            $this->fail('Bayi başına sınır aşıldı.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('kullanım hakkınız doldu', $e->getMessage());
        }

        $this->inTenant($business, fn () => app(OrderService::class)->reject($first, 'Stok yok'));
        $this->withCode($business, $rel, 'TEK');
        $again = $this->inTenant($business, fn () => app(OrderService::class)->submit($rel, $rel->addresses()->value('id'), null, (string) Str::uuid()));
        $this->assertSame('1.00', (string) $again->promotion_discount, 'Reddedilen siparişin kullanımı geri verilir.');
    }

    public function test_scoped_fixed_coupon_applies_only_to_category_lines_and_keeps_a_cent(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        [$parent, $child] = $this->inTenant($business, function () {
            $parent = Category::create(['name' => 'Defter']);

            return [$parent, Category::create(['name' => 'Spiralli', 'parent_id' => $parent->id])];
        });
        $inScope = $this->makeProduct($business, ['dealer_price' => '30.00', 'category_id' => $child->id]);
        $outside = $this->makeProduct($business, ['dealer_price' => '200.00']);
        $this->stockUp($business, $inScope, '10');
        $this->stockUp($business, $outside, '10');
        $this->promotion($business, ['code' => 'DEFTER', 'type' => 'fixed', 'value' => '500', 'category_ids' => [$parent->id]]);

        $order = $this->order($business, $rel, [$inScope->id => '1', $outside->id => '1'], 'DEFTER');

        $items = $order->items->keyBy('product_id');
        $this->assertSame('29.99', (string) $items[$inScope->id]->order_discount, 'Satırda en az 0,01 ₺ kalır.');
        $this->assertSame('0.01', (string) $items[$inScope->id]->line_net);
        $this->assertSame('0.00', (string) $items[$outside->id]->order_discount);
        $this->assertSame('29.99', (string) $order->promotion_discount);
    }

    public function test_partial_approval_recalculates_percent_and_return_refunds_only_what_was_paid(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $product = $this->makeProduct($business, ['dealer_price' => '10.00']);
        $this->stockUp($business, $product, '100');
        $this->promotion($business, ['code' => 'YUZDE', 'value' => '10']);

        $order = $this->order($business, $rel, [$product->id => '10'], 'YUZDE');
        $this->assertSame('10.00', (string) $order->promotion_discount);
        $item = $order->items->first();

        $order = $this->approve($business, $order, [$item->id => '3']);
        $this->assertSame('3.00', (string) $order->promotion_discount);
        $this->assertSame('27.00', (string) $order->net_total);
        $this->assertSame('32.40', (string) $order->grand_total);
        $this->assertSame('32.40', $this->balance($business, $rel));
        $this->assertSame('3.00', (string) $this->inTenant($business, fn () => PromotionRedemption::where('order_id', $order->id)->value('amount')));

        $this->inTenant($business, fn () => app(OrderService::class)->complete($order));
        $this->inTenant($business, fn () => app(ReturnService::class)->create($order, [$item->id => '1'], null, $business->user->id));
        $this->inTenant($business, fn () => app(ReturnService::class)->create($order->fresh(), [$item->id => '2'], null, $business->user->id));

        // İki iadenin toplamı ödenen tutara eşittir (kupon payı düşülür); cari sıfırlanır.
        $refunded = $this->inTenant($business, fn () => (string) OrderReturn::where('order_id', $order->id)->sum('grand_total'));
        $this->assertSame('32.40', bcadd($refunded, '0', 2));
        $this->assertSame('0.00', $this->balance($business, $rel));
        $this->assertSame(1, $this->inTenant($business, fn () => CurrentAccountMovement::where('order_id', $order->id)->where('type', 'order_debit')->count()));
    }

    public function test_fixed_coupon_on_partial_approval_never_exceeds_the_approved_amount(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $product = $this->makeProduct($business, ['dealer_price' => '10.00']);
        $this->stockUp($business, $product, '100');
        $this->promotion($business, ['code' => 'TUTAR', 'type' => 'fixed', 'value' => '50']);

        $order = $this->order($business, $rel, [$product->id => '10'], 'TUTAR');
        $this->assertSame('50.00', (string) $order->promotion_discount);

        $order = $this->approve($business, $order, [$order->items->first()->id => '2']);
        $this->assertSame('19.99', (string) $order->promotion_discount);
        $this->assertSame('0.01', (string) $order->net_total);
    }

    public function test_invalid_coupon_in_cart_blocks_submit_with_reason(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $product = $this->makeProduct($business, ['dealer_price' => '10.00']);
        $this->stockUp($business, $product, '10');
        $this->promotion($business, ['code' => 'BUYUK', 'min_subtotal' => '1000']);

        $this->actingAs($rel->dealer->user, 'dealer');
        $this->post(route('dealer.cart.add'), ['product_id' => $product->id, 'quantity' => '1']);
        $this->post(route('dealer.cart.promotion'), ['code' => 'buyuk'])->assertSessionHasNoErrors();
        $this->get(route('dealer.cart'))->assertOk()->assertSee('BUYUK')->assertSee('ve üzeri');

        $this->post(route('dealer.cart.submit'), ['address_id' => $rel->addresses()->value('id'), 'submit_token' => (string) Str::uuid()])
            ->assertSessionHasErrors('cart');
        $this->assertSame(0, $this->inTenant($business, fn () => Order::count()));

        $this->delete(route('dealer.cart.promotion.remove'));
        $this->post(route('dealer.cart.submit'), ['address_id' => $rel->addresses()->value('id'), 'submit_token' => (string) Str::uuid()])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, $this->inTenant($business, fn () => Order::count()));
    }

    public function test_business_manages_promotions_with_validation_and_tenant_isolation(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $foreignRel = $this->orderingDealer($this->makeBusiness());
        $this->actingAs($business->user, 'business');

        $this->get(route('business.promotions.index'))->assertOk()->assertSee('Henüz kupon veya kampanya yok');
        $this->get(route('business.promotions.create'))->assertOk()->assertSee($rel->dealer->legal_name)->assertDontSee($foreignRel->dealer->legal_name);

        $base = ['name' => 'Bahar', 'kind' => 'coupon', 'code' => 'bahar-25', 'type' => 'percent', 'value' => '12,5', 'target' => 'all', 'is_active' => '1'];
        $this->post(route('business.promotions.store'), ['value' => '100'] + $base)->assertSessionHasErrors('value');
        $this->post(route('business.promotions.store'), ['code' => 'ŞEKER'] + $base)->assertSessionHasErrors('code');
        $this->post(route('business.promotions.store'), ['target' => 'dealers', 'dealer_ids' => [$foreignRel->id]] + $base)->assertSessionHasErrors('dealer_ids');
        $this->post(route('business.promotions.store'), ['product_skus' => 'YOK-123'] + $base)->assertSessionHasErrors('product_skus');

        $this->post(route('business.promotions.store'), $base)->assertRedirect();
        $promo = $this->inTenant($business, fn () => Promotion::firstOrFail());
        $this->assertSame(['BAHAR-25', '12.50', true], [$promo->code, (string) $promo->value, $promo->is_active]);
        $this->post(route('business.promotions.store'), $base)->assertSessionHasErrors('code');
        $this->get(route('business.promotions.index'))->assertSee('BAHAR-25')->assertSee('Geçerli');

        // Başka işletmenin toptancısı göremez.
        $this->flushSession();
        $this->actingAs($this->makeBusiness()->user, 'business')->get(route('business.promotions.edit', $promo))->assertNotFound();
        $this->flushSession();

        // Kullanılmış kuponun kodu değişmez, silinemez.
        $product = $this->makeProduct($business, ['dealer_price' => '10.00']);
        $this->stockUp($business, $product, '10');
        $this->order($business, $rel, [$product->id => '1'], 'BAHAR-25');
        $this->actingAs($business->user, 'business');
        $this->put(route('business.promotions.update', $promo), ['code' => 'YENI'] + $base)->assertSessionHasErrors('promotion');
        $this->delete(route('business.promotions.destroy', $promo))->assertSessionHasErrors('promotion');
        $this->put(route('business.promotions.toggle', $promo));
        $this->assertFalse($this->inTenant($business, fn () => $promo->fresh()->is_active));
        $this->get(route('business.promotions.edit', $promo))->assertOk()->assertSee($rel->dealer->legal_name);
    }

    public function test_order_pages_show_coupon_line(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $product = $this->makeProduct($business, ['dealer_price' => '10.00']);
        $this->stockUp($business, $product, '10');
        $this->promotion($business, ['name' => 'Hoş geldin', 'code' => 'HOSGELDIN', 'type' => 'fixed', 'value' => '5']);
        $order = $this->order($business, $rel, [$product->id => '2'], 'HOSGELDIN');

        $this->actingAs($rel->dealer->user, 'dealer')->get(route('dealer.orders.show', $order))->assertOk()
            ->assertSee('Kupon HOSGELDIN')->assertSee('Hoş geldin')->assertSee('−₺5,00', false);
        $this->actingAs($business->user, 'business')->get(route('business.orders.show', $order))->assertOk()
            ->assertSee('Kupon HOSGELDIN');
    }

    public function test_fixed_discount_requires_minimum_above_discount(): void
    {
        $business = $this->makeBusiness();
        $this->actingAs($business->user, 'business');
        $base = ['name' => 'Sabit', 'kind' => 'campaign', 'type' => 'fixed', 'value' => '100', 'target' => 'all', 'is_active' => '1'];

        $this->post(route('business.promotions.store'), $base)->assertSessionHasErrors('min_subtotal');
        $this->post(route('business.promotions.store'), ['min_subtotal' => '100'] + $base)->assertSessionHasErrors('min_subtotal');
        $this->post(route('business.promotions.store'), ['min_subtotal' => '2000'] + $base)->assertSessionHasNoErrors();
        // Yüzde indirimde en düşük tutar zorunlu değil.
        $this->post(route('business.promotions.store'), ['name' => 'Yüzde', 'type' => 'percent', 'value' => '5'] + $base)->assertSessionHasNoErrors();

        $promos = $this->inTenant($business, fn () => Promotion::orderBy('id')->get());
        $this->assertCount(2, $promos);
        // Otomatik kampanya bayilere her zaman gösterilir; kupon yalnızca seçilirse.
        $this->assertTrue($promos->every->show_to_dealers);
        $coupon = ['name' => 'Kod', 'kind' => 'coupon', 'type' => 'percent', 'value' => '5', 'target' => 'all', 'is_active' => '1'];
        $this->post(route('business.promotions.store'), ['code' => 'GIZLI'] + $coupon)->assertSessionHasNoErrors();
        $this->post(route('business.promotions.store'), ['code' => 'ACIK', 'show_to_dealers' => '1'] + $coupon)->assertSessionHasNoErrors();
        $this->assertSame(['ACIK' => true, 'GIZLI' => false], $this->inTenant($business, fn () => Promotion::whereNotNull('code')->orderBy('code')->pluck('show_to_dealers', 'code')->all()));
    }

    public function test_dealer_campaigns_page_shows_only_what_the_dealer_can_use(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $other = $this->orderingDealer($business);
        $product = $this->makeProduct($business, ['dealer_price' => '100.00']);
        $this->stockUp($business, $product, '10');
        $category = $this->inTenant($business, fn () => Category::create(['name' => 'Defterler']));

        $this->promotion($business, ['name' => 'Sezon', 'show_to_dealers' => true, 'ends_at' => now()->addDays(5), 'category_ids' => [$category->id], 'description' => 'Okul sezonu']);
        $this->promotion($business, ['name' => 'Açık kupon', 'code' => 'ACIK10', 'show_to_dealers' => true, 'per_dealer_limit' => 2]);
        $this->promotion($business, ['name' => 'Gizli kupon', 'code' => 'GIZLI10']);
        $this->promotion($business, ['name' => 'Bitmiş', 'show_to_dealers' => true, 'ends_at' => now()->subDay()]);
        $this->promotion($business, ['name' => 'Gelecek', 'show_to_dealers' => true, 'starts_at' => now()->addDay()]);
        $this->promotion($business, ['name' => 'Pasif', 'show_to_dealers' => true, 'is_active' => false]);
        $this->promotion($business, ['name' => 'Başkasına', 'show_to_dealers' => true, 'target' => 'dealers', 'dealer_ids' => [$other->id]]);
        $this->promotion($business, ['name' => 'Tükendi', 'code' => 'TEK', 'show_to_dealers' => true, 'per_dealer_limit' => 1]);
        $this->order($business, $rel, [$product->id => '1'], 'TEK');
        $foreign = $this->makeBusiness();
        $this->promotion($foreign, ['name' => 'Yabancı', 'show_to_dealers' => true]);

        $this->actingAs($rel->dealer->user, 'dealer');
        $this->get(route('dealer.promotions.index'))->assertOk()
            ->assertSee('Sezon')->assertSee('Okul sezonu')->assertSee('5 gün kaldı')->assertSee('Defterler')
            ->assertSee('Açık kupon')->assertSee('ACIK10')->assertSee('Kalan kullanım hakkınız')
            ->assertDontSee('Gizli kupon')->assertDontSee('GIZLI10')->assertDontSee('Bitmiş')->assertDontSee('Gelecek')
            ->assertDontSee('Pasif')->assertDontSee('Başkasına')->assertDontSee('Tükendi')->assertDontSee('Yabancı');
        $this->get(route('dealer.home'))->assertOk()->assertSee('Aktif kampanyalar')->assertSee('Tümünü gör (2)');

        // Sayfadaki "Sepete uygula" kodu sepete yazar ve sepete götürür.
        $this->post(route('dealer.cart.promotion'), ['code' => 'ACIK10', 'to_cart' => '1'])->assertRedirect(route('dealer.cart'));
        $this->assertSame('ACIK10', $this->inTenant($business, fn () => Cart::where('dealer_relationship_id', $rel->id)->value('promotion_code')));
    }
}
