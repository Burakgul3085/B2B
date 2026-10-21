<?php

namespace Tests\Feature\Store;

use App\Domain\Store\Cart\CartService;
use App\Domain\Store\Inventory\StoreStockService;
use App\Http\Controllers\Store\Front\Concerns\CurrentCart;
use App\Models\Store\StoreCart;
use App\Models\Store\StoreCustomer;
use App\Models\Store\StoreProduct;
use App\Models\Store\StoreProductVariant;
use App\Models\Store\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Mağaza sepeti (M5): ekleme, stok sınırı, adet, çıkarma, güncel fiyat ve KDV, girişte birleştirme. */
class StoreCartTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'http://magaza.localhost';

    protected function setUp(): void
    {
        parent::setUp();
        StoreSetting::put(['is_open' => '1']);
    }

    private function variant(string $name, string $price, int $stock, string $vat = '20', bool $published = true): StoreProductVariant
    {
        $product = StoreProduct::create(['name' => $name, 'slug' => Str::slug($name), 'is_published' => $published]);
        $variant = $product->variants()->create(['sku' => Str::upper(Str::slug($name)), 'price' => $price, 'vat_rate' => $vat]);
        if ($stock) {
            app(StoreStockService::class)->receive($variant, $stock);
        }

        return $variant->fresh();
    }

    private function guestToken(): ?string
    {
        return StoreCart::whereNull('customer_id')->value('token');
    }

    public function test_guest_adds_to_cart_with_stock_limits(): void
    {
        $pen = $this->variant('Jel Kalem', '24.90', 5);

        $this->post(self::HOST.'/sepet', ['variant_id' => $pen->id, 'quantity' => 2])->assertSessionHas('cart_added')->assertCookie(CurrentCart::COOKIE);
        $token = $this->guestToken();
        $this->withCookie(CurrentCart::COOKIE, $token);

        $this->post(self::HOST.'/sepet', ['variant_id' => $pen->id, 'quantity' => 2])->assertSessionHasNoErrors();
        $this->post(self::HOST.'/sepet', ['variant_id' => $pen->id, 'quantity' => 2])
            ->assertSessionHasErrors(['cart' => 'Bu üründen stokta 5 adet var; sepetinize en fazla 1 adet daha ekleyebilirsiniz.']);
        $this->assertSame(4, (int) StoreCart::where('token', $token)->firstOrFail()->items()->sum('quantity'));

        // Ödeme aşamasında başkasına ayrılan adet satılabilir stoktan düşer.
        app(StoreStockService::class)->hold('checkout:baska', [$pen->id => 1]);
        $this->post(self::HOST.'/sepet', ['variant_id' => $pen->id, 'quantity' => 1])->assertSessionHasErrors('cart');

        $this->get(self::HOST.'/')->assertSee('Sepet (4 ürün)', false);
    }

    public function test_unsellable_products_cannot_be_added(): void
    {
        $draft = $this->variant('Taslak Ürün', '10.00', 5, published: false);
        $sold = $this->variant('Tükenen Ürün', '10.00', 0);
        $passive = $this->variant('Pasif Seçenek', '10.00', 5);
        $passive->update(['is_active' => false]);

        $this->post(self::HOST.'/sepet', ['variant_id' => $draft->id])->assertSessionHasErrors(['cart' => 'Bu ürün şu anda satışta değil.']);
        $this->post(self::HOST.'/sepet', ['variant_id' => $passive->id])->assertSessionHasErrors(['cart' => 'Bu ürün şu anda satışta değil.']);
        $this->post(self::HOST.'/sepet', ['variant_id' => $sold->id])->assertSessionHasErrors(['cart' => 'Bu ürün tükendi.']);
        $this->post(self::HOST.'/sepet', ['variant_id' => 999999])->assertSessionHasErrors('cart');
        $this->assertSame(0, StoreCart::count());
    }

    public function test_summary_uses_current_prices_and_vat_and_flags_stock_problems(): void
    {
        $pen = $this->variant('Jel Kalem', '24.90', 10);
        $book = $this->variant('Hikaye Kitabı', '100.00', 3, vat: '0');
        $carts = app(CartService::class);
        $cart = $carts->createGuest();
        $carts->add($cart, $pen->id, 3);
        $carts->add($cart, $book->id, 2);

        $summary = $carts->summary($cart);
        $this->assertSame('274.70', $summary->subtotal());
        $this->assertSame(['0' => '0.00', '20' => '12.45'], $summary->vatBreakdown());

        // Fiyat sonradan değişirse sepet güncel fiyatı kullanır (sepette fiyat tutulmaz).
        $pen->update(['price' => '20.00']);
        $this->assertSame('260.00', $carts->summary($cart)->subtotal());

        // Stok düşerse satır işaretlenir ve toplama girmez.
        app(StoreStockService::class)->adjust($book, -2, 'Hasarlı');
        $summary = $carts->summary($cart);
        $this->assertTrue($summary->hasIssues());
        $this->assertSame('insufficient', collect($summary->lines)->firstWhere(fn ($l) => $l->variant->id === $book->id)->issue);
        $this->assertSame('60.00', $summary->subtotal());

        $this->withCookie(CurrentCart::COOKIE, $cart->token)->get(self::HOST.'/sepet')->assertOk()
            ->assertSee('Stokta yalnızca 1 adet var')->assertSee('₺60,00');
    }

    public function test_quantity_update_and_removal_only_on_own_cart(): void
    {
        $pen = $this->variant('Jel Kalem', '24.90', 10);
        $carts = app(CartService::class);
        $mine = $carts->createGuest();
        $item = $carts->add($mine, $pen->id, 1);
        $theirs = $carts->createGuest();
        $foreign = $carts->add($theirs, $pen->id, 1);
        $this->withCookie(CurrentCart::COOKIE, $mine->token);

        $this->put(self::HOST."/sepet/{$item->id}", ['quantity' => 4])->assertSessionHasNoErrors();
        $this->put(self::HOST."/sepet/{$item->id}", ['quantity' => 11])->assertSessionHasErrors('cart');
        $this->assertSame(4, $item->fresh()->quantity);

        $this->put(self::HOST."/sepet/{$foreign->id}", ['quantity' => 3])->assertNotFound();
        $this->delete(self::HOST."/sepet/{$foreign->id}");
        $this->assertNotNull($foreign->fresh());

        $this->put(self::HOST."/sepet/{$item->id}", ['quantity' => 0]);
        $this->assertNull($item->fresh());
    }

    public function test_guest_cart_is_merged_into_customer_cart_on_login(): void
    {
        $pen = $this->variant('Jel Kalem', '24.90', 5);
        $book = $this->variant('Defter', '50.00', 10);
        $customer = StoreCustomer::create(['first_name' => 'Ayşe', 'last_name' => 'Yılmaz', 'email' => 'ayse@ornek.com', 'password' => self::PASSWORD]);
        $carts = app(CartService::class);
        $carts->add($carts->forCustomer($customer), $pen->id, 3);

        $guest = $carts->createGuest();
        $carts->add($guest, $pen->id, 4);
        $carts->add($guest, $book->id, 2);

        $this->withCookie(CurrentCart::COOKIE, $guest->token)
            ->post(self::HOST.'/giris', ['email' => 'ayse@ornek.com', 'password' => self::PASSWORD])->assertRedirect();

        $items = $customer->cart()->firstOrFail()->items()->pluck('quantity', 'variant_id')->all();
        $this->assertSame([$pen->id => 5, $book->id => 2], $items, 'adet satılabilir stokla sınırlanır');
        $this->assertNull(StoreCart::find($guest->id));

        $this->get(self::HOST.'/sepet')->assertOk()->assertSee('Defter')->assertSee('Sepet (7 ürün)', false);
    }

    public function test_cart_page_and_product_page_add_form(): void
    {
        $pen = $this->variant('Jel Kalem', '24.90', 5);

        $this->get(self::HOST.'/sepet')->assertOk()->assertSee('Sepetiniz boş');
        $this->get(self::HOST.'/urun/jel-kalem')->assertOk()->assertSee(route('store.cart.add'), false)->assertSee('name="variant_id"', false);
        $this->get(self::HOST.'/urunler')->assertOk()->assertSee('Jel Kalem sepete ekle');
    }
}
