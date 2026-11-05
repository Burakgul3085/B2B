<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOrders;
use Tests\TestCase;

/** Sınırsız seviyeli kategori ağacı (docs/03-is-kurallari.md §2.5): yönetim, döngü ve güvenlik sınırı, filtrelerin tüm alt seviyeleri kapsaması. */
class CategoryTreeTest extends TestCase
{
    use CreatesOrders, RefreshDatabase;

    public function test_categories_can_be_nested_to_any_level_without_cycles(): void
    {
        $business = $this->makeBusiness();
        $this->actingAs($business->user, 'business');

        $parent = null;
        foreach (['Okul', 'Defterler', 'Spiralli', 'A4'] as $name) {
            $this->post(route('business.categories.store'), ['name' => $name, 'parent_id' => $parent?->id])->assertSessionHasNoErrors();
            $parent = $this->inTenant($business, fn () => Category::where('name', $name)->firstOrFail());
        }
        [$root, $a4] = $this->inTenant($business, fn () => [Category::where('name', 'Okul')->firstOrFail(), Category::where('name', 'A4')->firstOrFail()]);
        $this->assertSame('Okul › Defterler › Spiralli › A4', $this->inTenant($business, fn () => $a4->fresh()->fullName()));

        // Ağaç her seviyeyi gösterir; düzenleme ve alt kategori ekleme formu açılır.
        $this->get(route('business.categories.index'))->assertOk()->assertSeeInOrder(['Okul', 'Defterler', 'Spiralli', 'A4']);
        $this->get(route('business.categories.index', ['duzenle' => $a4->id]))->assertOk()->assertSee('Düzenle: A4')->assertSee('Okul › Defterler › Spiralli');
        $this->get(route('business.categories.index', ['ust' => $a4->id]))->assertOk()->assertSee('Yeni kategori');

        // Döngü: kategori kendi alt kategorisinin altına taşınamaz.
        $this->put(route('business.categories.update', $root), ['name' => 'Okul', 'parent_id' => $a4->id, 'is_active' => '1'])->assertSessionHasErrors('parent_id');
        $this->put(route('business.categories.update', $root), ['name' => 'Okul', 'parent_id' => $root->id, 'is_active' => '1'])->assertSessionHasErrors('parent_id');

        // Alt ağaç başka bir kategorinin altına taşınabilir (eskiden alt kategorisi olan taşınamazdı).
        $this->post(route('business.categories.store'), ['name' => 'Kırtasiye'])->assertSessionHasNoErrors();
        $top = $this->inTenant($business, fn () => Category::where('name', 'Kırtasiye')->firstOrFail());
        $this->put(route('business.categories.update', $root), ['name' => 'Okul', 'parent_id' => $top->id, 'is_active' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('Kırtasiye › Okul › Defterler › Spiralli › A4', $this->inTenant($business, fn () => $a4->fresh()->fullName()));

        // Güvenlik sınırı: ağaç MAX_DEPTH seviyeyi aşamaz.
        $parent = $a4;
        for ($level = 6; $level <= Category::MAX_DEPTH; $level++) {
            $this->post(route('business.categories.store'), ['name' => "Seviye {$level}", 'parent_id' => $parent->id])->assertSessionHasNoErrors();
            $parent = $this->inTenant($business, fn () => Category::where('name', "Seviye {$level}")->firstOrFail());
        }
        $this->post(route('business.categories.store'), ['name' => 'Fazla', 'parent_id' => $parent->id])->assertSessionHasErrors('parent_id');

        // Alt kategorisi olan silinemez; yaprak silinir.
        $this->delete(route('business.categories.destroy', $a4))->assertSessionHasErrors('category');
        $this->delete(route('business.categories.destroy', $parent))->assertRedirect(route('business.categories.index'));
    }

    public function test_another_business_category_cannot_be_used_as_parent(): void
    {
        $a = $this->makeBusiness();
        $foreign = $this->inTenant($this->makeBusiness(), fn () => Category::create(['name' => 'Yabancı']));
        $this->actingAs($a->user, 'business');

        $this->post(route('business.categories.store'), ['name' => 'Benim', 'parent_id' => $foreign->id])->assertSessionHasErrors('parent_id');
        $this->assertSame(0, $this->inTenant($a, fn () => Category::count()));
    }

    public function test_filters_include_every_sub_level(): void
    {
        $business = $this->makeBusiness();
        $rel = $this->orderingDealer($business);
        [$root, $leaf] = $this->inTenant($business, function () {
            $root = Category::create(['name' => 'Okul']);
            $mid = Category::create(['name' => 'Defterler', 'parent_id' => $root->id]);
            $leaf = Category::create(['name' => 'Spiralli', 'parent_id' => Category::create(['name' => 'A4', 'parent_id' => $mid->id])->id]);

            return [$root, $leaf];
        });
        $this->makeProduct($business, ['name' => 'Derin Defter', 'category_id' => $leaf->id]);
        $this->makeProduct($business, ['name' => 'Başka Ürün']);

        // Toptancı ürün listesi: ana kategori seçilince en alttaki ürün de gelir; kategori yolu görünür.
        $this->actingAs($business->user, 'business')->get(route('business.products.index', ['category' => $root->id]))
            ->assertOk()->assertSee('Derin Defter')->assertDontSee('Başka Ürün')->assertSee('Okul › Defterler › A4 › Spiralli');
        $this->get(route('business.stock.index', ['category' => $root->id]))->assertOk()->assertSee('Derin Defter')->assertDontSee('Başka Ürün');

        // Bayi kataloğu: filtre ağacı her seviyeyi gösterir, sayılar alt seviyelerle toplanır.
        $this->flushSession();
        $page = $this->actingAs($rel->dealer->user, 'dealer')->get(route('dealer.catalog', ['category' => [$root->id]]))->assertOk();
        $page->assertSee('Derin Defter')->assertDontSee('Başka Ürün')->assertSeeInOrder(['Okul', 'Defterler', 'A4', 'Spiralli']);
        $this->assertSame(1, $page->viewData('categories')->first()->product_count);
    }
}
