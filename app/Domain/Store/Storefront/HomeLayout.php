<?php

namespace App\Domain\Store\Storefront;

use App\Domain\Audit\AuditLogger;
use App\Models\Store\StoreSetting;
use Illuminate\Support\Facades\Validator;

/**
 * Ana sayfa blokları (Vitrin → Ana Sayfa): sıra, açık/kapalı, başlık ve alt başlık. Ayar store_settings.home'da JSON.
 * Blokların içeriği her zaman canlı veriden gelir (banner, kategori, kupon, ürün, seçki, marka, Hakkımızda);
 * içeriği olmayan blok sayfada görünmez.
 */
class HomeLayout
{
    /** Varsayılan sıra ve başlıklar. */
    public const BLOCKS = [
        'hero' => ['label' => 'Kampanya alanı (banner)', 'title' => null, 'subtitle' => null],
        'perks' => ['label' => 'Avantaj şeridi', 'title' => null, 'subtitle' => null],
        'categories' => ['label' => 'Kategori kutuları', 'title' => 'Kırtasiyede aradığınız her şey', 'subtitle' => 'İhtiyacınıza en yakın rafı keşfedin.'],
        'coupons' => ['label' => 'Kampanya ve kuponlar', 'title' => 'Sepetiniz için hazır avantajlar', 'subtitle' => 'Kodu kopyalayın, sepette kullanın.'],
        'showcase' => ['label' => 'Ürün vitrini (sekmeli)', 'title' => 'Sizin için seçtiklerimiz', 'subtitle' => 'Mağazamızdan sizin için seçtiğimiz ürünler.'],
        'recent' => ['label' => 'Son bakılan ürünler (ziyaretçiye özel)', 'title' => 'Kaldığınız yerden devam edin', 'subtitle' => 'Son baktığınız ürünler.'],
        'collections' => ['label' => 'Seçkiler', 'title' => 'Bugün neye ihtiyacınız var?', 'subtitle' => 'Temaya göre hazırladığımız ürün seçkileri.'],
        'brands' => ['label' => 'Markalar', 'title' => 'Güvendiğiniz markalar', 'subtitle' => 'Orijinal ürünler, yetkili tedarikçilerden.'],
        'blog' => ['label' => 'Blogdan son yazılar', 'title' => 'Blogdan', 'subtitle' => 'Okul, ofis ve sanat üzerine öneriler.'],
        'about' => ['label' => 'Hakkımızda özeti', 'title' => null, 'subtitle' => null],
    ];

    public function __construct(private AuditLogger $audit) {}

    /** @return list<array{key: string, label: string, enabled: bool, title: ?string, subtitle: ?string}> sıralı */
    public static function blocks(): array
    {
        $saved = json_decode((string) StoreSetting::get('home'), true);
        $saved = is_array($saved) ? $saved : [];
        $byKey = collect($saved)->keyBy('key');

        $ordered = [];
        foreach ($saved as $row) {
            if (isset(self::BLOCKS[$row['key'] ?? ''])) {
                $ordered[] = $row['key'];
            }
        }
        foreach (array_keys(self::BLOCKS) as $key) {
            if (! in_array($key, $ordered, true)) {
                $ordered[] = $key; // yeni eklenen bloklar sona, açık olarak
            }
        }

        return array_map(fn ($key) => [
            'key' => $key,
            'label' => self::BLOCKS[$key]['label'],
            'enabled' => (bool) ($byKey[$key]['enabled'] ?? true),
            'title' => ($byKey[$key]['title'] ?? null) ?: self::BLOCKS[$key]['title'],
            'subtitle' => ($byKey[$key]['subtitle'] ?? null) ?: self::BLOCKS[$key]['subtitle'],
        ], $ordered);
    }

    /** @param  array<string, array{enabled?: mixed, title?: ?string, subtitle?: ?string, sort?: mixed}>  $input */
    public function save(array $input): void
    {
        Validator::make(['blocks' => $input], [
            'blocks' => ['array'],
            'blocks.*.title' => ['nullable', 'string', 'max:80'],
            'blocks.*.subtitle' => ['nullable', 'string', 'max:160'],
            'blocks.*.sort' => ['nullable', 'integer', 'min:0', 'max:99'],
        ], [], ['blocks.*.title' => 'başlık', 'blocks.*.subtitle' => 'alt başlık'])->validate();

        $rows = [];
        foreach (array_keys(self::BLOCKS) as $i => $key) {
            $row = (array) ($input[$key] ?? []);
            $rows[] = [
                'key' => $key,
                'enabled' => filter_var($row['enabled'] ?? false, FILTER_VALIDATE_BOOL),
                'title' => trim((string) ($row['title'] ?? '')) ?: null,
                'subtitle' => trim((string) ($row['subtitle'] ?? '')) ?: null,
                'sort' => isset($row['sort']) && $row['sort'] !== '' ? (int) $row['sort'] : $i,
            ];
        }
        usort($rows, fn ($a, $b) => $a['sort'] <=> $b['sort']);

        StoreSetting::put(['home' => json_encode(array_map(fn ($r) => array_diff_key($r, ['sort' => 1]), $rows), JSON_UNESCAPED_UNICODE)]);
        $this->audit->log('store.home_layout_updated');
    }
}
