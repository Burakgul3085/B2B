<?php

namespace App\Domain\Store\Content;

use App\Domain\Audit\AuditLogger;
use App\Domain\Files\ImageUploader;
use App\Domain\Store\Storefront\ImageVariants;
use App\Models\Store\StorePage;
use App\Models\Store\StoreSetting;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Mağazanın sabit sayfaları. Serbest sayfa oluşturma yoktur: her sayfa burada tanımlıdır (adı, adresi, alanları,
 * varsayılan içeriği) ve yönetimde kendi formuyla düzenlenir. Yeni bir sayfa gerekirse buraya eklenir.
 *  - about (Hakkımızda): kapak, rakamlar, hikâye (Markdown + iki fotoğraf), misyon/vizyon/değerler, "neden biz"
 *    kartları, zaman çizelgesi, fotoğraf galerisi (açıklamalı, sıralı), ekip ve kapanış bandı. Her bölüm açılıp kapatılır.
 *  - contact (İletişim): giriş metni, çalışma saatleri, harita, iletişim formu. Telefon, e-posta, adres ve satıcı
 *    bilgileri Mağaza Ayarları'ndan gelir (tek yerden yönetilir).
 */
class SitePages
{
    public const PAGES = [
        'about' => ['title' => 'Hakkımızda', 'path' => 'hakkimizda'],
        'contact' => ['title' => 'İletişim', 'path' => 'iletisim'],
    ];

    /** "Neden biz" kartlarında seçilebilen simgeler. */
    public const ICONS = [
        'truck' => 'Kargo / teslimat', 'shield' => 'Güvenlik', 'check-circle' => 'Onay', 'star' => 'Yıldız', 'heart' => 'Kalp',
        'gift' => 'Hediye', 'clock' => 'Saat', 'package' => 'Paket', 'book-open' => 'Kitap', 'pencil' => 'Kalem',
        'percent' => 'İndirim', 'users' => 'Müşteriler', 'map-pin' => 'Konum', 'award' => 'Ödül', 'phone' => 'Telefon',
    ];

    public const MAX_STATS = 6;

    public const MAX_FEATURES = 6;

    public const MAX_GALLERY = 24;

    public const MAX_TIMELINE = 10;

    public const MAX_TEAM = 8;

    /** Hakkımızda bölümleri (yönetimde açılıp kapatılır; boş bölüm zaten görünmez). */
    public const ABOUT_SECTIONS = [
        'stats' => 'Rakamlarla biz', 'story' => 'Hikâyemiz', 'pillars' => 'Misyon, vizyon, değerler', 'features' => 'Neden biz',
        'timeline' => 'Yolculuğumuz', 'gallery' => 'Fotoğraf galerisi', 'team' => 'Ekibimiz',
    ];

    /** Misyon / vizyon / değerler kartları: anahtar => [varsayılan başlık, simge] */
    public const PILLARS = ['mission' => ['Misyonumuz', 'award'], 'vision' => ['Vizyonumuz', 'eye'], 'values' => ['Değerlerimiz', 'heart']];

    private const IMAGE_DIR = 'store/pages';

    private const IMAGE_MAX_BYTES = 8 * 1024 * 1024;

    public function __construct(private AuditLogger $audit, private ImageUploader $images, private ImageVariants $variants, private MapLink $maps) {}

    /** Sayfa kaydı (yoksa varsayılan içerikle, kaydedilmemiş). */
    public static function page(string $key): StorePage
    {
        abort_unless(isset(self::PAGES[$key]), 404);
        $page = StorePage::firstOrNew(['key' => $key]);
        $page->content = array_replace(self::defaults($key), $page->content ?? []);

        return $page;
    }

    /** Alt bilgide ve site haritasında gösterilecek yayındaki sayfalar. @return list<array{key: string, title: string, url: string, updated_at: mixed}> */
    public static function published(): array
    {
        return StorePage::where('is_published', true)->get(['key', 'updated_at'])
            ->filter(fn ($p) => isset(self::PAGES[$p->key]))
            ->sortBy(fn ($p) => array_search($p->key, array_keys(self::PAGES), true))
            ->map(fn ($p) => ['key' => $p->key, 'title' => self::PAGES[$p->key]['title'], 'url' => route('store.'.$p->key), 'updated_at' => $p->updated_at])
            ->values()->all();
    }

