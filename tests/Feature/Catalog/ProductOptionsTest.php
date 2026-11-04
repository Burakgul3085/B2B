<?php

namespace Tests\Feature\Catalog;

use App\Models\Product;
use App\Models\TaxRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrders;
use Tests\TestCase;

/** Ürün seçenekleri (docs/03-is-kurallari.md §2.2) ve ayrı Kategoriler / Markalar sayfaları. */
class ProductOptionsTest extends TestCase
{
    use CreatesOrders, RefreshDatabase;

    private function optionPayload($business, Product $source, array $overrides = []): array
    {
        return $this->inTenant($business, fn () => $overrides + [
            'option_of' => $source->id, 'sku' => 'DFT-K', 'name' => $source->name, 'sale_mode' => 'single', 'min_order_qty' => '1',
            'tax_rate_id' => TaxRate::where('rate', '20.00')->value('id'), 'list_price' => '10', 'critical_stock_level' => '0',
            'is_active' => '1', 'visible_to_dealers' => '1', 'option_name' => 'Renk', 'option_value' => 'Kırmızı',
        ]);
    }

    public function test_option_is_added_from_the_product_and_linked(): void
    {
        $business = $this->makeBusiness();
        $source = $this->makeProduct($business, ['sku' => 'DFT-M', 'name' => 'Defter A4', 'dealer_price' => '7.5000']);
        $this->actingAs($business->user, 'business');

        $this->get(route('business.products.create', ['secenek' => $source->id]))->assertOk()
            ->assertSee('Defter A4')->assertSee('name="option_of"', false)->assertSee('name="source_option_value"', false);

        // Kaynağın değeri yoksa sorulur.
        $this->post(route('business.products.store'), $this->optionPayload($business, $source))->assertSessionHasErrors('source_option_value');

        $this->post(route('business.products.store'), $this->optionPayload($business, $source, ['source_option_value' => 'Mavi']))->assertSessionHasNoErrors();

        $this->inTenant($business, function () use ($source) {
            $source->refresh();
            $new = Product::where('sku', 'DFT-K')->firstOrFail();
            $this->assertNotNull($source->variant_group_id);
            $this->assertSame($source->variant_group_id, $new->variant_group_id);
            $this->assertSame(['Renk', 'Mavi'], [$source->option_name, $source->option_value]);
            $this->assertSame(['Renk', 'Kırmızı'], [$new->option_name, $new->option_value]);
        });

        $this->get(route('business.products.show', $source))->assertOk()->assertSee('Kırmızı')->assertSee('Yeni seçenek ekle')->assertSee('Seçenekten ayır');

        // Aynı değer ikinci kez eklenemez.
        $this->post(route('business.products.store'), $this->optionPayload($business, $source, ['sku' => 'DFT-K2']))->assertSessionHasErrors('option_value');
        $this->assertSame(2, $this->inTenant($business, fn () => Product::count()));
    }

    public function test_dealer_switches_between_options(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $source = $this->makeProduct($business, ['sku' => 'K-1', 'name' => 'Jel Kalem', 'option_name' => 'Renk', 'option_value' => 'Mavi']);
        $this->actingAs($business->user, 'business')
            ->post(route('business.products.store'), $this->optionPayload($business, $source, ['sku' => 'K-2', 'option_value' => 'Siyah']))
            ->assertSessionHasNoErrors();

        $this->flushSession();
        $this->actingAs($rel->dealer->user, 'dealer')->get(route('dealer.catalog.show', $source))->assertOk()
            ->assertSee('Renk')->assertSee('Siyah');
    }

    public function test_option_name_is_shared_and_detach_unlinks(): void
    {
        $business = $this->makeBusiness();
        $source = $this->makeProduct($business, ['sku' => 'K-1', 'name' => 'Jel Kalem', 'option_name' => 'Renk', 'option_value' => 'Mavi']);
        $this->actingAs($business->user, 'business')
            ->post(route('business.products.store'), $this->optionPayload($business, $source, ['sku' => 'K-2', 'option_value' => 'Siyah']));
        $other = $this->inTenant($business, fn () => Product::where('sku', 'K-2')->firstOrFail());

        // Ailedeki ürünün değeri boş bırakılamaz; seçenek adı tüm seçeneklere yayılır.
        $edit = $this->optionPayload($business, $source, ['sku' => 'K-1', 'option_name' => 'Mürekkep rengi', 'option_value' => '']);
        unset($edit['option_of']);
        $this->put(route('business.products.update', $source), $edit)->assertSessionHasErrors('option_value');
        $this->put(route('business.products.update', $source), ['option_value' => 'Mavi'] + $edit)->assertSessionHasNoErrors();
        $this->assertSame('Mürekkep rengi', $other->refresh()->option_name);

        $this->post(route('business.products.option.detach', $other))->assertRedirect();
        $this->assertNull($other->refresh()->variant_group_id);
        $this->assertNull($source->refresh()->variant_group_id); // tek kalan da bağımsız olur
    }

    public function test_cannot_add_option_to_another_business_product(): void
    {
        $business = $this->makeBusiness();
        $foreign = $this->makeProduct($this->makeBusiness(), ['sku' => 'YAB-1']);
        $this->actingAs($business->user, 'business');

        $this->get(route('business.products.create', ['secenek' => $foreign->id]))->assertNotFound();
        $this->post(route('business.products.store'), $this->optionPayload($business, $foreign))->assertNotFound();
        $this->post(route('business.products.option.detach', $foreign))->assertNotFound();
    }

    public function test_categories_and_brands_have_their_own_pages_and_variant_groups_are_gone(): void
    {
        $business = $this->makeBusiness();
        $this->actingAs($business->user, 'business');

        $this->get('/toptanci/kategoriler')->assertOk()->assertSee('Yeni kategori')->assertDontSee('Varyant');
        $this->get('/toptanci/markalar')->assertOk()->assertSee('Yeni marka')->assertDontSee('Varyant');
        $this->get('/toptanci/kategori-ve-markalar')->assertRedirect('/toptanci/kategoriler');
        $this->get('/toptanci/kategori-ve-markalar?tab=brands')->assertRedirect('/toptanci/markalar');
        $this->post('/toptanci/varyant-gruplari', ['name' => 'X', 'attributes' => 'Renk'])->assertNotFound();
        $this->get(route('business.products.create'))->assertOk()->assertSee('Seçenek adı')->assertDontSee('Varyant grubu');
    }
}
