<?php

namespace Tests\Feature\Pricing;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\TaxRate;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrders;
use Tests\TestCase;

/** İndirimli bayi fiyatı (docs/03-is-kurallari.md §4.1): ürün formu, bayi ekranları ve sipariş satırı. */
class DealerSalePriceTest extends TestCase
{
    use CreatesOrders, RefreshDatabase;

    private function payload($business, array $overrides = []): array
    {
        return $this->inTenant($business, fn () => $overrides + [
            'sku' => 'DFT-1', 'name' => 'Kareli Defter',
            'unit_id' => Unit::where('code', 'ADET')->value('id'),
            'tax_rate_id' => TaxRate::where('rate', '20.00')->value('id'),
            'list_price' => '10.00',
            'pack_quantity' => '1', 'min_order_qty' => '1', 'order_step' => '1', 'critical_stock_level' => '0',
            'is_active' => '1', 'visible_to_dealers' => '1', 'price_includes_tax' => '0',
        ]);
    }

    public function test_business_sets_and_clears_dealer_sale_price_with_validation(): void
    {
        $business = $this->makeBusiness();
        $this->actingAs($business->user, 'business');

        // Bayi fiyatından düşük olmalı.
        $this->post(route('business.products.store'), $this->payload($business, ['dealer_sale_price' => '10.00']))->assertSessionHasErrors('dealer_sale_price');
        $this->post(route('business.products.store'), $this->payload($business, ['dealer_sale_price' => '12.50']))->assertSessionHasErrors('dealer_sale_price');
        $this->post(route('business.products.store'), $this->payload($business, ['dealer_sale_price' => '0']))->assertSessionHasErrors('dealer_sale_price');
        $this->post(route('business.products.store'), $this->payload($business, [
            'dealer_sale_price' => '8.00', 'dealer_sale_starts_on' => '2026-06-10', 'dealer_sale_ends_on' => '2026-06-01',
        ]))->assertSessionHasErrors('dealer_sale_ends_on');

        $this->post(route('business.products.store'), $this->payload($business, [
            'dealer_sale_price' => '8.00', 'dealer_sale_starts_on' => '2026-06-01', 'dealer_sale_ends_on' => '2026-06-30',
        ]))->assertSessionHasNoErrors();
        $product = $this->inTenant($business, fn () => Product::where('sku', 'DFT-1')->sole());
        $this->assertSame(['8.0000', '2026-06-01', '2026-06-30'], [(string) $product->dealer_sale_price, $product->dealer_sale_starts_on->toDateString(), $product->dealer_sale_ends_on->toDateString()]);
        $this->get(route('business.products.show', $product))->assertOk()->assertSee('İndirimli bayi fiyatı');
        $this->get(route('business.products.edit', $product))->assertOk()->assertSee('2026-06-30');

        // Fiyat boşaltılınca tarihler de silinir; değişiklik ürün geçmişine yazılır.
        $this->put(route('business.products.update', $product), $this->payload($business, [
            'dealer_sale_price' => '', 'dealer_sale_starts_on' => '2026-06-01', 'dealer_sale_ends_on' => '2026-06-30',
        ]))->assertSessionHasNoErrors();
        $product->refresh();
        $this->assertNull($product->dealer_sale_price);
        $this->assertNull($product->dealer_sale_ends_on);
        $log = $this->inTenant($business, fn () => AuditLog::where('action', 'product.updated')->latest('id')->first());
        $this->assertSame('8.0000', (string) $log->old_values['dealer_sale_price']);

        // "İndirim uygula" kapatılınca (has_sale=0) girilen değerler olsa da indirim kaldırılır.
        $this->put(route('business.products.update', $product), $this->payload($business, ['has_sale' => '1', 'dealer_sale_price' => '7.00']))->assertSessionHasNoErrors();
        $this->assertSame('7.0000', (string) $product->refresh()->dealer_sale_price);
        $this->put(route('business.products.update', $product), $this->payload($business, ['has_sale' => '0', 'dealer_sale_price' => '7.00', 'dealer_sale_ends_on' => '2026-06-30']))->assertSessionHasNoErrors();
        $product->refresh();
        $this->assertNull($product->dealer_sale_price);
        $this->assertNull($product->dealer_sale_ends_on);
    }

    public function test_single_price_form_and_legacy_dealer_price_is_kept_on_edit(): void
    {
        $business = $this->makeBusiness();
        $this->actingAs($business->user, 'business');
        $this->get(route('business.products.create'))->assertOk()->assertSee('Bayi fiyatı (₺)')->assertSee('İndirim uygula')
            ->assertDontSee('Liste fiyatı')->assertDontSee('Standart bayi fiyatı');

        // Eski kayıt: ayrı bayi fiyatı (9,99) varsa form onu gösterir; kaydedince tek fiyata iner.
        $product = $this->makeProduct($business, ['sku' => 'ESKI-1', 'list_price' => '12.0000', 'dealer_price' => '9.9900']);
        $this->get(route('business.products.edit', $product))->assertOk()->assertSee('value="9.9900"', false);
        $this->put(route('business.products.update', $product), $this->payload($business, ['sku' => 'ESKI-1', 'list_price' => '9.99']))->assertSessionHasNoErrors();
        $product->refresh();
        $this->assertSame(['9.9900', null], [(string) $product->list_price, $product->dealer_price]);
    }

    public function test_order_line_uses_exact_sale_price_and_dealer_sees_struck_regular_price(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $product = $this->makeProduct($business, ['name' => 'Kareli Defter', 'dealer_price' => '9.99', 'dealer_sale_price' => '8.49']);
        $this->stockUp($business, $product, '10');

        $this->actingAs($rel->dealer->user, 'dealer');
        $this->get(route('dealer.catalog.show', $product))->assertOk()->assertSee('₺9,99')->assertSee('₺8,49')->assertSee('%15');
        $this->get(route('dealer.catalog'))->assertOk()->assertSee('%15 indirim');

        $order = $this->placeOrder($business, $rel, [$product->id => '3']);
        $item = $order->items->first();
        $this->assertSame(['8.4900', 'dealer_sale', '0.00'], [(string) $item->unit_price, $item->price_source, (string) $item->discount_percent]);
        $this->assertSame('25.47', (string) $item->line_net);   // 3 × 8,49 (yüzde yuvarlaması yok)
        $this->assertSame('30.56', (string) $order->grand_total); // + %20 KDV 5,09
    }
}