    public static function defaults(string $key): array
    {
        $name = config('store.name');

        return match ($key) {
            'about' => [
                'headline' => 'Hakkımızda',
                'tagline' => "{$name} — okul, ofis ve sanat malzemelerinde güvenilir adresiniz.",
                'story' => "{$name} olarak kırtasiye ihtiyaçlarınızı uygun fiyat ve hızlı teslimatla karşılıyoruz.\n\nBu metni mağaza yönetiminde **Vitrin → Hakkımızda** ekranından düzenleyebilirsiniz.",
                'stats' => [],
                'story_title' => 'Hikâyemiz',
                'story_images' => [],
                'badge_value' => null,
                'badge_label' => null,
                'pillars' => array_map(fn ($p) => ['title' => $p[0], 'text' => null], self::PILLARS),
                'features_title' => null,
                'features' => [
                    ['icon' => 'truck', 'title' => 'Hızlı teslimat', 'text' => 'Siparişleriniz özenle paketlenip en kısa sürede yola çıkar.'],
                    ['icon' => 'shield', 'title' => 'Güvenli ödeme', 'text' => 'Kart bilgileriniz iyzico güvencesiyle korunur.'],
                    ['icon' => 'check-circle', 'title' => 'Orijinal ürünler', 'text' => 'Tüm ürünler yetkili tedarikçilerden gelir.'],
                ],
                'timeline_title' => 'Yolculuğumuz',
                'timeline' => [],
                'cover' => null,
                'gallery_title' => 'Mağazamızdan kareler',
                'gallery' => [],
                'gallery_captions' => [],
                'team_title' => 'Ekibimiz',
                'team' => [],
                'show_cta' => true,
                'cta_title' => 'Aradığınız kırtasiye ürünleri burada',
                'cta_text' => null,
                'cta_label' => 'Ürünlere göz atın',
                'cta_url' => null,
                'hidden_sections' => [],
            ],
            'contact' => [
                'intro' => 'Sorularınız, siparişleriniz veya toplu alım talepleriniz için bize ulaşın.',
                'hours' => OpeningHours::defaults(),
                'hours_note' => null,
                'map_input' => null,
                'map_embed' => null,
                'map_directions' => null,
                'form_enabled' => true,
            ],
        };
    }

    /** Markdown'dan güvenli HTML (ham HTML ve tehlikeli bağlantılar atılır). */
    public static function markdown(?string $text): HtmlString
    {
        return new HtmlString(Str::markdown((string) $text, ['html_input' => 'strip', 'allow_unsafe_links' => false]));
    }

    /** Bu dosya yayındaki bir sayfanın görseli mi? (vitrin görsel adresi için) */
    public static function ownsImage(string $file, bool $publishedOnly = true): bool
    {
        if (! preg_match('/^[A-Za-z0-9]{40}\.webp$/', $file)) {
            return false;
        }
        $path = self::IMAGE_DIR.'/'.$file;

        return StorePage::where('key', 'about')->when($publishedOnly, fn ($q) => $q->where('is_published', true))->get(['content'])
            ->contains(fn ($p) => in_array($path, self::aboutImages($p->content ?? []), true));
    }

    /** Hakkımızda sayfasının tüm fotoğrafları (kapak, hikâye, galeri, ekip). @return list<string> */
    public static function aboutImages(array $content): array
    {
        return array_values(array_filter([
            $content['cover'] ?? null,
            ...($content['story_images'] ?? []),
            ...($content['gallery'] ?? []),
            ...array_column($content['team'] ?? [], 'photo'),
        ]));
    }

    /** Bölüm yayında mı (yönetimde kapatılmamış)? */
    public static function sectionOn(array $content, string $section): bool
    {
        return ! in_array($section, $content['hidden_sections'] ?? [], true);
    }

