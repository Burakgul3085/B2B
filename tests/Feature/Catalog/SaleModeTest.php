<?php

namespace Tests\Feature\Catalog;

use App\Models\Product;
use App\Models\TaxRate;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\CreatesOrders;
use Tests\TestCase;

/** Satış şekli: tek tek, koli halinde, özel kural (docs/03-is-kurallari.md §2.1). */
class SaleModeTest extends TestCase
{
    use CreatesOrders, RefreshDatabase;

    private function payload($business, array $overrides = []): array
    {
        return $this->inTenant($business, fn () => $overrides + [
            'sku' => 'KLM-1', 'name' => 'Kalem', 'tax_rate_id' => TaxRate::where('rate', '20.00')->value('id'),
            'list_price' => '10', 'critical_stock_level' => '0', 'is_active' => '1', 'visible_to_dealers' => '1',
        ]);
    }

    private function store($business, array $data)
    {
        return $this->actingAs($business->user, 'business')->post(route('business.products.store'), $this->payload($business, $data));
    }

    public function test_single_mode_resets_pack_and_step(): void
    {
        $business = $this->makeBusiness();
        $this->store($business, ['sale_mode' => 'single', 'min_order_qty' => '3', 'pack_quantity' => '24', 'order_step' => '5'])
            ->assertSessionHasNoErrors();

        $p = $this->inTenant($business, fn () => Product::with('unit')->where('sku', 'KLM-1')->firstOrFail());
        $this->assertSame('single', $p->sale_mode);
        $this->assertSame(['1.000', '3.000', '1.000'], [(string) $p->pack_quantity, (string) $p->min_order_qty, (string) $p->order_step]);
        $this->assertSame('ADET', $p->unit->code);
        $this->assertSame('Tek tek · en az 3 adet', $p->saleRuleLabel());
    }

    public function test_case_mode_derives_step_and_minimum_from_cases(): void
    {
        $business = $this->makeBusiness();
        $this->store($business, ['sale_mode' => 'case', 'pack_quantity' => '24', 'min_cases' => '2', 'min_order_qty' => '1', 'order_step' => '7'])
            ->assertSessionHasNoErrors();

        $p = $this->inTenant($business, fn () => Product::with('unit')->where('sku', 'KLM-1')->firstOrFail());
        $this->assertSame('case', $p->sale_mode);
        $this->assertSame(['24.000', '48.000', '24.000'], [(string) $p->pack_quantity, (string) $p->min_order_qty, (string) $p->order_step]);
        $this->assertSame(2, $p->minCases());
        $this->assertSame('Koli: 24 adet · en az 2 koli', $p->saleRuleLabel());
        $this->assertSame('3 koli', $p->casesLabel('72.000'));
        $this->assertNull($p->casesLabel('50'));
    }

    public function test_custom_mode_keeps_minimum_and_step(): void
    {
        $business = $this->makeBusiness();
        $this->store($business, ['sale_mode' => 'custom', 'min_order_qty' => '15', 'order_step' => '15', 'pack_quantity' => '24'])
            ->assertSessionHasNoErrors();

        $p = $this->inTenant($business, fn () => Product::with('unit')->where('sku', 'KLM-1')->firstOrFail());
        $this->assertSame(['1.000', '15.000', '15.000'], [(string) $p->pack_quantity, (string) $p->min_order_qty, (string) $p->order_step]);
        $this->assertSame("En az 15 adet, 15'er artar", $p->saleRuleLabel());
    }

    public function test_invalid_rules_are_rejected(): void
    {
        $business = $this->makeBusiness();
        $this->store($business, ['sale_mode' => 'case', 'pack_quantity' => '1', 'min_cases' => '1'])->assertSessionHasErrors('pack_quantity');
        $this->store($business, ['sale_mode' => 'case', 'pack_quantity' => '12', 'min_cases' => '0'])->assertSessionHasErrors('min_cases');
        $this->store($business, ['sale_mode' => 'custom', 'min_order_qty' => '10', 'order_step' => '15'])->assertSessionHasErrors('min_order_qty');
        $this->store($business, ['sale_mode' => 'single', 'min_order_qty' => '1.5'])->assertSessionHasErrors('min_order_qty');
        $this->store($business, ['sale_mode' => 'paket'])->assertSessionHasErrors('sale_mode');
        $this->assertSame(0, $this->inTenant($business, fn () => Product::count()));
    }

    public function test_unit_cannot_be_chosen_and_existing_unit_is_kept(): void
    {
        $business = $this->makeBusiness();
        $kg = $this->inTenant($business, fn () => Unit::where('code', 'KG')->value('id'));
        $this->store($business, ['sale_mode' => 'single', 'unit_id' => $kg])->assertSessionHasNoErrors();
        $this->assertSame('ADET', $this->inTenant($business, fn () => Product::with('unit')->firstOrFail()->unit->code));

        $old = $this->makeProduct($business, ['sku' => 'ESKI', 'unit_id' => $kg]);
        $this->actingAs($business->user, 'business')
            ->put(route('business.products.update', $old), $this->payload($business, ['sku' => 'ESKI', 'sale_mode' => 'single', 'unit_id' => null]))
            ->assertSessionHasNoErrors();
        $this->assertSame($kg, $old->refresh()->unit_id);
    }

    public function test_dealer_sees_rule_and_case_price(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $product = $this->makeProduct($business, ['sale_mode' => 'case', 'pack_quantity' => '24', 'min_order_qty' => '24', 'order_step' => '24', 'dealer_price' => '2.5000']);

        $this->actingAs($rel->dealer->user, 'dealer')->get(route('dealer.catalog.show', $product))->assertOk()
            ->assertSee('Koli: 24 adet · en az 1 koli')->assertSee('Koli fiyatı');
    }

    public function test_import_infers_sale_mode_from_rule_columns(): void
    {
        $business = $this->makeBusiness();
        $lines = [
            ['Ürün Kodu', 'Ürün Adı', 'Liste Fiyatı', 'KDV Oranı', 'Koli İçi Adet', 'En Az Sipariş', 'Artış'],
            ['A-1', 'Tekli', '1', '20', '', '', ''],
            ['A-2', 'Kolili', '1', '20', '24', '48', '24'],
            ['A-3', 'Özel', '1', '20', '', '15', '15'],
        ];
        $csv = implode("\n", array_map(fn ($r) => implode(';', $r), $lines));

        $this->actingAs($business->user, 'business')
            ->post(route('business.products.import.store'), ['file' => UploadedFile::fake()->createWithContent('u.csv', $csv), 'create_missing' => '1'])
            ->assertRedirect();

        $this->assertSame(['A-1' => 'single', 'A-2' => 'case', 'A-3' => 'custom'],
            $this->inTenant($business, fn () => Product::orderBy('sku')->pluck('sale_mode', 'sku')->all()));
    }

    public function test_unit_management_is_gone(): void
    {
        $business = $this->makeBusiness();
        $this->actingAs($business->user, 'business');

        $this->get('/toptanci/kdv-oranlari')->assertOk()->assertSee('KDV Oranları')->assertDontSee('Birimler');
        $this->get('/toptanci/kdv-ve-birimler')->assertRedirect('/toptanci/kdv-oranlari');
        $this->post('/toptanci/birimler', ['code' => 'DZN', 'name' => 'Düzine'])->assertNotFound();
    }
}
