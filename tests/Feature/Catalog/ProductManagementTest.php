<?php

namespace Tests\Feature\Catalog;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\TaxRate;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesCatalog;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use CreatesCatalog, RefreshDatabase;

    private function payload($business, array $overrides = []): array
    {
        return $this->inTenant($business, fn () => $overrides + [
            'sku' => 'KLM-001', 'barcode' => '8690000000017', 'name' => 'Tükenmez Kalem',
            'unit_id' => Unit::where('code', 'ADET')->value('id'),
            'tax_rate_id' => TaxRate::where('rate', '20.00')->value('id'),
            'list_price' => '8.50', 'dealer_price' => '7.25', 'purchase_price' => '4.20',
            'pack_quantity' => '50', 'min_order_qty' => '12', 'order_step' => '12', 'critical_stock_level' => '100',
            'is_active' => '1', 'visible_to_dealers' => '1', 'price_includes_tax' => '0',
        ]);
    }

    public function test_business_creates_a_product(): void
    {
        $business = $this->makeBusiness();

        $this->actingAs($business->user, 'business')
            ->post(route('business.products.store'), $this->payload($business))
            ->assertSessionHasNoErrors()->assertRedirect()
            ->assertRedirect();

        $product = $this->inTenant($business, fn () => Product::where('sku', 'KLM-001')->firstOrFail());
        $this->assertSame('8.5000', (string) $product->list_price);
        $this->assertSame($business->id, $product->business_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'product.created', 'entity_id' => $product->id]);
    }

    public function test_license_product_limit_is_enforced(): void
    {
        $business = $this->makeBusiness();
        $business->licenses()->create(['plan' => 'Mini', 'starts_on' => now()->subDay()->toDateString(), 'max_products' => 1]);
        $this->makeProduct($business);

        $this->actingAs($business->user, 'business')
            ->post(route('business.products.store'), $this->payload($business))
            ->assertSessionHasErrors('name');
        $this->assertSame(1, $this->inTenant($business, fn () => Product::count()));
    }

    public function test_sku_and_barcode_are_unique_per_business_only(): void
    {
        $a = $this->makeBusiness();
        $b = $this->makeBusiness();
        $this->makeProduct($a, ['sku' => 'KLM-001', 'barcode' => '8690000000017']);

        $this->actingAs($a->user, 'business')
            ->post(route('business.products.store'), $this->payload($a))
            ->assertSessionHasErrors(['sku', 'barcode']);

        // Başka işletme aynı kodu kullanabilir.
        $this->flushSession();
        $this->actingAs($b->user, 'business')
            ->post(route('business.products.store'), $this->payload($b))
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(1, $this->inTenant($b, fn () => Product::where('sku', 'KLM-001')->count()));
    }

    public function test_category_of_another_business_is_rejected(): void
    {
        $a = $this->makeBusiness();
        $b = $this->makeBusiness();
        $foreignCategory = $this->inTenant($b, fn () => Category::create(['name' => 'Kalem']));

        $this->actingAs($a->user, 'business')
            ->post(route('business.products.store'), $this->payload($a, ['category_id' => $foreignCategory->id]))
            ->assertSessionHasErrors('category_id');
    }

    public function test_order_rules_are_validated(): void
    {
        $business = $this->makeBusiness();
        $this->actingAs($business->user, 'business');

        $this->post(route('business.products.store'), $this->payload($business, ['min_order_qty' => '10', 'order_step' => '12']))
            ->assertSessionHasErrors('min_order_qty');

        $this->post(route('business.products.store'), $this->payload($business, ['order_step' => '1.5', 'min_order_qty' => '3']))
            ->assertSessionHasErrors('order_step');

        $this->post(route('business.products.store'), $this->payload($business, ['list_price' => '-1']))
            ->assertSessionHasErrors('list_price');

        $this->post(route('business.products.store'), $this->payload($business, ['list_price' => 'abc']))
            ->assertSessionHasErrors('list_price');
    }

    public function test_price_change_is_recorded_in_product_history(): void
    {
        $business = $this->makeBusiness();
        $product = $this->makeProduct($business, ['list_price' => '10.0000']);

        $this->actingAs($business->user, 'business')
            ->put(route('business.products.update', $product), $this->payload($business, ['sku' => $product->sku, 'barcode' => null, 'list_price' => '12.00']))
            ->assertSessionHasNoErrors()->assertRedirect();

        $log = AuditLog::where('action', 'product.updated')->where('entity_id', $product->id)->latest('id')->firstOrFail();
        $this->assertSame('10.0000', $log->old_values['list_price']);
        $this->assertSame('12.0000', $log->new_values['list_price']);

        $this->get(route('business.products.show', $product))->assertOk()->assertSee('Liste fiyatı');
    }

    public function test_bulk_price_update_by_percent_never_goes_below_zero(): void
    {
        $business = $this->makeBusiness();
        $a = $this->makeProduct($business, ['list_price' => '10.0000']);
        $b = $this->makeProduct($business, ['list_price' => '3.0000']);
        $this->actingAs($business->user, 'business');

        $this->post(route('business.products.price.update'), ['field' => 'list_price', 'mode' => 'percent', 'value' => '10', 'confirm' => '1'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('11.0000', (string) $a->fresh()->list_price);
        $this->assertSame('3.3000', (string) $b->fresh()->list_price);

        $this->post(route('business.products.price.update'), ['field' => 'list_price', 'mode' => 'amount', 'value' => '-5', 'confirm' => '1']);
        $this->assertSame('6.0000', (string) $a->fresh()->list_price);
        $this->assertSame('0.0000', (string) $b->fresh()->list_price);

        $this->post(route('business.products.price.update'), ['field' => 'list_price', 'mode' => 'percent', 'value' => '10'])
            ->assertSessionHasErrors('confirm');
    }

    public function test_bulk_update_only_touches_own_products(): void
    {
        $a = $this->makeBusiness();
        $b = $this->makeBusiness();
        $foreign = $this->makeProduct($b, ['list_price' => '10.0000']);

        $this->actingAs($a->user, 'business')
            ->post(route('business.products.price.update'), ['field' => 'list_price', 'mode' => 'percent', 'value' => '50', 'confirm' => '1']);
        $this->actingAs($a->user, 'business')
            ->post(route('business.products.bulk'), ['action' => 'deactivate', 'ids' => [$foreign->id]]);

        $this->assertSame('10.0000', (string) $foreign->fresh()->list_price);
        $this->assertTrue($foreign->fresh()->is_active);
    }

    public function test_archived_product_is_hidden_and_cannot_be_edited(): void
    {
        $business = $this->makeBusiness();
        $product = $this->makeProduct($business, ['name' => 'Arşivlenecek Ürün']);
        $this->actingAs($business->user, 'business');

        $this->post(route('business.products.archive', $product))->assertRedirect();
        $this->assertNotNull($product->fresh()->archived_at);
        $this->assertFalse($product->fresh()->is_active);

        $this->get(route('business.products.index'))->assertDontSee('Arşivlenecek Ürün');
        $this->get(route('business.products.index', ['status' => 'archived']))->assertSee('Arşivlenecek Ürün');
        $this->put(route('business.products.update', $product), $this->payload($business, ['sku' => $product->sku]))->assertStatus(409);
    }

    public function test_duplicate_creates_inactive_copy_without_barcode(): void
    {
        $business = $this->makeBusiness();
        $product = $this->makeProduct($business, ['barcode' => '123456']);

        $this->actingAs($business->user, 'business')
            ->post(route('business.products.duplicate', $product), ['sku' => $product->sku.'-2'])
            ->assertRedirect();

        $copy = $this->inTenant($business, fn () => Product::where('sku', $product->sku.'-2')->firstOrFail());
        $this->assertNull($copy->barcode);
        $this->assertFalse($copy->is_active);
        $this->assertSame((string) $product->list_price, (string) $copy->list_price);
    }

    public function test_products_of_another_business_return_404(): void
    {
        $a = $this->makeBusiness();
        $b = $this->makeBusiness();
        $foreign = $this->makeProduct($b);
        $this->actingAs($a->user, 'business');

        $this->get(route('business.products.show', $foreign))->assertNotFound();
        $this->get(route('business.products.edit', $foreign))->assertNotFound();
        $this->put(route('business.products.update', $foreign), $this->payload($a))->assertNotFound();
        $this->post(route('business.products.archive', $foreign))->assertNotFound();
        $this->post(route('business.products.duplicate', $foreign), ['sku' => 'X'])->assertNotFound();
        $this->get(route('business.stock.create', ['product' => $foreign->id]))->assertOk()->assertDontSee($foreign->name);
        $this->getJson(route('business.stock.lookup', ['q' => $foreign->sku]))->assertExactJson([]);
    }

    public function test_image_upload_is_reencoded_and_served_only_to_owner(): void
    {
        Storage::fake('local');
        $a = $this->makeBusiness();
        $b = $this->makeBusiness();
        $product = $this->makeProduct($a);

        $this->actingAs($a->user, 'business')
            ->post(route('business.products.images.store', $product), ['images' => [UploadedFile::fake()->image('kalem.png', 800, 600)]])
            ->assertSessionHasNoErrors()->assertRedirect();

        $image = $this->inTenant($a, fn () => ProductImage::firstOrFail());
        $this->assertStringEndsWith('.webp', $image->path);
        $this->assertTrue($image->is_primary);
        Storage::disk('local')->assertExists($image->path);

        $this->get(route('business.images.show', $image))->assertOk()->assertHeader('Content-Type', 'image/webp');

        // Farklı işletmenin oturumu (aynı testte kullanıcı değiştirirken oturum sıfırlanır).
        $this->flushSession();
        $this->actingAs($b->user, 'business')->get(route('business.images.show', $image))->assertNotFound();
    }

    public function test_disguised_non_image_file_is_rejected(): void
    {
        Storage::fake('local');
        $business = $this->makeBusiness();
        $product = $this->makeProduct($business);

        $fake = UploadedFile::fake()->createWithContent('resim.png', '<?php echo "x"; ?>');

        $this->actingAs($business->user, 'business')
            ->post(route('business.products.images.store', $product), ['images' => [$fake]])
            ->assertSessionHasErrors();

        $this->assertSame(0, $this->inTenant($business, fn () => ProductImage::count()));
    }
}