    /**
     * Hakkımızda: tüm bölümler. $files: cover, story_images[0|1], gallery[] (yeni), team[i] (ekip fotoğrafı).
     * Kaldırma işaretleri $input'tadır: remove_cover, remove_story_image[i], remove_gallery[], remove_team_photo[i].
     *
     * @param  array{cover?: ?UploadedFile, story_images?: array<int, UploadedFile>, gallery?: list<UploadedFile>, team?: array<int, UploadedFile>}  $files
     *
     * @throws ValidationException|DomainException
     */
    public function saveAbout(array $input, array $files = []): StorePage
    {
        $text = fn (int $max) => ['nullable', 'string', 'max:'.$max];
        $data = Validator::make($input, [
            'headline' => ['required', 'string', 'max:120'],
            'tagline' => $text(240),
            'story_title' => $text(80),
            'story' => $text(20000),
            'badge_value' => $text(12),
            'badge_label' => $text(40),
            'stats' => ['nullable', 'array', 'max:'.self::MAX_STATS],
            'stats.*.value' => $text(20),
            'stats.*.label' => $text(60),
            'stats.*.icon' => ['nullable', 'in:'.implode(',', array_keys(self::ICONS))],
            'pillars' => ['nullable', 'array'],
            'pillars.*.title' => $text(60),
            'pillars.*.text' => $text(600),
            'features_title' => $text(80),
            'features' => ['nullable', 'array', 'max:'.self::MAX_FEATURES],
            'features.*.icon' => ['nullable', 'in:'.implode(',', array_keys(self::ICONS))],
            'features.*.title' => $text(60),
            'features.*.text' => $text(200),
            'timeline_title' => $text(80),
            'timeline' => ['nullable', 'array', 'max:'.self::MAX_TIMELINE],
            'timeline.*.year' => $text(12),
            'timeline.*.title' => $text(80),
            'timeline.*.text' => $text(300),
            'gallery_title' => $text(80),
            'gallery_captions' => ['nullable', 'array'],
            'gallery_captions.*' => $text(120),
            'gallery_order' => ['nullable', 'array'],
            'gallery_order.*' => ['nullable', 'integer', 'min:0', 'max:999'],
            'team_title' => $text(80),
            'team' => ['nullable', 'array', 'max:'.self::MAX_TEAM],
            'team.*.name' => $text(60),
            'team.*.role' => $text(60),
            'cta_title' => $text(120),
            'cta_text' => $text(240),
            'cta_label' => $text(40),
            'cta_url' => ['nullable', 'string', 'max:255', 'regex:#^(https://|/)#'],
            'seo_title' => $text(160),
            'seo_description' => $text(320),
        ], ['cta_url.regex' => 'Düğme adresi https:// veya / ile başlamalı.'], [
            'headline' => 'başlık', 'tagline' => 'slogan', 'story' => 'hikâye', 'story_title' => 'hikâye başlığı', 'badge_value' => 'rozet değeri',
            'badge_label' => 'rozet yazısı', 'features_title' => 'bölüm başlığı', 'timeline_title' => 'bölüm başlığı', 'gallery_title' => 'bölüm başlığı',
            'team_title' => 'bölüm başlığı', 'cta_title' => 'bant başlığı', 'cta_text' => 'bant metni', 'cta_label' => 'düğme yazısı', 'cta_url' => 'düğme adresi',
            'seo_title' => 'SEO başlığı', 'seo_description' => 'SEO açıklaması',
        ])->validate();

        $clean = fn ($v) => trim((string) $v) === '' ? null : trim((string) $v);
        // Boş satırlar atılır; yarım doldurulmuş satır uyarılır.
        $errors = [];
        $stats = [];
        foreach (array_values($data['stats'] ?? []) as $i => $row) {
            [$value, $label] = [trim((string) ($row['value'] ?? '')), trim((string) ($row['label'] ?? ''))];
            if ($value === '' && $label === '') {
                continue;
            }
            if ($value === '' || $label === '') {
                $errors["stats.{$i}"] = 'Rakam satırında hem değeri hem açıklamayı yazın (ör. "2005" ve "Kuruluş").';
            }
            $stats[] = ['value' => $value, 'label' => $label, 'icon' => $row['icon'] ?? 'star'];
        }
        $features = [];
        foreach (array_values($data['features'] ?? []) as $i => $row) {
            [$title, $body] = [trim((string) ($row['title'] ?? '')), trim((string) ($row['text'] ?? ''))];
            if ($title === '' && $body === '') {
                continue;
            }
            if ($title === '') {
                $errors["features.{$i}"] = 'Kartın başlığını yazın.';
            }
            $features[] = ['icon' => $row['icon'] ?? 'check-circle', 'title' => $title, 'text' => $body];
        }
        $timeline = [];
        foreach (array_values($data['timeline'] ?? []) as $i => $row) {
            [$year, $title] = [trim((string) ($row['year'] ?? '')), trim((string) ($row['title'] ?? ''))];
            if ($year === '' && $title === '' && trim((string) ($row['text'] ?? '')) === '') {
                continue;
            }
            if ($year === '' || $title === '') {
                $errors["timeline.{$i}"] = 'Her adımda yıl ve başlık olmalı (ör. "1998" ve "İlk dükkânımızı açtık").';
            }
            $timeline[] = ['year' => $year, 'title' => $title, 'text' => $clean($row['text'] ?? null)];
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $page = self::page('about');
        $content = $page->content;
        $remove = [];
        $store = fn (UploadedFile $file, int $edge) => $this->images->store($file, self::IMAGE_DIR, $edge, self::IMAGE_MAX_BYTES, 84);

        // Kapak
        if (($files['cover'] ?? null) || filter_var($input['remove_cover'] ?? false, FILTER_VALIDATE_BOOL)) {
            $remove[] = $content['cover'];
            $content['cover'] = ($files['cover'] ?? null) ? $this->images->store($files['cover'], self::IMAGE_DIR, 2400, self::IMAGE_MAX_BYTES, 86) : null;
        }

        // Hikâye fotoğrafları (2 yuva)
        $storyImages = array_values($content['story_images'] ?? []);
        for ($slot = 0; $slot < 2; $slot++) {
            $new = $files['story_images'][$slot] ?? null;
            if ($new || filter_var($input['remove_story_image'][$slot] ?? false, FILTER_VALIDATE_BOOL)) {
                $remove[] = $storyImages[$slot] ?? null;
                $storyImages[$slot] = $new ? $store($new, 1600) : null;
            }
        }
        $storyImages = array_values(array_filter($storyImages));

        // Galeri: kaldırılanlar silinir, sıra ve açıklamalar uygulanır, yeniler sona eklenir.
        $removeGallery = (array) ($input['remove_gallery'] ?? []);
        $order = $data['gallery_order'] ?? [];
        $gallery = [];
        foreach ($content['gallery'] as $i => $path) {
            in_array(basename($path), $removeGallery, true) ? $remove[] = $path : $gallery[] = ['path' => $path, 'sort' => (int) ($order[basename($path)] ?? $i)];
        }
        usort($gallery, fn ($a, $b) => $a['sort'] <=> $b['sort']);
        $gallery = array_column($gallery, 'path');
        $newGallery = $files['gallery'] ?? [];
        if (count($gallery) + count($newGallery) > self::MAX_GALLERY) {
            throw ValidationException::withMessages(['gallery' => 'Galeride en fazla '.self::MAX_GALLERY.' fotoğraf olabilir.']);
        }
        foreach ($newGallery as $file) {
            $gallery[] = $store($file, 1800);
        }
        $captions = [];
        foreach ($gallery as $path) {
            $caption = $clean(($data['gallery_captions'] ?? [])[basename($path)] ?? ($content['gallery_captions'][basename($path)] ?? null));
            if ($caption) {
                $captions[basename($path)] = $caption;
            }
        }

        // Ekip: adı boşaltılan kişi (ve fotoğrafı) kaldırılır.
        $team = [];
        $oldTeam = array_values($content['team'] ?? []);
        foreach (range(0, self::MAX_TEAM - 1) as $i) {
            $row = $data['team'][$i] ?? null;
            $oldPhoto = $oldTeam[$i]['photo'] ?? null;
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                $remove[] = $oldPhoto;

                continue;
            }
            $photo = $oldPhoto;
            if (($files['team'][$i] ?? null) || filter_var($input['remove_team_photo'][$i] ?? false, FILTER_VALIDATE_BOOL)) {
                $remove[] = $oldPhoto;
                $photo = ($files['team'][$i] ?? null) ? $store($files['team'][$i], 800) : null;
            }
            $team[] = ['name' => $name, 'role' => $clean($row['role'] ?? null), 'photo' => $photo];
        }
        // Formda ekip satırları hiç gönderilmediyse (eski form) mevcut ekip korunur.
        if (! array_key_exists('team', $input)) {
            $team = $oldTeam;
            $remove = array_values(array_diff(array_filter($remove), array_column($oldTeam, 'photo')));
        }

        $pillars = [];
        foreach (self::PILLARS as $key => [$title]) {
            $row = $data['pillars'][$key] ?? ($content['pillars'][$key] ?? []);
            $pillars[$key] = ['title' => $clean($row['title'] ?? null) ?? $title, 'text' => $clean($row['text'] ?? null)];
        }

        // Bölüm anahtarları gönderildiyse kapalı olanlar kaydedilir; gönderilmediyse önceki durum korunur.
        $hidden = array_key_exists('sections', $input)
            ? array_values(array_filter(array_keys(self::ABOUT_SECTIONS), fn ($k) => ! filter_var($input['sections'][$k] ?? false, FILTER_VALIDATE_BOOL)))
            : ($content['hidden_sections'] ?? []);

        $content = array_replace($content, [
            'headline' => trim($data['headline']),
            'tagline' => $clean($data['tagline'] ?? null),
            'story_title' => $clean($data['story_title'] ?? null) ?? 'Hikâyemiz',
            'story' => $clean($data['story'] ?? null),
            'story_images' => $storyImages,
            'badge_value' => $clean($data['badge_value'] ?? null),
            'badge_label' => $clean($data['badge_label'] ?? null),
            'stats' => $stats,
            'pillars' => $pillars,
            'features_title' => $clean($data['features_title'] ?? null),
            'features' => $features,
            'timeline_title' => $clean($data['timeline_title'] ?? null) ?? 'Yolculuğumuz',
            'timeline' => $timeline,
            'gallery_title' => $clean($data['gallery_title'] ?? null) ?? 'Mağazamızdan kareler',
            'gallery' => $gallery,
            'gallery_captions' => $captions,
            'team_title' => $clean($data['team_title'] ?? null) ?? 'Ekibimiz',
            'team' => $team,
            'show_cta' => filter_var($input['show_cta'] ?? false, FILTER_VALIDATE_BOOL),
            'cta_title' => $clean($data['cta_title'] ?? null) ?? 'Aradığınız kırtasiye ürünleri burada',
            'cta_text' => $clean($data['cta_text'] ?? null),
            'cta_label' => $clean($data['cta_label'] ?? null) ?? 'Ürünlere göz atın',
            'cta_url' => $clean($data['cta_url'] ?? null),
            'hidden_sections' => $hidden,
        ]);

        $this->persist($page, $content, $input, $data);
        $keep = self::aboutImages($content);
        foreach (array_unique(array_filter($remove)) as $path) {
            if (! in_array($path, $keep, true)) {
                $this->images->delete($path);
                $this->variants->forget($path);
            }
        }

        return $page;
    }

