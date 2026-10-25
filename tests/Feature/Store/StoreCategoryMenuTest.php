<?php

namespace Tests\Feature\Store;

use App\Domain\Store\Storefront\Catalog;
use App\Models\Store\StoreCategory;
use App\Models\Store\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Tüm kategoriler menüsü: çok sayıda kategori, 3. seviye sınırı, mobil ekranlar ve menü önbelleği. */
class StoreCategoryMenuTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'http://magaza.localhost';

    protected function setUp(): void
    {
        parent::setUp();
        StoreSetting::put(['is_open' => '1']);
    }

    public function test_menu_handles_many_categories_and_deep_levels(): void
    {
        for ($i = 1; $i <= 100; $i++) {
            StoreCategory::create(['name' => "Kategori {$i}", 'slug' => "kategori-{$i}", 'sort' => $i]);
        }
        $pens = StoreCategory::firstWhere('slug', 'kategori-1');
        $gel = StoreCategory::create(['name' => 'Jel Kalemler', 'slug' => 'jel-kalemler', 'parent_id' => $pens->id]);
        for ($i = 1; $i <= 9; $i++) {
            StoreCategory::create(['name' => "Jel Renk {$i}", 'slug' => "jel-renk-{$i}", 'parent_id' => $gel->id, 'sort' => $i]);
        }

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
        $this->assertSame(['Kalemler'], app(Catalog::class)->menu()->pluck('name')->all());

        $pens->update(['name' => 'Kalem Çeşitleri']);
        $this->assertSame(['Kalem Çeşitleri'], app(Catalog::class)->menu()->pluck('name')->all());

        // Model olayı tetiklemeyen toplu değişiklikler de menüye yansır.
        StoreCategory::where('id', $pens->id)->update(['is_active' => false]);
        $this->assertSame([], app(Catalog::class)->menu()->all());

        DB::table('store_categories')->insert(['name' => 'Defterler', 'slug' => 'defterler', 'sort' => 0, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame(['Defterler'], app(Catalog::class)->menu()->pluck('name')->all());

        $pens->delete();
        StoreCategory::firstWhere('slug', 'defterler')->delete();
        $this->assertSame([], app(Catalog::class)->menu()->all());
    }
}
