<?php

namespace Tests\Feature\Catalog;

use App\Models\BusinessSetting;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrders;
use Tests\TestCase;

/** Bayi "Yeni Ürünler": süre ayarı, başlangıç tarihi, rozet sayısı ve yeni ürün yokken öneriler. */
class NewProductsTest extends TestCase
{
    use CreatesOrders, RefreshDatabase;

    public function test_new_products_page_lists_only_recent_orderable_products_and_menu_shows_count(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $this->makeProduct($business, ['name' => 'Yeni Defter']);
        $this->makeProduct($business, ['name' => 'Yeni Kalem']);
        $this->aged($this->makeProduct($business, ['name' => 'Eski Silgi']), 40);
        $this->makeProduct($business, ['name' => 'Gizli Yeni', 'visible_to_dealers' => false]);
        $this->makeProduct($this->makeBusiness(), ['name' => 'Başka Toptancının Yenisi']);

        $page = $this->actingAs($rel->dealer->user, 'dealer')->get(route('dealer.catalog', ['yeni' => 1]))->assertOk();

        $page->assertSee('Yeni Defter')->assertSee('Yeni Kalem')->assertSee('Son 30 günde eklenenler')
            ->assertDontSee('Eski Silgi')->assertDontSee('Gizli Yeni')->assertDontSee('Başka Toptancının Yenisi');
        $this->assertSame(2, $page->viewData('products')->total());
        $page->assertSeeInOrder(['Yeni Ürünler', '>2</span>'], false);

        // Tüm ürünlerde eski ürün de var; yeni olanlarda rozet görünür.
        $this->get(route('dealer.catalog'))->assertOk()->assertSee('Eski Silgi')->assertSee('>Yeni</span>', false);
    }

    public function test_day_window_and_cutoff_date_settings_are_respected(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $this->aged($this->makeProduct($business, ['name' => 'On Günlük']), 10);
        $this->aged($this->makeProduct($business, ['name' => 'Üç Günlük']), 3);
        $this->makeProduct($business, ['name' => 'Bugünkü']);

        $this->actingAs($business->user, 'business')->put(route('business.settings.update'), [
            'display_name' => $business->display_name, 'stock_display' => 'level', 'ledger_post_on' => 'approval',
            'default_payment_term_days' => 30, 'price_display' => 'excl_vat', 'new_product_days' => 7,
        ])->assertSessionHasNoErrors();

        $this->flushSession();
        $this->actingAs($rel->dealer->user, 'dealer');
        $this->get(route('dealer.catalog', ['yeni' => 1]))->assertSee('Üç Günlük')->assertSee('Bugünkü')->assertDontSee('On Günlük');

        // "Bu tarihten önce eklenenleri yeni sayma": ilk toplu yükleme sayılmaz.
        $this->inTenant($business, fn () => BusinessSetting::query()->update(['new_products_since' => now()->subDay()->toDateString()]));
        $this->get(route('dealer.catalog', ['yeni' => 1]))->assertSee('Bugünkü')->assertDontSee('Üç Günlük');
    }

    public function test_when_nothing_is_new_latest_products_are_suggested(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        $this->aged($this->makeProduct($business, ['name' => 'Eski Ama Güzel']), 60);

        $this->actingAs($rel->dealer->user, 'dealer')->get(route('dealer.catalog', ['yeni' => 1]))->assertOk()
            ->assertSee('Son 30 günde yeni ürün eklenmedi')->assertSee('En son eklenen ürünler')->assertSee('Eski Ama Güzel');
    }

    private function aged(Product $product, int $days): Product
    {
        $product->forceFill(['created_at' => now()->subDays($days)])->saveQuietly();

        return $product;
    }
}
