<?php

namespace App\Http\Controllers\Store\Admin;

use App\Domain\Store\Content\FooterLinks;
use App\Domain\Store\Content\HelpPages;
use App\Domain\Store\Content\StoreFooter;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Vitrin → Yardım Sayfaları: Yardım Merkezi, SSS, teslimat, ödeme, kargo takip ve yasal haklar sayfaları. */
class HelpPageController extends Controller
{
    public function __construct(private HelpPages $pages) {}

    public function index(): View
    {
        return view('store.admin.help-pages.index', [
            'pages' => collect(HelpPages::PAGES)->map(fn ($page, $key) => ['key' => $key, 'title' => $page[0], 'path' => $page[1], 'note' => $page[2]] + HelpPages::get($key)),
        ]);
    }

    public function edit(string $key): View
    {
        return view('store.admin.help-pages.edit', [
            'key' => $key,
            'meta' => HelpPages::PAGES[$key],
            'page' => HelpPages::get($key),
            'icons' => StoreFooter::ICONS,
            'linkOptions' => FooterLinks::options(),
        ]);
    }

    public function update(Request $request, string $key): RedirectResponse
    {
        $this->pages->save($key, $request->except(['_token', '_method']));

        return redirect()->route('store_admin.help-pages.edit', $key)->with('status', HelpPages::PAGES[$key][0].' kaydedildi.');
    }
}
