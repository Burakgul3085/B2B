<?php

namespace Tests\Feature\Catalog;

use App\Domain\Files\ImageUploader;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Ürün görselleri yüklenirken çevredeki beyaz boşluk kırpılır; açık renkli ürünler ve kenarsız görseller korunur. */
class ProductImageTrimTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_white_margins_around_the_product_are_trimmed_with_a_small_pad(): void
    {
        $size = $this->storeTrimmed($this->canvas(800, 600, [300, 200, 500, 450], 70));

        // İçerik 201x251; her yöne en fazla %3 pay.
        $this->assertGreaterThanOrEqual(201, $size[0]);
        $this->assertLessThanOrEqual(201 + 2 * 9, $size[0]);
        $this->assertGreaterThanOrEqual(251, $size[1]);
        $this->assertLessThanOrEqual(251 + 2 * 9, $size[1]);
    }

    public function test_light_coloured_products_are_not_mistaken_for_background(): void
    {
        // Açık gri (230) ürün: arka plan sayılmamalı, yalnızca etrafı kırpılmalı.
        $size = $this->storeTrimmed($this->canvas(800, 600, [300, 200, 500, 450], 230));

        $this->assertLessThan(260, $size[0]);
        $this->assertGreaterThanOrEqual(201, $size[0]);
    }

    public function test_images_without_white_margins_are_left_as_they_are(): void
    {
        $this->assertSame([800, 600], $this->storeTrimmed($this->canvas(800, 600, [0, 0, 799, 599], 70)));
        $this->assertSame([800, 600], $this->storeTrimmed($this->canvas(800, 600, null, 0)), 'tamamen beyaz görsel');
    }

    public function test_trimming_is_opt_in(): void
    {
        $path = app(ImageUploader::class)->store($this->upload($this->canvas(800, 600, [300, 200, 500, 450], 70)), 'test');

        $this->assertSame([800, 600], array_slice(getimagesizefromstring(Storage::disk('local')->get($path)), 0, 2));
    }

    /** @return array{0: int, 1: int} */
    private function storeTrimmed(GdImage $image): array
    {
        $path = app(ImageUploader::class)->store($this->upload($image), 'test', trimWhitespace: true);

        return array_slice(getimagesizefromstring(Storage::disk('local')->get($path)), 0, 2);
    }

    /** Hafif JPEG benzeri gürültülü beyaz zemin üzerinde bir dikdörtgen "ürün". */
    private function canvas(int $w, int $h, ?array $rect, int $gray): GdImage
    {
        mt_srand(7);
        $image = imagecreatetruecolor($w, $h);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        for ($i = 0; $i < 3000; $i++) {
            imagesetpixel($image, mt_rand(0, $w - 1), mt_rand(0, $h - 1), imagecolorallocate($image, 248, 248, 248));
        }
        if ($rect) {
            imagefilledrectangle($image, ...[...$rect, imagecolorallocate($image, $gray, $gray, $gray)]);
        }

        return $image;
    }

    private function upload(GdImage $image): UploadedFile
    {
        $file = tempnam(sys_get_temp_dir(), 'img');
        imagepng($image, $file);

        return new UploadedFile($file, 'urun.png', 'image/png', null, true);
    }
}
