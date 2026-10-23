<?php

namespace Tests\Feature\Store;

use App\Models\Store\StoreAdmin;
use App\Models\Store\StoreBlogCategory;
use App\Models\Store\StoreBlogPost;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Vitrin yenilemesi — Aşama 5: yönetimden yazılan blog. */
class StoreBlogTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'http://magaza.localhost';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        StoreSetting::put(['is_open' => '1']);
    }

    private function admin(): void
    {
        $this->actingAs(StoreAdmin::create(['name' => 'V', 'email' => 'v@magaza.local', 'password' => self::PASSWORD]), 'store_admin');
    }

    private function blogPost(array $attrs = []): StoreBlogPost
    {
        return StoreBlogPost::create($attrs + ['title' => 'Okula dönüş listesi', 'slug' => 'okula-donus-listesi', 'body' => "Giriş paragrafı.\n\n## Defterler\n\n- Kareli defter",
            'is_published' => true, 'published_at' => now()->subHour()]);
    }

    public function test_admin_writes_posts_and_manages_categories(): void
    {
        $this->admin();
        $product = StoreProduct::create(['name' => 'Kareli Defter', 'slug' => 'kareli-defter', 'is_published' => true]);
        $product->variants()->create(['sku' => 'DEF-1', 'price' => '30.00', 'vat_rate' => '20']);

        $this->put(route('store_admin.blog.categories'), ['categories' => [['name' => 'Okul'], ['name' => ''], ['name' => 'Sanat']]])->assertSessionHasNoErrors();
        $this->assertSame(['Okul', 'Sanat'], StoreBlogCategory::orderBy('sort')->pluck('name')->all());
        $okul = StoreBlogCategory::firstWhere('name', 'Okul');

        $this->get(route('store_admin.blog.create'))->assertOk()->assertSee('Yazıda geçen ürünler');
        $this->post(route('store_admin.blog.store'), ['title' => '', 'body' => ''])->assertSessionHasErrors(['title', 'body']);
        $this->post(route('store_admin.blog.store'), ['title' => 'Okula Dönüş', 'body' => 'Metin', 'product_skus' => 'YOK-1'])->assertSessionHasErrors('product_skus');

        $file = tempnam(sys_get_temp_dir(), 'kap');
        imagepng(imagecreatetruecolor(1600, 1000), $file);
        $this->post(route('store_admin.blog.store'), ['title' => 'Okula Dönüş Rehberi', 'body' => "Yeni dönem **başlıyor**.\n\n<script>alert(1)</script>",
            'category_id' => $okul->id, 'product_skus' => 'def-1', 'is_published' => '1',
            'cover' => new UploadedFile($file, 'k.png', 'image/png', null, true)])->assertSessionHasNoErrors();

        $post = StoreBlogPost::sole();
        $this->assertSame(['okula-donus-rehberi', true, $okul->id], [$post->slug, $post->isLive(), $post->category_id]);
        Storage::disk('local')->assertExists($post->cover_path);
        $this->get(route('store_admin.blog.cover', $post))->assertOk();
        $this->get(route('store_admin.blog.index'))->assertSee('Okula Dönüş Rehberi')->assertSee('Yayında');

        $this->get(self::HOST.'/blog/okula-donus-rehberi')->assertOk()
            ->assertSee('<strong>başlıyor</strong>', false)->assertDontSee('<script>alert', false)
            ->assertSee('Kareli Defter')->assertSee('"BlogPosting"', false)
            ->assertSee('/gorsel/blog/'.$post->id, false);
        $this->get(self::HOST.'/gorsel/blog/'.$post->id)->assertOk();

        // Kategori silinince yazı kategorisiz kalır; yazı silinince kapak da silinir.
        $this->put(route('store_admin.blog.categories'), ['categories' => [['id' => $okul->id, 'name' => '']]])->assertSessionHasNoErrors();
        $this->assertNull($post->fresh()->category_id);
        $this->delete(route('store_admin.blog.destroy', $post))->assertRedirect(route('store_admin.blog.index'));
        Storage::disk('local')->assertMissing($post->cover_path);
    }

    public function test_only_live_posts_are_visible(): void
    {
        $category = StoreBlogCategory::create(['name' => 'Okul', 'slug' => 'okul']);
        $this->blogPost(['category_id' => $category->id]);
        $this->blogPost(['title' => 'Taslak yazı', 'slug' => 'taslak', 'is_published' => false]);
        $this->blogPost(['title' => 'Gelecek yazı', 'slug' => 'gelecek', 'published_at' => now()->addDay()]);

        $this->get(self::HOST.'/blog')->assertOk()->assertSee('Okula dönüş listesi')->assertDontSee('Taslak yazı')->assertDontSee('Gelecek yazı')
            ->assertSee('dk okuma');
        $this->get(self::HOST.'/blog/kategori/okul')->assertOk()->assertSee('Okula dönüş listesi');
        $this->get(self::HOST.'/blog/taslak')->assertNotFound();
        $this->get(self::HOST.'/blog/gelecek')->assertNotFound();
        $this->get(self::HOST.'/blog/okula-donus-listesi')->assertOk()->assertSee('<h2>Defterler</h2>', false);

        // Ana sayfa bloğu, menü bağlantısı ve site haritası
        $this->get(self::HOST.'/')->assertSee('Blogdan')->assertSee(route('store.blog'), false);
        $this->get(self::HOST.'/sitemap.xml')->assertSee('/blog/okula-donus-listesi')->assertDontSee('/blog/taslak');
    }

    public function test_blog_link_is_hidden_without_posts(): void
    {
        $this->get(self::HOST.'/')->assertDontSee(route('store.blog'), false);
        $this->get(self::HOST.'/blog')->assertOk()->assertSee('Henüz yazı yok');
    }
}
