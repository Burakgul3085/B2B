<?php

namespace App\Domain\Store\Print;

use App\Domain\Audit\AuditLogger;
use App\Domain\Store\Cart\CartSummary;
use App\Domain\Support\Decimal;
use App\Domain\Support\Money;
use App\Models\Store\StoreSetting;
use Illuminate\Validation\ValidationException;

/**
 * Fotokopi ayarları (Mağaza yönetimi → Fotokopi): açık/kapalı, yaprak başı fiyat kademeleri, spiral fiyatı, sınırlar
 * ve saklama süreleri. store_settings.print anahtarında JSON olarak tutulur. Fiyatlar KDV dahildir.
 *
 * Fiyat kademesi basılacak sayfa sayısına göre seçilir (önlü arkalı ve kopya kademeyi değiştirmez); her kademede siyah-beyaz
 * ve renkli yaprak fiyatı vardır. Basılacak sayfa özel teklif eşiğine (quote_threshold) ulaşırsa sipariş verilemez, müşteriye
 * quote_text ve iletişim bilgileri gösterilir; eşik boşsa (null) sınır yoktur. Spiral cilt kopya başına tek sabit fiyattır; zımba yoktur.
 */
class PrintSettings
{
    public const DEFAULTS = [
        'enabled' => false,
        // Makine arızasında bir renk geçici kapatılabilir; en az biri açık kalır.
        'bw_enabled' => true,
        'color_enabled' => true,
        'tiers' => [
            ['from' => 1, 'bw' => '2.00', 'color' => '6.00'],
            ['from' => 101, 'bw' => '1.50', 'color' => '5.00'],
            ['from' => 501, 'bw' => '1.00', 'color' => '4.00'],
        ],
        'a3_enabled' => false,
        'a3_multiplier' => '2.00',
        'spiral_price' => '40.00',
        'quote_threshold' => null,
        'quote_text' => 'Bu kadar sayfalı baskılarda size özel indirimli (iskontolu) fiyat veriyoruz. Lütfen mağazamızla iletişime geçin.',
        'min_total' => '0.00',
        'vat_rate' => '20',
        'max_mb' => 200,
        'max_copies' => 100,
        'unordered_days' => 7,
        'ordered_days' => 30,
        'prep_text' => 'Baskılar 1-2 iş günü içinde hazırlanır.',
    ];

    public function __construct(private AuditLogger $audit) {}

    public function all(): array
    {
        $saved = json_decode((string) StoreSetting::get('print'), true);
        $all = array_replace(self::DEFAULTS, is_array($saved) ? $saved : []);
        // Yüz başı fiyatlı eski kayıtta (bw_duplex, color_duplex) tek yüz fiyatları yaprak fiyatı olarak kullanılır.
        $all['tiers'] = array_map(fn (array $t) => ['from' => (int) $t['from'], 'bw' => (string) $t['bw'], 'color' => (string) $t['color']], $all['tiers']);
        $all['quote_threshold'] = $all['quote_threshold'] === null ? null : (int) $all['quote_threshold'];
        $all['quote_text'] = trim((string) $all['quote_text']) ?: self::DEFAULTS['quote_text'];
        // Yaprak aralıklı spiral tablosu olan eski kayıt: yeni fiyat kaydedilene kadar tablodaki en yüksek fiyat kullanılır.
        if (! isset($saved['spiral_price']) && ! empty($saved['spiral']) && is_array($saved['spiral'])) {
            $all['spiral_price'] = (string) collect($saved['spiral'])->map(fn ($t) => (string) $t['price'])->sort(fn ($a, $b) => bccomp($a, $b, 2))->last();
        }
        $all['spiral_price'] = (string) $all['spiral_price'];
        unset($all['staple'], $all['staple_max_sheets'], $all['spiral']);

        return $all;
    }

    /**
     * Kaydı engellemeyen fiyat uyarıları: üst kademede yaprak fiyatı alt kademeden yüksekse.
     *
     * @return list<string>
     */
    public function warnings(): array
    {
        $warnings = [];
        $tiers = collect($this->get('tiers'))->sortBy('from')->values();
        foreach ($tiers->skip(1) as $i => $tier) {
            foreach (['bw' => 'Siyah-beyaz', 'color' => 'Renkli'] as $key => $label) {
                if (bccomp($tier[$key], $tiers[$i - 1][$key], 2) > 0) {
                    $warnings[] = "{$label}: {$tier['from']} sayfadan başlayan kademenin yaprak fiyatı (".Money::format($tier[$key]).') bir önceki kademeden ('.Money::format($tiers[$i - 1][$key]).') yüksek.';
                }
            }
        }

        return $warnings;
    }

    public function get(string $key): mixed
    {
        return $this->all()[$key];
    }

    public function enabled(): bool
    {
        return (bool) $this->get('enabled');
    }

    /** @return list<string> müşterinin seçebileceği renkler ('bw', 'color'), PrintOptions::COLORS sırasıyla */
    public function colors(): array
    {
        $all = $this->all();

        return array_values(array_filter(array_keys(PrintOptions::COLORS), fn (string $c) => (bool) $all[$c.'_enabled']));
    }

    public function colorEnabled(string $color): bool
    {
        return in_array($color, $this->colors(), true);
    }

