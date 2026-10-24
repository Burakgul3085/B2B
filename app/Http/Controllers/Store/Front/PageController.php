<?php

namespace App\Http\Controllers\Store\Front;

use App\Domain\Store\Storefront\BrandDirectory;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** Markalar sayfası: öne çıkan markalar ve A–Z liste. (Hakkımızda ve İletişim: SitePageController) */
class PageController extends Controller
{
    public function brands(BrandDirectory $directory): View
    {
        $brands = $directory->all();

        return view('store.front.brands', [
            'brands' => $brands,
            'groups' => $brands->groupBy('letter'),
            'featured' => $directory->featured($brands),
            'letters' => BrandDirectory::LETTERS,
        ]);
    }
}
