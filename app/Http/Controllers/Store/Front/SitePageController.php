<?php

namespace App\Http\Controllers\Store\Front;

use App\Domain\Store\Content\ContactMessages;
use App\Domain\Store\Content\OpeningHours;
use App\Domain\Store\Content\SitePages;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreContactMessage;
use App\Models\Store\StorePage;
use App\Models\Store\StoreSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Hakkımızda ve İletişim sayfaları; iletişim formu. Yayında olmayan sayfa 404 verir (önizleme yönetimden yapılır). */
class SitePageController extends Controller
{
    public function about(): View
    {
        return $this->render('about', $this->published('about'), 'store.media.page');
    }

    /** Hakkımızda galerisinin tüm fotoğrafları (ayrı sayfa). Galeri boşsa veya kapatıldıysa 404. */
    public function gallery(): View
    {
        $page = $this->published('about');
        abort_unless($page->content['gallery'] && SitePages::sectionOn($page->content, 'gallery'), 404);

        return view('store.front.about-gallery', [
            'page' => $page,
            'content' => $page->content,
            'photos' => SitePages::galleryPhotos($page->content, 'store.media.page'),
        ]);
    }

    public function contact(): View
    {
        return $this->render('contact', $this->published('contact'), 'store.media.page');
    }

    public function send(Request $request, ContactMessages $messages): RedirectResponse
    {
        abort_unless($this->published('contact')->content['form_enabled'], 404);
        $messages->submit($request->only(['name', 'email', 'phone', 'subject', 'message']), $request->ip(), $request->userAgent());

        return redirect()->to(route('store.contact').'#form')->with('contact_sent', true);
    }

    /** Vitrin ve yönetim önizlemesi aynı görünümü kullanır; görseller önizlemede yönetim adresinden gelir. */
    public function render(string $key, StorePage $page, string $imageRoute, bool $preview = false): View
    {
        $content = $page->content;
        $data = ['page' => $page, 'content' => $content, 'imageRoute' => $imageRoute, 'adminPreview' => $preview, 'settings' => StoreSetting::values()];

        if ($key === 'contact') {
            $data += [
                'hours' => OpeningHours::grouped($content['hours']),
                'openNow' => OpeningHours::isOpenAt($content['hours']),
                'directions' => SitePages::directionsUrl($content),
                'subjects' => StoreContactMessage::SUBJECTS,
                'jsonLd' => [$this->localBusiness($content, $data['settings'])],
            ];
        }

        return view('store.front.'.$key, $data);
    }

    private function published(string $key): StorePage
    {
        $page = SitePages::page($key);
        abort_unless($page->exists && $page->is_published, 404);

        return $page;
    }

    /** Google için işletme bilgisi (adres, telefon, çalışma saatleri). */
    private function localBusiness(array $content, array $settings): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Store',
            'name' => config('store.name'),
            'url' => route('store.home'),
            'telephone' => $settings['phone'] ?: null,
            'email' => $settings['email'] ?: null,
            'address' => $settings['address'] ? ['@type' => 'PostalAddress', 'streetAddress' => $settings['address'], 'addressCountry' => 'TR'] : null,
            'openingHoursSpecification' => OpeningHours::schema($content['hours']) ?: null,
        ]);
    }
}
