<?php

namespace App\Http\Controllers\Dealer;

use App\Domain\Catalog\BrandDirectory;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Dealer\Concerns\UsesDealerContext;
use App\Models\Brand;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Bayi Markalar sayfası: A–Z marka listesi, öne çıkan markalar; markaya tıklayınca katalog o markaya süzülür. */
class BrandController extends Controller
{
    use UsesDealerContext;

    public function index(BrandDirectory $directory): View
    {
        $this->relationship(); // bayi bağlamı (seçili toptancı) zorunlu

        $brands = $directory->all();

        return view('dealer.brands', [
            'brands' => $brands,
            'groups' => $brands->groupBy('letter'),
            'featured' => $directory->featured($brands),
            'letters' => BrandDirectory::LETTERS,
        ]);
    }

    public function logo(Brand $brand): StreamedResponse
    {
        abort_unless($brand->is_active && $brand->logo_path && Storage::disk('local')->exists($brand->logo_path), 404);

        return Storage::disk('local')->response($brand->logo_path, null, ['Content-Type' => 'image/webp', 'Cache-Control' => 'private, max-age=86400', 'X-Content-Type-Options' => 'nosniff']);
    }
}
