<?php

namespace App\Domain\Store\Content;

use App\Domain\Audit\AuditLogger;
use App\Domain\Support\Decimal;
use App\Domain\Support\Money;
use App\Models\Store\StorePage;
use App\Models\Store\StoreSetting;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Mağaza üst şeridi ve alt bilgisi (Vitrin → Üst ve Alt Bilgi). Ayarlar store_settings.footer içinde JSON olarak tutulur; iletişim bilgileri
 * Mağaza Ayarları'ndan, kategoriler katalogdan, sayfalar SitePages'ten, çalışma saatleri İletişim sayfasından gelir.
 */
class StoreFooter
{
    public const MAX_STRIP = 4;

    public const ICONS = SitePages::ICONS + ['lock' => 'Kilit', 'refresh' => 'İade / değişim'];

    public function __construct(private AuditLogger $audit) {}

    public static function defaults(): array
    {
        return [
            'tagline' => 'Kırtasiye & Ofis',
            'strip_enabled' => true,
            'strip' => [
                ['icon' => 'truck', 'title' => 'Hızlı teslimat', 'text' => 'Türkiye geneli kargo'],
                ['icon' => 'lock', 'title' => 'Güvenli ödeme', 'text' => '256-bit SSL · iyzico güvencesi'],
                ['icon' => 'refresh', 'title' => 'Kolay iade', 'text' => '14 gün içinde iade hakkı'],
            ],
            'shop_title' => 'Alışveriş',
            'category_count' => 6,
            'info_title' => 'Bilgi',
            'store_title' => 'Mağazamız',
            'show_map' => true,
            'show_hours' => true,
            'show_b2b' => true,
            'whatsapp_button' => true,
            'bottom_note' => null,
            'manager_name' => 'Onur Birbilen',   // alt bilgide "Sayfa yöneticisi"
            // Üst şerit (her sayfanın en üstü). Boş maddeler ayarlardan kendiliğinden üretilir.
            'topbar_enabled' => true,
            'topbar_items' => [],
        ];
    }

    /**
     * Üst şeritteki kısa maddeler: yönetimde yazılanlar; yoksa ayarlardan (ücretsiz kargo sınırı, Eskişehir teslimatı).
     *
     * @return list<array{0: string, 1: string}> [simge, metin]
     */
    public static function topbar(array $config): array
    {
        $icons = ['truck', 'map-pin', 'lock'];
        if ($config['topbar_items']) {
            return array_map(fn ($text, $i) => [$icons[$i % 3], $text], $config['topbar_items'], array_keys($config['topbar_items']));
        }
        $items = [];
        if ($threshold = Decimal::parse(StoreSetting::get('shipping_free_threshold'), 2)) {
            $items[] = ['truck', Money::format($threshold).' üzeri ücretsiz kargo'];
        }
        if (StoreSetting::flag('local_delivery_enabled')) {
            $items[] = ['map-pin', 'Eskişehir içi mağaza teslimatı'];
        }
        $items[] = ['lock', 'Güvenli alışveriş'];

        return $items;
    }

    public static function config(): array
    {
        $saved = json_decode((string) StoreSetting::get('footer'), true);

        return array_replace(self::defaults(), is_array($saved) ? $saved : []);
    }

    /** Alt bilgi görünümünün ihtiyaç duyduğu her şey (mağaza düzeni her sayfada çağırır). */
    public static function viewData(): array
    {
        $config = self::config();
        $contact = StorePage::firstWhere('key', 'contact');
        $content = $contact ? array_replace(SitePages::defaults('contact'), $contact->content ?? []) : null;

        return $config + [
            // Saatler yalnızca İletişim sayfası kaydedilmişse gösterilir (varsayılan saatler gerçek olmayabilir).
            'hours' => $config['show_hours'] && $content ? OpeningHours::grouped($content['hours']) : [],
            'directions' => $config['show_map'] && StoreSetting::get('address') ? SitePages::directionsUrl($content ?? []) : null,
            'contact_url' => $contact?->is_published ? route('store.contact') : null,
            'topbar' => $config['topbar_enabled'] ? self::topbar($config) : [],
        ];
    }

    /** @throws ValidationException */
    public function save(array $input): void
    {
        $data = Validator::make($input, [
            'footer_about' => ['nullable', 'string', 'max:600'],
            'tagline' => ['nullable', 'string', 'max:40'],
            'strip' => ['nullable', 'array', 'max:'.self::MAX_STRIP],
            'strip.*.icon' => ['nullable', 'in:'.implode(',', array_keys(self::ICONS))],
            'strip.*.title' => ['nullable', 'string', 'max:40'],
            'strip.*.text' => ['nullable', 'string', 'max:60'],
            'shop_title' => ['required', 'string', 'max:30'],
            'category_count' => ['required', 'integer', 'min:0', 'max:10'],
            'info_title' => ['required', 'string', 'max:30'],
            'store_title' => ['required', 'string', 'max:30'],
            'bottom_note' => ['nullable', 'string', 'max:120'],
            'manager_name' => ['nullable', 'string', 'max:60'],
            'topbar_items' => ['nullable', 'array', 'max:3'],
            'topbar_items.*' => ['nullable', 'string', 'max:50'],
        ], [], [
            'footer_about' => 'tanıtım metni', 'tagline' => 'logo altı yazı', 'shop_title' => 'alışveriş başlığı', 'category_count' => 'kategori sayısı',
            'info_title' => 'bilgi başlığı', 'store_title' => 'mağaza başlığı', 'bottom_note' => 'alt not', 'manager_name' => 'sayfa yöneticisi',
        ])->validate();

        $strip = [];
        foreach (array_values($data['strip'] ?? []) as $i => $row) {
            [$title, $text] = [trim((string) ($row['title'] ?? '')), trim((string) ($row['text'] ?? ''))];
            if ($title === '' && $text === '') {
                continue;
            }
            if ($title === '') {
                throw ValidationException::withMessages(["strip.{$i}" => 'Şeritteki maddenin başlığını yazın.']);
            }
            $strip[] = ['icon' => $row['icon'] ?? 'check-circle', 'title' => $title, 'text' => $text];
        }

        $flag = fn (string $key) => filter_var($input[$key] ?? false, FILTER_VALIDATE_BOOL);
        $config = [
            'tagline' => trim((string) ($data['tagline'] ?? '')) ?: null,
            'strip_enabled' => $flag('strip_enabled'),
            'strip' => $strip,
            'shop_title' => trim($data['shop_title']),
            'category_count' => (int) $data['category_count'],
            'info_title' => trim($data['info_title']),
            'store_title' => trim($data['store_title']),
            'show_map' => $flag('show_map'),
            'show_hours' => $flag('show_hours'),
            'show_b2b' => $flag('show_b2b'),
            'whatsapp_button' => $flag('whatsapp_button'),
            'bottom_note' => trim((string) ($data['bottom_note'] ?? '')) ?: null,
            'manager_name' => trim((string) ($data['manager_name'] ?? '')) ?: null,
            'topbar_enabled' => $flag('topbar_enabled'),
            'topbar_items' => array_values(array_filter(array_map(fn ($t) => trim((string) $t), $data['topbar_items'] ?? []))),
        ];

        StoreSetting::put(['footer' => json_encode($config, JSON_UNESCAPED_UNICODE), 'footer_about' => trim((string) ($data['footer_about'] ?? '')) ?: null]);
        $this->audit->log('store.footer_updated');
    }
}
