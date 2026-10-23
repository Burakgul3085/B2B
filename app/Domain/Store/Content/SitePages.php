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
 *  - about (Hakkımızda): başlık, slogan, kapak, hikâye (Markdown), rakamlar, "neden biz" kartları, galeri.
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

    public const MAX_STATS = 4;

    public const MAX_FEATURES = 4;

    public const MAX_GALLERY = 6;

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
                'features' => [
                    ['icon' => 'truck', 'title' => 'Hızlı teslimat', 'text' => 'Siparişleriniz özenle paketlenip en kısa sürede yola çıkar.'],
                    ['icon' => 'shield', 'title' => 'Güvenli ödeme', 'text' => 'Kart bilgileriniz iyzico güvencesiyle korunur.'],
                    ['icon' => 'check-circle', 'title' => 'Orijinal ürünler', 'text' => 'Tüm ürünler yetkili tedarikçilerden gelir.'],
                ],
                'cover' => null,
                'gallery' => [],
                'show_cta' => true,
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
            ->contains(fn ($p) => ($p->content['cover'] ?? null) === $path || in_array($path, $p->content['gallery'] ?? [], true));
    }

    /**
     * Hakkımızda: metin alanları, rakamlar, kartlar, kapak ve galeri.
     *
     * @param  list<UploadedFile>  $newGallery
     * @param  list<string>  $removeGallery  kaldırılacak galeri dosya adları
     *
     * @throws ValidationException|DomainException
     */
    public function saveAbout(array $input, ?UploadedFile $cover, bool $removeCover, array $newGallery, array $removeGallery): StorePage
    {
        $data = Validator::make($input, [
            'headline' => ['required', 'string', 'max:120'],
            'tagline' => ['nullable', 'string', 'max:240'],
            'story' => ['nullable', 'string', 'max:20000'],
            'stats' => ['nullable', 'array', 'max:'.self::MAX_STATS],
            'stats.*.value' => ['nullable', 'string', 'max:20'],
            'stats.*.label' => ['nullable', 'string', 'max:60'],
            'features' => ['nullable', 'array', 'max:'.self::MAX_FEATURES],
            'features.*.icon' => ['nullable', 'in:'.implode(',', array_keys(self::ICONS))],
            'features.*.title' => ['nullable', 'string', 'max:60'],
            'features.*.text' => ['nullable', 'string', 'max:200'],
            'seo_title' => ['nullable', 'string', 'max:160'],
            'seo_description' => ['nullable', 'string', 'max:320'],
        ], [], ['headline' => 'başlık', 'tagline' => 'slogan', 'story' => 'hikâye', 'seo_title' => 'SEO başlığı', 'seo_description' => 'SEO açıklaması'])->validate();

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
            $stats[] = ['value' => $value, 'label' => $label];
        }
        $features = [];
        foreach (array_values($data['features'] ?? []) as $i => $row) {
            [$title, $text] = [trim((string) ($row['title'] ?? '')), trim((string) ($row['text'] ?? ''))];
            if ($title === '' && $text === '') {
                continue;
            }
            if ($title === '') {
                $errors["features.{$i}"] = 'Kartın başlığını yazın.';
            }
            $features[] = ['icon' => $row['icon'] ?? 'check-circle', 'title' => $title, 'text' => $text];
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $page = self::page('about');
        $content = $page->content;
        $remove = [];

        if ($cover || $removeCover) {
            $remove[] = $content['cover'];
            $content['cover'] = $cover ? $this->images->store($cover, self::IMAGE_DIR, 2400, self::IMAGE_MAX_BYTES, 86) : null;
        }
        $gallery = [];
        foreach ($content['gallery'] as $path) {
            in_array(basename($path), $removeGallery, true) ? $remove[] = $path : $gallery[] = $path;
        }
        if (count($gallery) + count($newGallery) > self::MAX_GALLERY) {
            throw ValidationException::withMessages(['gallery' => 'Galeride en fazla '.self::MAX_GALLERY.' fotoğraf olabilir.']);
        }
        foreach ($newGallery as $file) {
            $gallery[] = $this->images->store($file, self::IMAGE_DIR, 1800, self::IMAGE_MAX_BYTES, 84);
        }

        $content = array_replace($content, [
            'headline' => trim($data['headline']),
            'tagline' => trim((string) ($data['tagline'] ?? '')) ?: null,
            'story' => trim((string) ($data['story'] ?? '')) ?: null,
            'stats' => $stats,
            'features' => $features,
            'gallery' => $gallery,
            'show_cta' => filter_var($input['show_cta'] ?? false, FILTER_VALIDATE_BOOL),
        ]);

        $this->persist($page, $content, $input, $data);
        foreach (array_filter($remove) as $path) {
            $this->images->delete($path);
            $this->variants->forget($path);
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
