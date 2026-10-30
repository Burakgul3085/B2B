<?php

namespace Tests\Feature\Store;

use App\Domain\Store\Inventory\StoreStockService;
use App\Domain\Store\Mail\StoreMailer;
use App\Models\Store\StoreAdmin;
use App\Models\Store\StoreCategory;
use App\Models\Store\StoreCustomer;
use App\Models\Store\StoreFavorite;
use App\Models\Store\StoreOrder;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreReview;
use App\Models\Store\StoreSetting;
use App\Models\Store\StoreStockAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/** Vitrin yenilemesi — Aşama 4: favoriler, yorumlar, stoğa gelince haber ver, son bakılanlar. */
class StorefrontEngagementTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'http://magaza.localhost';

    private StoreCategory $pens;

    protected function setUp(): void
    {
        parent::setUp();
        config(['store.bot_protection' => false]);
        StoreSetting::put(['is_open' => '1']);
        $this->pens = StoreCategory::create(['name' => 'Kalemler', 'slug' => 'kalemler']);
    }

    private function product(string $name, int $stock = 10): StoreProduct
    {
        $product = StoreProduct::create(['name' => $name, 'slug' => Str::slug($name), 'category_id' => $this->pens->id, 'is_published' => true]);
        $variant = $product->variants()->create(['sku' => strtoupper(Str::slug($name)), 'price' => '20.00', 'vat_rate' => '20']);
        if ($stock) {
            app(StoreStockService::class)->receive($variant, $stock);
        }

        return $product;
    }

    private function customer(string $email = 'ayse@ornek.com'): StoreCustomer
    {
        return StoreCustomer::create(['first_name' => 'Ayşe', 'last_name' => 'kaya', 'email' => $email, 'phone' => '05321234567', 'password' => self::PASSWORD]);
    }

    private function deliveredOrder(StoreCustomer $customer, StoreProduct $product, string $status = 'delivered'): void
    {
        $order = StoreOrder::forceCreate([
            'order_no' => 'YB'.random_int(100000, 999999), 'access_token' => Str::random(40), 'customer_id' => $customer->id,
            'email' => $customer->email, 'phone' => $customer->phone, 'status' => $status, 'payment_method' => 'card', 'payment_status' => 'paid',
            'delivery_method' => 'cargo', 'shipping_name' => 'Ayşe Kaya', 'shipping_phone' => $customer->phone, 'shipping_province_id' => 26,
            'shipping_district_id' => 1, 'shipping_province' => 'Eskişehir', 'shipping_district' => 'Tepebaşı', 'shipping_address' => 'Adres',
            'billing_type' => 'individual', 'billing_name' => 'Ayşe Kaya', 'billing_address' => 'Adres', 'subtotal' => '20.00', 'total' => '20.00', 'vat_total' => '3.33',
        ]);
        $order->items()->forceCreate(['product_id' => $product->id, 'product_name' => $product->name, 'sku' => 'X', 'unit_price' => '20.00', 'vat_rate' => '20',
            'quantity' => 1, 'line_total' => '20.00', 'vat_amount' => '3.33']);
    }

    public function test_favorites_are_for_members_and_toggle(): void
    {
        $pen = $this->product('Jel Kalem');

        $this->postJson(self::HOST.'/favoriler/'.$pen->id)->assertUnauthorized();
        $this->post(self::HOST.'/favoriler/'.$pen->id)->assertRedirect(route('store.login'));

        $customer = $this->customer();
        $this->actingAs($customer, 'customer');
        $this->postJson(self::HOST.'/favoriler/'.$pen->id)->assertOk()->assertJson(['favorite' => true]);
        $this->assertTrue(StoreFavorite::where('customer_id', $customer->id)->where('product_id', $pen->id)->exists());

        $this->get(self::HOST.'/hesabim/favoriler')->assertOk()->assertSee('Jel Kalem')->assertSee('1 ürün');
        $this->get(self::HOST.'/urunler')->assertSee('aria-pressed', false);

        $this->postJson(self::HOST.'/favoriler/'.$pen->id)->assertOk()->assertJson(['favorite' => false]);
        $this->get(self::HOST.'/hesabim/favoriler')->assertSee('Favori listeniz boş');
        $this->postJson(self::HOST.'/favoriler/999999')->assertNotFound();
    }

    public function test_only_buyers_can_review_and_reviews_need_approval(): void
    {
        $pen = $this->product('Jel Kalem');
        $buyer = $this->customer();
        $other = $this->customer('ali@ornek.com');
        $this->deliveredOrder($buyer, $pen);
        $this->deliveredOrder($other, $pen, 'shipped');   // henüz teslim edilmedi

        $this->get(self::HOST.'/urun/jel-kalem')->assertSee('Yorum yazmak için')->assertSee('Henüz yorum yok');

        $this->actingAs($other, 'customer');
        $this->get(self::HOST.'/urun/jel-kalem')->assertSee('Yalnızca bu ürünü satın alıp teslim alan');
        $this->post(self::HOST.'/urun/jel-kalem/yorum', ['rating' => 5, 'body' => 'Çok güzel bir kalem.'])->assertSessionHasErrors('body');

        $this->flushSession();   // oturum başka üyeye geçerken (auth.session parolayı eşler)
        $this->actingAs($buyer, 'customer');
        $this->get(self::HOST.'/urun/jel-kalem')->assertSee('Yorum yazın')->assertSee('Ayşe K.');
        $this->post(self::HOST.'/urun/jel-kalem/yorum', ['rating' => 9, 'body' => 'Çok güzel bir kalem.'])->assertSessionHasErrors('rating');
        $this->post(self::HOST.'/urun/jel-kalem/yorum', ['rating' => 4, 'title' => 'Akıcı', 'body' => 'Çok güzel yazıyor, tavsiye ederim.'])
            ->assertRedirect(route('store.product', 'jel-kalem').'#yorumlar');
        $this->post(self::HOST.'/urun/jel-kalem/yorum', ['rating' => 5, 'body' => 'İkinci yorum denemesi.'])->assertSessionHasErrors('body');

        $review = StoreReview::sole();
        $this->assertSame(['pending', 'Ayşe K.', 4], [$review->status, $review->author_name, $review->rating]);
        $this->get(self::HOST.'/urun/jel-kalem')->assertDontSee('Çok güzel yazıyor')->assertDontSee('AggregateRating');

        // Yönetici onaylar ve yanıt yazar
        $this->actingAs(StoreAdmin::create(['name' => 'V', 'email' => 'v@magaza.local', 'password' => self::PASSWORD]), 'store_admin');
        $this->get(route('store_admin.reviews.index'))->assertOk()->assertSee('Çok güzel yazıyor');
        $this->put(route('store_admin.reviews.update', $review), ['status' => 'yayinda'])->assertSessionHasErrors('status');
        $this->put(route('store_admin.reviews.update', $review), ['status' => 'approved', 'reply' => 'Teşekkür ederiz!'])->assertSessionHasNoErrors();

        $this->get(self::HOST.'/urun/jel-kalem')->assertSee('Çok güzel yazıyor')->assertSee('Teşekkür ederiz!')
            ->assertSee('"AggregateRating"', false)->assertSee('4,0 (1 yorum)');
        $this->get(self::HOST.'/urunler')->assertSee('5 üzerinden 4,0 puan');

        $this->put(route('store_admin.reviews.update', $review), ['status' => 'rejected'])->assertSessionHasNoErrors();
        $this->get(self::HOST.'/urun/jel-kalem')->assertDontSee('Çok güzel yazıyor');
        $this->delete(route('store_admin.reviews.destroy', $review))->assertSessionHasNoErrors();
        $this->assertSame(0, StoreReview::count());
    }

    public function test_account_lists_products_to_review_and_photo_reviews_show_after_approval(): void
    {
        Storage::fake('local');
        $pen = $this->product('Jel Kalem');
        $book = $this->product('Defter');
        $buyer = $this->customer();
        $this->deliveredOrder($buyer, $pen);
        $this->deliveredOrder($buyer, $book, 'shipped'); // teslim edilmedi: listede yok

        $this->actingAs($buyer, 'customer');
        $this->get(self::HOST.'/hesabim')->assertSee('1 ürün değerlendirmenizi bekliyor');
        $this->get(self::HOST.'/hesabim/degerlendirmeler')->assertOk()->assertSee('Değerlendirmelerim')->assertSee('Jel Kalem')->assertDontSee('Defter')
            ->assertSee('Henüz yorum yazmadınız');

        // Hesaptan fotoğraflı yorum; en fazla 5 fotoğraf, yalnızca resim.
        $form = ['from' => 'account', 'product_form' => $pen->id, 'rating' => 5, 'body' => 'Harika bir kalem, çok akıcı.'];
        $this->post(self::HOST.'/urun/jel-kalem/yorum', $form + ['photos' => array_fill(0, 6, UploadedFile::fake()->image('a.jpg'))])->assertSessionHasErrors('photos');
        $this->post(self::HOST.'/urun/jel-kalem/yorum', $form + ['photos' => [UploadedFile::fake()->create('a.pdf', 5, 'application/pdf')]])->assertSessionHasErrors('photos.0');
        $this->post(self::HOST.'/urun/jel-kalem/yorum', $form + ['photos' => [UploadedFile::fake()->image('a.jpg', 1200, 900), UploadedFile::fake()->image('b.png', 300, 300)]])
            ->assertRedirect(route('store.reviews'));
        $review = StoreReview::sole();
        $this->assertSame(2, $review->photos()->count());
        $photo = $review->photos()->firstOrFail();
        Storage::disk('local')->assertExists($photo->path);

        // Onay bekleyen yorumun fotoğrafı: yalnızca sahibine; yorum hesapta "Onay bekliyor".
        $this->get(self::HOST.'/hesabim/degerlendirmeler')->assertSee('Değerlendirilecek ürün yok')->assertSee('Onay bekliyor')->assertSee('yorum-fotografi/'.$photo->id);
        $this->get(self::HOST.'/yorum-fotografi/'.$photo->id)->assertOk();
        $this->post(self::HOST.'/cikis');
        $this->flushSession();
        auth('customer')->logout();
        $this->get(self::HOST.'/yorum-fotografi/'.$photo->id)->assertNotFound();

        // Yayımlanınca ürün sayfasında fotoğraflarıyla, herkese.
        $this->actingAs(StoreAdmin::create(['name' => 'V', 'email' => 'v@magaza.local', 'password' => self::PASSWORD]), 'store_admin');
        $this->get(route('store_admin.reviews.index'))->assertSee(route('store_admin.reviews.photo', [$review, $photo->id]));
        $this->get(route('store_admin.reviews.photo', [$review, $photo->id]))->assertOk();
        $this->put(route('store_admin.reviews.update', $review), ['status' => 'approved'])->assertSessionHasNoErrors();
        $this->get(self::HOST.'/urun/jel-kalem')->assertSee('Harika bir kalem')->assertSee('yorum-fotografi/'.$photo->id);
        $this->get(self::HOST.'/yorum-fotografi/'.$photo->id)->assertOk()->assertHeader('Cache-Control', 'max-age=86400, public');

        // Silinen yorumun fotoğrafları da silinir.
        $this->delete(route('store_admin.reviews.destroy', $review));
        Storage::disk('local')->assertMissing($photo->path);
    }

    public function test_stock_alert_is_sent_once_when_stock_arrives(): void
    {
        $pen = $this->product('Jel Kalem', 0);
        $inStock = $this->product('Kurşun Kalem', 5);
        $variant = $pen->variants()->sole();

        $this->get(self::HOST.'/urun/jel-kalem')->assertSee('Stoğa gelince haber verelim');
        $this->post(self::HOST.'/stok-bildirimi', ['variant_id' => $variant->id, 'email' => 'yanlis'])->assertSessionHasErrors('email');
        $this->post(self::HOST.'/stok-bildirimi', ['variant_id' => $inStock->variants()->value('id'), 'email' => 'ali@ornek.com'])->assertSessionHasErrors('email');
        $this->post(self::HOST.'/stok-bildirimi', ['variant_id' => $variant->id, 'email' => 'Ali@Ornek.com'])->assertRedirect(route('store.product', 'jel-kalem').'#stok-bildirimi');
        $this->post(self::HOST.'/stok-bildirimi', ['variant_id' => $variant->id, 'email' => 'ali@ornek.com']);
        $this->assertSame(1, StoreStockAlert::where('email', 'ali@ornek.com')->count());

        $mailer = Mockery::mock(StoreMailer::class);
        $mailer->shouldReceive('send')->once()->withArgs(fn ($to, $subject) => $to === 'ali@ornek.com' && str_contains($subject, 'Jel Kalem'))->andReturnTrue();
        $this->app->instance(StoreMailer::class, $mailer);

        $this->artisan('store:stock-alerts')->expectsOutputToContain('0 stok bildirimi')->assertSuccessful();
        app(StoreStockService::class)->receive($variant->fresh(), 3);
        $this->artisan('store:stock-alerts')->expectsOutputToContain('1 stok bildirimi')->assertSuccessful();
        $this->assertSame(0, StoreStockAlert::count());
    }

    public function test_recently_viewed_products_follow_the_visitor(): void
    {
        $this->product('Jel Kalem');
        $this->product('Kurşun Kalem');

        $this->get(self::HOST.'/')->assertDontSee('Kaldığınız yerden devam edin');
        $this->get(self::HOST.'/urun/jel-kalem')->assertDontSee('Son baktığınız ürünler');
        $this->get(self::HOST.'/urun/kursun-kalem')->assertSee('Son baktığınız ürünler')->assertSee('Jel Kalem');
        $this->get(self::HOST.'/')->assertSee('Kaldığınız yerden devam edin');
    }

    public function test_closing_the_account_removes_favorites_reviews_and_alerts(): void
    {
        $pen = $this->product('Jel Kalem', 0);
        $customer = $this->customer();
        StoreFavorite::create(['customer_id' => $customer->id, 'product_id' => $pen->id]);
        StoreReview::create(['product_id' => $pen->id, 'customer_id' => $customer->id, 'author_name' => 'Ayşe K.', 'rating' => 5, 'body' => 'Güzel kalem.']);
        StoreStockAlert::create(['variant_id' => $pen->variants()->value('id'), 'email' => 'ayse@ornek.com']);

        $this->actingAs($customer, 'customer');
        $this->delete(self::HOST.'/hesabim', ['close_password' => self::PASSWORD]);
        $this->assertSame([0, 0, 0], [StoreFavorite::count(), StoreReview::count(), StoreStockAlert::count()]);
    }
}
