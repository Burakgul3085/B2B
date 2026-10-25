<?php

namespace Tests\Feature\Catalog;

use App\Domain\Files\ImageUploader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Telefon fotoğraflarındaki EXIF yön bilgisi yüklemede piksellere uygulanır: fotoğraf yan veya ters görünmez. */
class ImageOrientationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_exif_rotated_photos_are_stored_upright(): void
    {
        // Sensör yönünde 400x200; "Orientation 6" = 90° saat yönünde döndürülerek gösterilmeli → 200x400.
        $this->assertSame([200, 400], $this->upload($this->jpeg(400, 200, 6)));
        $this->assertSame([200, 400], $this->upload($this->jpeg(400, 200, 8)));
        $this->assertSame([400, 200], $this->upload($this->jpeg(400, 200, 3)));
    }

    public function test_photos_without_orientation_are_left_as_they_are(): void
    {
        $this->assertSame([400, 200], $this->upload($this->jpeg(400, 200, null)));
        $this->assertSame([400, 200], $this->upload($this->jpeg(400, 200, 1)));
    }

    public function test_rotation_keeps_the_top_of_the_photo_on_top(): void
    {
        // Sol yarısı kırmızı: Orientation 6'da sol kenar üste gelir.
        $path = (new ImageUploader)->store($this->jpegFile($this->jpeg(400, 200, 6, true)), 'test');
        $image = imagecreatefromstring(Storage::disk('local')->get($path));

        $top = imagecolorsforindex($image, imagecolorat($image, 100, 20));
        $bottom = imagecolorsforindex($image, imagecolorat($image, 100, 380));
        $this->assertGreaterThan(200, $top['red']);
        $this->assertLessThan(60, $bottom['red']);
    }

    /** @return array{0: int, 1: int} */
    private function upload(string $binary): array
    {
        $path = (new ImageUploader)->store($this->jpegFile($binary), 'test');
        $image = imagecreatefromstring(Storage::disk('local')->get($path));

        return [imagesx($image), imagesy($image)];
    }

    private function jpegFile(string $binary): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'exif').'.jpg';
        file_put_contents($tmp, $binary);

        return new UploadedFile($tmp, 'foto.jpg', 'image/jpeg', null, true);
    }

    /** GD ile JPEG üretir ve isteğe bağlı olarak en küçük EXIF bloğunu (yalnızca Orientation) ekler. */
    private function jpeg(int $width, int $height, ?int $orientation, bool $redLeftHalf = false): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 20, 40, 160));
        if ($redLeftHalf) {
            imagefilledrectangle($image, 0, 0, intdiv($width, 2) - 1, $height - 1, imagecolorallocate($image, 230, 20, 20));
        }
        ob_start();
        imagejpeg($image, null, 95);
        $jpeg = (string) ob_get_clean();

        if ($orientation === null) {
            return $jpeg;
        }

        // TIFF (big endian) + tek IFD girdisi: 0x0112 Orientation, SHORT, 1 adet.
        $tiff = 'MM'.pack('n', 42).pack('N', 8).pack('n', 1).pack('nnN', 0x0112, 3, 1).pack('nn', $orientation, 0).pack('N', 0);
        $app1 = "Exif\0\0".$tiff;

        return substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($app1) + 2).$app1.substr($jpeg, 2);
    }
}
