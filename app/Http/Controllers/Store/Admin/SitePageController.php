<?php

namespace App\Http\Controllers\Store\Admin;

use App\Domain\Store\Content\OpeningHours;
use App\Domain\Store\Content\SitePages;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Store\Front\SitePageController as FrontPages;
use App\Models\Store\StoreSetting;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Vitrin → Hakkımızda ve İletişim: sayfaya özel formlar, önizleme. */
class SitePageController extends Controller
{
    private const ABOUT_FIELDS = ['headline', 'tagline', 'story_title', 'story', 'badge_value', 'badge_label', 'stats', 'pillars', 'features_title', 'features',
        'timeline_title', 'timeline', 'gallery_title', 'gallery_captions', 'gallery_order', 'remove_gallery', 'team_title', 'team', 'remove_team_photo',
        'remove_cover', 'remove_story_image', 'show_cta', 'cta_title', 'cta_text', 'cta_label', 'cta_url', 'sections', 'is_published', 'seo_title', 'seo_description'];

    private const CONTACT_FIELDS = ['intro', 'hours', 'hours_note', 'map_input', 'form_enabled', 'is_published', 'seo_title', 'seo_description'];

    public function __construct(private SitePages $pages) {}

    public function about(): View
    {
        return view('store.admin.storefront.site-pages.about', ['page' => SitePages::page('about'), 'icons' => SitePages::ICONS]);
    }

    public function updateAbout(Request $request): RedirectResponse
    {
        $request->validate([
            'cover' => ['nullable', 'file', 'max:8192'],
            'story_images' => ['nullable', 'array', 'max:2'],
            'story_images.*' => ['file', 'max:8192'],
            'gallery' => ['nullable', 'array', 'max:'.SitePages::MAX_GALLERY],
            'gallery.*' => ['file', 'max:8192'],
            'team_photo' => ['nullable', 'array', 'max:'.SitePages::MAX_TEAM],
            'team_photo.*' => ['file', 'max:8192'],
            'remove_gallery' => ['nullable', 'array'],
            'remove_gallery.*' => ['string', 'max:60'],
        ], [], ['cover' => 'kapak fotoğrafı', 'story_images.*' => 'hikâye fotoğrafı', 'gallery' => 'galeri', 'gallery.*' => 'galeri fotoğrafı', 'team_photo.*' => 'ekip fotoğrafı']);

        try {
            $this->pages->saveAbout($request->only(self::ABOUT_FIELDS), [
                'cover' => $request->file('cover'),
                'story_images' => (array) $request->file('story_images', []),
                'gallery' => array_values($request->file('gallery', [])),
                'team' => (array) $request->file('team_photo', []),
            ]);
        } catch (DomainException $e) {
            return back()->withErrors(['cover' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'Hakkımızda sayfası kaydedildi.');
    }

    public function contact(): View
    {
        return view('store.admin.storefront.site-pages.contact', [
            'page' => SitePages::page('contact'),
            'settings' => StoreSetting::values(),
            'days' => OpeningHours::DAYS,
        ]);
    }

    public function updateContact(Request $request): RedirectResponse
    {
        $this->pages->saveContact($request->only(self::CONTACT_FIELDS));

        return back()->with('status', 'İletişim sayfası kaydedildi.');
    }

    /** Kaydedilmiş içeriği vitrin görünümüyle gösterir (yayında olmasa da). */
    public function preview(string $key): View
    {
        return app(FrontPages::class)->render($key, SitePages::page($key), 'store_admin.site-pages.image', true);
    }

    /** Önizlemedeki görseller (sayfa yayında olmasa da). */
    public function image(string $file): StreamedResponse
    {
        abort_unless(SitePages::ownsImage($file, false), 404);

        return Storage::disk('local')->response('store/pages/'.$file, null, ['Content-Type' => 'image/webp', 'Cache-Control' => 'private, max-age=86400']);
    }
}