    /**
     * İletişim: giriş metni, saatler, harita, form. Harita bağlantısı değiştiyse yeniden çözülür.
     *
     * @throws ValidationException
     */
    public function saveContact(array $input): StorePage
    {
        $data = Validator::make($input, [
            'intro' => ['nullable', 'string', 'max:500'],
            'hours' => ['nullable', 'array'],
            'hours_note' => ['nullable', 'string', 'max:200'],
            'map_input' => ['nullable', 'string', 'max:3000'],
            'seo_title' => ['nullable', 'string', 'max:160'],
            'seo_description' => ['nullable', 'string', 'max:320'],
        ], [], ['intro' => 'giriş metni', 'hours_note' => 'saat notu', 'map_input' => 'harita bağlantısı', 'seo_title' => 'SEO başlığı', 'seo_description' => 'SEO açıklaması'])->validate();

        [$hours, $errors] = OpeningHours::fromInput((array) ($data['hours'] ?? []));

        $page = self::page('contact');
        $content = $page->content;
        $mapInput = trim((string) ($data['map_input'] ?? ''));
        if ($mapInput === '') {
            $content = array_replace($content, ['map_input' => null, 'map_embed' => null, 'map_directions' => null]);
        } elseif ($mapInput !== $content['map_input']) {
            try {
                $map = $this->maps->resolve($mapInput);
                $content = array_replace($content, ['map_input' => $mapInput, 'map_embed' => $map['embed'], 'map_directions' => $map['directions']]);
            } catch (DomainException $e) {
                $errors['map_input'] = $e->getMessage();
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $content = array_replace($content, [
            'intro' => trim((string) ($data['intro'] ?? '')) ?: null,
            'hours' => $hours,
            'hours_note' => trim((string) ($data['hours_note'] ?? '')) ?: null,
            'form_enabled' => filter_var($input['form_enabled'] ?? false, FILTER_VALIDATE_BOOL),
        ]);
        $this->persist($page, $content, $input, $data);

        return $page;
    }

    /** Yol tarifi: haritadan; yoksa Mağaza Ayarları'ndaki adresle. */
    public static function directionsUrl(array $content): ?string
    {
        if ($content['map_directions'] ?? null) {
            return $content['map_directions'];
        }
        $address = StoreSetting::get('address');

        return $address ? MapLink::directionsToAddress($address) : null;
    }

    private function persist(StorePage $page, array $content, array $input, array $data): void
    {
        DB::transaction(function () use ($page, $content, $input, $data) {
            $wasPublished = (bool) $page->is_published;
            $page->fill([
                'content' => $content,
                'is_published' => filter_var($input['is_published'] ?? false, FILTER_VALIDATE_BOOL),
                'seo_title' => trim((string) ($data['seo_title'] ?? '')) ?: null,
                'seo_description' => trim((string) ($data['seo_description'] ?? '')) ?: null,
            ])->save();
            $this->audit->log('store.site_page_updated', $page, ['is_published' => $wasPublished], ['page' => self::PAGES[$page->key]['title'], 'is_published' => $page->is_published]);
        });
    }
}
