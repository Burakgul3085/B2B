<?php

namespace App\Http\Controllers\Store\Front;

use App\Domain\Store\Storefront\Catalog;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Tüm kategoriler menüsünün içeriği (masaüstü açılır panel / mobil adım adım menü).
 * Adres kategori sürümünü taşır (?v=); sürüm değişmedikçe tarayıcı ve sunucu önbellekten verir.
 */
class CategoryMenuController extends Controller
{
    public function __invoke(Request $request, Catalog $catalog, string $part): Response
    {
        $version = $catalog->menuVersion();
        $html = Cache::remember("store.menu.html.{$part}.{$version}", now()->addDay(), function () use ($catalog, $part) {
            $menu = $catalog->menu();

            return view($part === 'mobil' ? 'store.front._menu-mobile' : 'store.front._menu-desktop', [
                'menu' => $menu,
                'menuNames' => $menu->map(fn ($root) => implode(' ', [
                    $root->name, ...$root->children->flatMap(fn ($child) => [$child->name, ...$child->children->pluck('name')]),
                ]))->all(),
            ])->render();
        });

        $response = response($html)->header('Content-Type', 'text/html; charset=UTF-8');

        return $request->query('v') === $version
            ? $response->header('Cache-Control', 'public, max-age=86400')
            : $response->header('Cache-Control', 'no-cache');
    }
}
