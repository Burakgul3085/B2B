<?php

namespace Tests\Feature\Catalog;

use App\Domain\Catalog\ProductImageUrl;
use App\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesCatalog;
use Tests\TestCase;

/** B2B ürün görselleri: imzalı, oturumsuz bağlantı; küçük boyutlar yüklemede hazırlanır. */
class ProductImageDeliveryTest extends TestCase
{
    use CreatesCatalog, RefreshDatabase;

    private function uploadedImage(): ProductImage
    {
        Storage::fake('local');
        $business = $this->makeBusiness();
        $product = $this->makeProduct($business);

        $this->actingAs($business->user, 'business')
            ->post(route('business.products.images.store', $product), ['images' => [UploadedFile::fake()->image('kalem.png', 1800, 1200)]])
            ->assertSessionHasNoErrors();
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        return $this->inTenant($business, fn () => ProductImage::firstOrFail());
    }

    public function test_variants_are_prepared_on_upload_and_served_by_signed_url_without_session(): void
    {
        $image = $this->uploadedImage();
        foreach ([320, 640, 1000] as $w) {
            Storage::disk('local')->assertExists(dirname($image->path)."/w{$w}/".basename($image->path));
        }

        $response = $this->get(ProductImageUrl::for($image, ProductImageUrl::THUMB))->assertOk()
            ->assertHeader('Content-Type', 'image/webp')->assertCookieMissing('b2b_public');
        $this->assertSame(320, getimagesize($response->baseResponse->getFile()->getPathname())[0]);
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));

        $this->get(ProductImageUrl::for($image, ProductImageUrl::THUMB), ['If-None-Match' => $response->headers->get('ETag')])->assertStatus(304);
    }

    public function test_unsigned_tampered_or_expired_links_are_rejected(): void
    {
        $image = $this->uploadedImage();
        $url = ProductImageUrl::for($image);

        $this->get(route('product-image', ['image' => $image->id]))->assertForbidden();
        $this->get(str_replace('w=640', 'w=1000', $url))->assertForbidden();
        $this->get(str_replace("urun-gorseli/{$image->id}", 'urun-gorseli/'.($image->id + 1), $url))->assertForbidden();

        $this->travelTo(ProductImageUrl::expires()->addMinute());
        $this->get($url)->assertForbidden();
    }
}