    public function a3Enabled(): bool
    {
        return (bool) $this->get('a3_enabled');
    }

    public function maxCopies(): int
    {
        return (int) $this->get('max_copies');
    }

    public function maxBytes(): int
    {
        return (int) $this->get('max_mb') * 1024 * 1024;
    }

    /** Sepetteki baskıların toplamı en düşük tutarın altındaysa müşteriye gösterilecek uyarı. */
    public function minimumNotice(CartSummary $summary): ?string
    {
        $min = (string) $this->get('min_total');

        return $summary->prints !== [] && bccomp($summary->printsSubtotal(), $min, 2) < 0
            ? 'Fotokopi siparişi en az '.Money::format($min).' olmalıdır.' : null;
    }

    /**
     * Yönetim formunu doğrulayıp kaydeder.
     *
     * @throws ValidationException
     */
    public function save(array $input): void
    {
        $errors = [];
        $money = function (mixed $value, string $key) use (&$errors, &$money): ?string {
            $parsed = Decimal::parse($value, 2);
            if ($parsed === null || bccomp($parsed, '0', 2) < 0) {
                $errors[$key] = 'Geçerli bir tutar girin (ör. 1,50).';
            }

            return $parsed;
        };
        $int = function (mixed $value, string $key, int $min, int $max) use (&$errors): int {
            $n = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
            if ($n === false) {
                $errors[$key] = "{$min} ile {$max} arasında bir sayı girin.";
            }

            return (int) $n;
        };

        $tiers = [];
        foreach (array_values((array) ($input['tiers'] ?? [])) as $i => $row) {
            if (blank($row['from'] ?? null)) {
                continue;
            }
            $tier = ['from' => $int($row['from'], "tiers.{$i}.from", 1, 1000000)];
            foreach (['bw', 'color'] as $k) {
                $tier[$k] = $money($row[$k] ?? null, "tiers.{$i}.{$k}");
            }
            $tiers[] = $tier;
        }
        usort($tiers, fn ($a, $b) => $a['from'] <=> $b['from']);
        if (! $tiers || $tiers[0]['from'] !== 1) {
            $errors['tiers'] = 'İlk fiyat kademesi 1. sayfadan başlamalıdır.';
        }
        if (count(array_unique(array_column($tiers, 'from'))) !== count($tiers)) {
            $errors['tiers'] = 'Her kademenin başlangıcı farklı olmalıdır.';
        }

        $threshold = blank($input['quote_threshold'] ?? null) ? null : $int($input['quote_threshold'], 'quote_threshold', 2, 1000000);
        if ($threshold !== null && $tiers && $threshold <= max(array_column($tiers, 'from'))) {
            $errors['quote_threshold'] = 'Özel teklif eşiği son fiyat kademesinin başlangıcından büyük olmalıdır.';
        }

        $bw = filter_var($input['bw_enabled'] ?? true, FILTER_VALIDATE_BOOL);
        $color = filter_var($input['color_enabled'] ?? true, FILTER_VALIDATE_BOOL);
        if (! $bw && ! $color) {
            $errors['color_enabled'] = 'Siyah-beyaz ve renkliden en az biri açık olmalıdır. Fotokopiyi tamamen kapatmak için "Fotokopi siparişi açık" anahtarını kapatın.';
        }

        $data = [
            'enabled' => filter_var($input['enabled'] ?? false, FILTER_VALIDATE_BOOL),
            'bw_enabled' => $bw,
            'color_enabled' => $color,
            'tiers' => $tiers,
            'a3_enabled' => filter_var($input['a3_enabled'] ?? false, FILTER_VALIDATE_BOOL),
            'a3_multiplier' => $money($input['a3_multiplier'] ?? '2', 'a3_multiplier'),
            'spiral_price' => $money($input['spiral_price'] ?? null, 'spiral_price'),
            'quote_threshold' => $threshold,
            'quote_text' => mb_substr(trim((string) ($input['quote_text'] ?? '')), 0, 300) ?: self::DEFAULTS['quote_text'],
            'min_total' => $money($input['min_total'] ?? '0', 'min_total'),
            'vat_rate' => in_array((string) ($input['vat_rate'] ?? '20'), ['0', '1', '10', '20'], true) ? (string) $input['vat_rate'] : '20',
            'max_mb' => $int($input['max_mb'] ?? 200, 'max_mb', 1, 500),
            'max_copies' => $int($input['max_copies'] ?? 100, 'max_copies', 1, 1000),
            'unordered_days' => $int($input['unordered_days'] ?? 7, 'unordered_days', 1, 90),
            'ordered_days' => $int($input['ordered_days'] ?? 30, 'ordered_days', 1, 365),
            'prep_text' => mb_substr(trim((string) ($input['prep_text'] ?? '')), 0, 200),
        ];
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $old = $this->all();
        StoreSetting::put(['print' => json_encode($data)]);
        $this->audit->log('store.print_settings_updated', null,
            ['enabled' => $old['enabled'], 'bw_enabled' => $old['bw_enabled'], 'color_enabled' => $old['color_enabled']],
            ['enabled' => $data['enabled'], 'bw_enabled' => $bw, 'color_enabled' => $color]);
    }
}
