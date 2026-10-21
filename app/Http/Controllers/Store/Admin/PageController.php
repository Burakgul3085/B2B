<?php

namespace App\Http\Controllers\Store\Admin;

use App\Domain\Store\Storefront\StorefrontAdmin;
use App\Http\Controllers\Controller;
use App\Models\Store\StorePage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Sabit sayfalar (Hakkımızda, İletişim, Teslimat koşulları…). */
class PageController extends Controller
{
    private const FIELDS = ['title', 'slug', 'body', 'seo_title', 'seo_description', 'is_active', 'show_in_footer'];

    public function __construct(private StorefrontAdmin $storefront) {}

    public function index(): View
    {
        return view('store.admin.storefront.pages.index', ['pages' => StorePage::orderBy('sort')->orderBy('title')->get()]);
    }

    public function create(): View
    {
        return view('store.admin.storefront.pages.form', ['page' => new StorePage]);
    }

    public function store(Request $request): RedirectResponse
    {
        $page = $this->storefront->savePage(null, $request->only(self::FIELDS));

        return redirect()->route('store_admin.pages.edit', $page)->with('status', 'Sayfa oluşturuldu.');
    }

    public function edit(StorePage $storePage): View
    {
        return view('store.admin.storefront.pages.form', ['page' => $storePage]);
    }

    public function update(Request $request, StorePage $storePage): RedirectResponse
    {
        $this->storefront->savePage($storePage, $request->only(self::FIELDS));

        return back()->with('status', 'Sayfa kaydedildi.');
    }

    public function destroy(StorePage $storePage): RedirectResponse
    {
        $this->storefront->deletePage($storePage);

        return redirect()->route('store_admin.pages.index')->with('status', "\"{$storePage->title}\" silindi.");
    }
}
