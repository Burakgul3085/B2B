<?php

namespace App\Http\Controllers;

use App\Domain\Files\ImageResponse;
use App\Domain\Files\ImageVariants;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * B2B ürün görseli (toptancı ve bayi ortak). Erişim imzalı bağlantıyladır (App\Domain\Catalog\ProductImageUrl);
 * oturum kullanılmaz. İmzayı "signed" middleware'i denetler. Görsel numarası imzalı olduğu için işletme kapsamı
 * burada bilerek kaldırılır: bağlantıyı yalnızca görseli kendi işletmesi/toptancısı bağlamında görebilen kullanıcı alır.
 */
class ProductImageFileController extends Controller
{
    public function __invoke(Request $request, int $image, ImageVariants $variants): Response
    {
        $record = ProductImage::withoutBusinessScope()->find($image) ?? abort(404);
        $path = $variants->path($record->path, $request->integer('w') ?: null) ?? abort(404);

        return ImageResponse::make($request, $path, 'private, max-age=604800');
    }
}
