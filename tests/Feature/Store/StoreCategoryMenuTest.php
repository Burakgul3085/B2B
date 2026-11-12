<?php

namespace Tests\Feature\Store;

use App\Domain\Store\Storefront\Catalog;
use App\Models\Store\StoreCategory;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Tüm kategoriler menüsü: çok sayıda kategori, 3. seviye sınırı, mobil ekranlar, menü önbelleği ve boş kategorilerin gizlenmesi. */
class StoreCategoryMenuTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'http://magaza.localhost';

    protected function setUp(): void
    {
        parent::setUp();
        StoreSetting::put(['is_open' => '1']);
    }

    /** Kategoriye vitrinde görünen bir ürün ekler (yayında, etkin seçenekli). */
    private function fill(StoreCategory $category, bool $published = true): StoreProduct
    {
        $product = StoreProduct::create(['name' => 'Ürün '.$category->slug.' '.uniqid(), 'slug' => 'urun-'.$category->slug.'-'.uniqid(), 'category_id' => $category->id, 'is_published' => $published]);
        $product->variants()->create(['sku' => 'SKU-'.uniqid(), 'price' => '10.00', 'vat_rate' => '20']);

        return $product;
    }

    public function test_menu_handles_many_categories_and_deep_levels(): void
    {
        for ($i = 1; $i <= 100; $i++) {
            StoreCategory::create(['name' => "Kategori {$i}", 'slug' => "kategori-{$i}", 'sort' => $i]);
        }
        $pens = StoreCategory::firstWhere('slug', 'kategori-1');
        $gel = StoreCategory::create(['name' => 'Jel Kalemler', 'slug' => 'jel-kalemler', 'parent_id' => $pens->id]);
        for ($i = 1; $i <= 9; $i++) {
            $this->fill(StoreCategory::create(['name' => "Jel Renk {$i}", 'slug' => "jel-renk-{$i}", 'parent_id' => $gel->id, 'sort' => $i]));
        }
        StoreCategory::where('slug', '!=', 'kategori-1')->whereNull('parent_id')->get()->each(fn ($c) => $this->fill($c));

        // Sayfaya yalnızca menünün adresi gömülür; yüzlerce kategori her sayfada taşınmaz.
        $version = app(Catalog::class)->menuVersion();
        $this->get(self::HOST.'/')->assertOk()
            ->assertSee(e(json_encode(route('store.category-menu', ['part' => 'masaustu', 'v' => $version]), JSON_UNESCAPED_SLASHES)), false)
            ->assertDontSee('Kategori 100');

        $this->get(self::HOST.'/menu/kategoriler/masaustu?v='.$version)->assertOk()
            ->assertHeader('Cache-Control', 'max-age=86400, public')
            ->assertSee('Kategori 100')
            ->assertSee('Kategori ara…')
            ->assertSee('+3 daha')
            ->assertSee('href="'.route('store.category', 'jel-renk-6').'"', false)
            ->assertDontSee('href="'.route('store.category', 'jel-renk-7').'"', false)
            // Arama alt kategori adlarıyla da eşleşir.
            ->assertSee('Kategori 1 Jel Kalemler Jel Renk 1', false);

        // Mobil: ana kategori › alt kategori › 3. seviye ekranları; 3. seviyenin tamamı listelenir.
        $this->get(self::HOST.'/menu/kategoriler/mobil?v=eski')->assertOk()
            ->assertHeader('Cache-Control', 'no-cache, private')
            ->assertSee("isScreen('r{$pens->id}')", false)
            ->assertSee("isScreen('c{$gel->id}')", false)
            ->assertSee('href="'.route('store.category', 'jel-renk-9').'"', false);

        $this->get(self::HOST.'/menu/kategoriler/baska')->assertNotFound();
    }

    public function test_menu_cache_follows_category_changes(): void
    {
        $pens = StoreCategory::create(['name' => 'Kalemler', 'slug' => 'kalemler']);
        $this->fill($pens);
        $this->assertSame(['Kalemler'], app(Catalog::class)->menu()->pluck('name')->all());

        $pens->update(['name' => 'Kalem Çeşitleri']);
        $this->assertSame(['Kalem Çeşitleri'], app(Catalog::class)->menu()->pluck('name')->all());

        // Model olayı tetiklemeyen toplu değişiklikler de menüye yansır.
        StoreCategory::where('id', $pens->id)->update(['is_active' => false]);
        $this->assertSame([], app(Catalog::class)->menu()->all());

        DB::table('store_categories')->insert(['name' => 'Defterler', 'slug' => 'defterler', 'sort' => 0, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->fill(StoreCategory::firstWhere('slug', 'defterler'));
        $this->assertSame(['Defterler'], app(Catalog::class)->menu()->pluck('name')->all());

        StoreProduct::query()->update(['category_id' => null]);
        $this->assertSame([], app(Catalog::class)->menu()->all());
    }

    public function test_empty_categories_are_hidden_everywhere_until_a_product_is_added(): void
    {
        $empty = StoreCategory::create(['name' => 'Boş Raf', 'slug' => 'bos-raf', 'sort' => 0]);
        $parent = StoreCategory::create(['name' => 'Kalemler', 'slug' => 'kalemler', 'sort' => 1]);
        $child = StoreCategory::create(['name' => 'Jel Kalemler', 'slug' => 'jel-kalemler', 'parent_id' => $parent->id]);
        $emptyChild = StoreCategory::create(['name' => 'Dolma Kalemler', 'slug' => 'dolma-kalemler', 'parent_id' => $parent->id]);
        $hidden = $this->fill($empty, published: false); // yayında olmayan ürün kategoriyi görünür yapmaz
        $this->fill($child); // ürün yalnızca alt kategoride: ana kategori de görünür

        $catalog = app(Catalog::class);
        $this->assertSame(['Kalemler'], $catalog->menu()->pluck('name')->all());
        $this->assertSame(['Jel Kalemler'], $catalog->menu()->first()->children->pluck('name')->all());

        $version = app(Catalog::class)->menuVersion();
        $this->get(self::HOST.'/menu/kategoriler/masaustu?v='.$version)->assertOk()->assertSee('Jel Kalemler')->assertDontSee('Boş Raf')->assertDontSee('Dolma Kalemler');
        $this->get(self::HOST.'/menu/kategoriler/mobil?v='.$version)->assertOk()->assertDontSee('Boş Raf')->assertDontSee('Dolma Kalemler');
        $this->get(self::HOST.'/')->assertOk()->assertSee('Kalemler')->assertDontSee('Boş Raf');
        $this->get(route('store.category', 'kalemler'))->assertOk()->assertSee('Jel Kalemler')->assertDontSee('Dolma Kalemler');
        $this->getJson(route('store.search.suggest', ['q' => 'kalem']))->assertOk()->assertJsonCount(2, 'categories')->assertJsonMissing(['name' => 'Dolma Kalemler']);
        $this->get(self::HOST.'/sitemap.xml')->assertOk()->assertSee(route('store.category', 'kalemler'))->assertDontSee(route('store.category', 'bos-raf'))->assertDontSee(route('store.category', 'dolma-kalemler'));

        // Tek bir ürün yayına alınınca kategori her yerde görünür; menü adresi (önbellek) değişir.
        $hidden->update(['is_published' => true]);
        $this->assertNotSame($version, app(Catalog::class)->menuVersion());
        $this->assertSame(['Boş Raf', 'Kalemler'], app(Catalog::class)->menu()->pluck('name')->all());
        $this->get(self::HOST.'/menu/kategoriler/masaustu?v='.app(Catalog::class)->menuVersion())->assertSee('Boş Raf');
        $this->get(self::HOST.'/')->assertSee('Boş Raf');

        // Son ürünü yayından kalkınca yeniden gizlenir.
        $hidden->update(['is_published' => false]);
        $this->assertSame(['Kalemler'], app(Catalog::class)->menu()->pluck('name')->all());
    }
}
