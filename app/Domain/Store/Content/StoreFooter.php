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

    public const ICONS = SitePages::ICONS + ['lock' => 'Kilit', 'refresh' => 'İade / değişim', 'cart' => 'Sepet', 'building' => 'Mağaza', 'message-circle' => 'Mesaj'];

    /** Kayan şerit: en fazla madde, renkler ve hızlar (madde başına saniye). */
    public const MAX_MARQUEE = 12;

    public const MAX_COLUMNS = 4;

    public const MAX_LINKS = 10;

    public const MARQUEE_COLORS = ['orange' => 'Turuncu', 'navy' => 'Lacivert', 'green' => 'Yeşil', 'blue' => 'Mavi', 'yellow' => 'Sarı'];

    public const MARQUEE_SPEEDS = ['slow' => ['Yavaş', 9], 'normal' => ['Normal', 6], 'fast' => ['Hızlı', 4]];

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
            // Bağlantı sütunları (en fazla 4, her biri en fazla 10 bağlantı). Bağlantılar FooterLinks anahtarlarıdır.
            'columns' => [
                ['title' => 'Kurumsal', 'links' => self::links(['about', 'contact', 'blog', 'legal', 'legal:kvkk', 'legal:iptal-iade'])],
                ['title' => 'Üyelik', 'links' => self::links(['register', 'login', 'password', 'account', 'orders', 'favorites'])],
                ['title' => 'Yardım', 'links' => self::links(['help', 'faq', 'shipping', 'payment', 'tracking', 'cookies', 'b2b'])],
            ],
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
            // Kayan şerit (en üstte, sağdan sola kayar). Boşsa maddeler ayarlardan üretilir.
            'marquee_enabled' => true,
            'marquee_color' => 'orange',
            'marquee_speed' => 'normal',
            'marquee_items' => [],
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

    /**
     * Kayan şeridin maddeleri: yönetimde yazılanlar; yoksa ayarlardan (ücretsiz kargo, taksit, mağaza teslimatı, güvenli ödeme).
     *
     * @return list<array{icon: string, text: string, badge: ?string}>
     */
    public static function marquee(array $config): array
    {
        return $config['marquee_items'] ?: self::marqueeSuggestions();
    }

    /**
     * Yönetimde madde yazılmamışsa kullanılan ve formu ilk açılışta dolduran maddeler. Yalnızca ayarlardan doğrulanabilen
     * bilgiler yazılır (ücretsiz kargo sınırı, taksit, Eskişehir teslimatı, aynı gün kargo saati, iade süresi, WhatsApp);
     * kuruluş yılı, müşteri memnuniyeti gibi bilgileri mağaza sahibi kendisi ekler.
     *
     * @return list<array{icon: string, text: string, badge: ?string}>
     */
    public static function marqueeSuggestions(): array
    {
        $s = StoreSetting::values();
        $items = [];
        if ($threshold = Decimal::parse($s['shipping_free_threshold'], 2)) {
            $items[] = ['icon' => 'truck', 'text' => Money::format($threshold).' üzeri alışverişlerde', 'badge' => 'Kargo bedava'];
        }
        $items[] = ['icon' => 'lock', 'text' => 'Güvenli alışveriş', 'badge' => '256 bit SSL'];
        if ($s['card_enabled'] === '1') {
            $items[] = ['icon' => 'percent', 'text' => 'Kredi kartına', 'badge' => 'Taksit imkânı'];
        }
        if ($s['local_delivery_enabled'] === '1') {
            $items[] = ['icon' => 'map-pin', 'text' => 'Eskişehir içi', 'badge' => 'Mağaza teslimatı'];
        }
        if ($s['cargo_cutoff']) {
            $items[] = ['icon' => 'clock', 'text' => 'Hafta içi '.$s['cargo_cutoff'].' öncesi siparişlerde', 'badge' => 'Aynı gün kargo'];
        }
        $items[] = ['icon' => 'refresh', 'text' => ((int) $s['return_days'] ?: 14).' gün içinde', 'badge' => 'Kolay iade'];
        $items[] = ['icon' => 'check-circle', 'text' => 'Her siparişte', 'badge' => 'Faturalı satış'];
        if ($s['whatsapp']) {
            $items[] = ['icon' => 'message-circle', 'text' => 'WhatsApp ile', 'badge' => 'Hızlı destek'];
        }
        $items[] = ['icon' => 'package', 'text' => 'Okul ve ofis ihtiyaçlarınız', 'badge' => 'Tek adreste'];

        return $items;
    }

    /** @param  list<string>  $keys */
    private static function links(array $keys): array
    {
        return array_map(fn ($key) => ['key' => $key, 'label' => null], $keys);
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
            // Harita herkese gösterilir (kişisel veri yok; Çerez Politikası'nda not var). Konum İletişim sayfasındaki
            // Google Haritalar bağlantısından, yoksa mağaza adresinden.
            'directions' => $config['show_map'] ? SitePages::directionsUrl($content ?? []) : null,
            'map_embed' => $config['show_map'] ? ($content['map_embed'] ?? null) : null,
            'contact_url' => $contact?->is_published ? route('store.contact') : null,
            'topbar' => $config['topbar_enabled'] ? self::topbar($config) : [],
            'marquee' => $config['marquee_enabled'] ? self::marquee($config) : [],
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
            'store_title' => ['nullable', 'string', 'max:30'],
            'columns' => ['nullable', 'array', 'max:'.self::MAX_COLUMNS],
            'columns.*.title' => ['nullable', 'string', 'max:30'],
            'columns.*.links' => ['nullable', 'array', 'max:'.self::MAX_LINKS],
            'columns.*.links.*.key' => ['nullable', 'string', 'max:255'],
            'columns.*.links.*.url' => ['nullable', 'string', 'max:255'],
            'columns.*.links.*.label' => ['nullable', 'string', 'max:40'],
            'bottom_note' => ['nullable', 'string', 'max:120'],
            'manager_name' => ['nullable', 'string', 'max:60'],
            'topbar_items' => ['nullable', 'array', 'max:3'],
            'topbar_items.*' => ['nullable', 'string', 'max:50'],
            'marquee_color' => ['nullable', 'in:'.implode(',', array_keys(self::MARQUEE_COLORS))],
            'marquee_speed' => ['nullable', 'in:'.implode(',', array_keys(self::MARQUEE_SPEEDS))],
            'marquee_items' => ['nullable', 'array', 'max:'.self::MAX_MARQUEE],
            'marquee_items.*.icon' => ['nullable', 'in:'.implode(',', array_keys(self::ICONS))],
            'marquee_items.*.text' => ['nullable', 'string', 'max:60'],
            'marquee_items.*.badge' => ['nullable', 'string', 'max:30'],
        ], [], [
            'footer_about' => 'tanıtım metni', 'tagline' => 'logo altı yazı', 'columns.*.title' => 'sütun başlığı', 'columns.*.links.*.label' => 'bağlantı adı',
            'store_title' => 'mağaza başlığı', 'bottom_note' => 'alt not', 'manager_name' => 'sayfa yöneticisi',
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

        $marquee = [];
        foreach (array_values($data['marquee_items'] ?? []) as $i => $row) {
            [$text, $badge] = [trim((string) ($row['text'] ?? '')), trim((string) ($row['badge'] ?? ''))];
            if ($text === '' && $badge === '') {
                continue;
            }
            $marquee[] = ['icon' => $row['icon'] ?? 'check-circle', 'text' => $text, 'badge' => $badge ?: null];
        }

        $columns = null;
        if (array_key_exists('columns', $data)) {
            $columns = [];
            foreach (array_values($data['columns'] ?? []) as $c => $column) {
                $links = [];
                foreach (array_values($column['links'] ?? []) as $l => $row) {
                    $key = trim((string) ($row['key'] ?? ''));
                    if ($key === 'url') {
                        $key = trim((string) ($row['url'] ?? ''));
                        if (! FooterLinks::isUrl($key)) {
                            throw ValidationException::withMessages(["columns.{$c}.links.{$l}.url" => 'Özel bağlantı https:// ile başlayan geçerli bir adres olmalı.']);
                        }
                        if (trim((string) ($row['label'] ?? '')) === '') {
                            throw ValidationException::withMessages(["columns.{$c}.links.{$l}.label" => 'Özel bağlantıya bir ad yazın.']);
                        }
                    } elseif ($key !== '' && FooterLinks::label($key) === null) {
                        throw ValidationException::withMessages(["columns.{$c}.links.{$l}.key" => 'Listeden bir sayfa seçin.']);
                    }
                    if ($key !== '') {
                        $links[] = ['key' => $key, 'label' => trim((string) ($row['label'] ?? '')) ?: null];
                    }
                }
                $title = trim((string) ($column['title'] ?? ''));
                if ($title !== '' || $links) {
                    $columns[] = ['title' => $title ?: 'Bağlantılar', 'links' => $links];
                }
            }
        }

        $flag = fn (string $key) => filter_var($input[$key] ?? false, FILTER_VALIDATE_BOOL);
        $config = [
            'tagline' => trim((string) ($data['tagline'] ?? '')) ?: null,
            'strip_enabled' => $flag('strip_enabled'),
            'strip' => $strip,
            'columns' => $columns ?? self::config()['columns'],
            'store_title' => trim((string) ($data['store_title'] ?? '')) ?: 'Mağazamız',
            'show_map' => $flag('show_map'),
            'show_hours' => $flag('show_hours'),
            'show_b2b' => $flag('show_b2b'),
            'whatsapp_button' => $flag('whatsapp_button'),
            'bottom_note' => trim((string) ($data['bottom_note'] ?? '')) ?: null,
            'manager_name' => trim((string) ($data['manager_name'] ?? '')) ?: null,
            'topbar_enabled' => $flag('topbar_enabled'),
            'topbar_items' => array_values(array_filter(array_map(fn ($t) => trim((string) $t), $data['topbar_items'] ?? []))),
            'marquee_enabled' => $flag('marquee_enabled'),
            'marquee_color' => $data['marquee_color'] ?? 'orange',
            'marquee_speed' => $data['marquee_speed'] ?? 'normal',
            'marquee_items' => $marquee,
        ];

        StoreSetting::put(['footer' => json_encode($config, JSON_UNESCAPED_UNICODE), 'footer_about' => trim((string) ($data['footer_about'] ?? '')) ?: null]);
        $this->audit->log('store.footer_updated');
    }
}
