<?php

namespace App\Domain\Store\Orders;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\CurrentActor;
use App\Domain\Support\Money;
use App\Models\Store\StoreLegalDocument;
use App\Models\Store\StoreSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Ön Bilgilendirme Formu ve Mesafeli Satış Sözleşmesi: sürümlü şablonlar, sipariş anında doldurulur.
 * Siparişte onaylanan metin (doldurulmuş hâliyle) ve sürüm numarası saklanır.
 */
class LegalDocuments
{
    public const PLACEHOLDERS = [
        'SATICI_UNVAN', 'SATICI_ADRES', 'SATICI_TELEFON', 'SATICI_EPOSTA', 'SATICI_VERGI', 'SATICI_MERSIS',
        'ALICI_AD', 'ALICI_ADRES', 'ALICI_TELEFON', 'ALICI_EPOSTA',
        'URUNLER', 'TESLIMAT_UCRETI', 'KAPIDA_ODEME_BEDELI', 'TOPLAM', 'ODEME_YONTEMI', 'TESLIMAT_YONTEMI', 'TARIH',
        'IADE_SURESI', 'IADE_ADRESI',
    ];

    public function __construct(private AuditLogger $audit, private CurrentActor $actor) {}

    /**
     * Şablonu sipariş bilgileriyle doldurur (Markdown).
     *
     * @param  array{name: string, address: string, phone: string, email: string, lines: list<array{name: string, quantity: int, total: string}>,
     *               shipping_fee: string, cod_fee: string, total: string, payment: string, delivery: string}  $order
     */
    public function fill(StoreLegalDocument $document, array $order): string
    {
        $s = StoreSetting::values();
        $lines = implode("\n", array_map(fn ($l) => "- {$l['name']} × {$l['quantity']} — ".Money::format($l['total']), $order['lines']));
        if (! empty($order['discount'])) {
            $lines .= "\n- İndirim (".str_replace(['*', '_', '#', '[', ']', '<', '>'], ' ', $order['discount']['name']).') — −'.Money::format($order['discount']['amount']);
        }
        $values = [
            'SATICI_UNVAN' => $s['company_title'] ?: config('store.name'),
            'SATICI_ADRES' => $s['address'] ?: '—',
            'SATICI_TELEFON' => $s['phone'] ?: '—',
            'SATICI_EPOSTA' => $s['email'] ?: '—',
            'SATICI_VERGI' => trim(($s['company_tax_office'] ?? '').' '.($s['company_tax_number'] ?? '')) ?: '—',
            'SATICI_MERSIS' => $s['company_mersis'] ?: '—',
            'ALICI_AD' => $order['name'],
            'ALICI_ADRES' => $order['address'],
            'ALICI_TELEFON' => $order['phone'],
            'ALICI_EPOSTA' => $order['email'],
            'URUNLER' => $lines,
            'TESLIMAT_UCRETI' => Money::format($order['shipping_fee']),
            'KAPIDA_ODEME_BEDELI' => Money::format($order['cod_fee']),
            'TOPLAM' => Money::format($order['total']),
            'ODEME_YONTEMI' => $order['payment'],
            'TESLIMAT_YONTEMI' => $order['delivery'],
            'TARIH' => now()->timezone('Europe/Istanbul')->format('d.m.Y'),
            'IADE_SURESI' => (string) max(14, (int) $s['return_days']),
            'IADE_ADRESI' => ($s['return_address'] ?: $s['address']) ?: '—',
        ];

        // Kullanıcı girdisi Markdown biçimlendirmesini bozmasın.
        $values = array_map(fn ($v) => $v === $lines ? $v : str_replace(['*', '_', '#', '[', ']', '<', '>'], ' ', (string) $v), $values);

        return strtr($document->body, array_combine(array_map(fn ($k) => '{'.$k.'}', array_keys($values)), $values));
    }

    public static function html(string $markdown): HtmlString
    {
        return new HtmlString(Str::markdown($markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]));
    }

    /**
     * Ara başlıklara (h2) bağlantı kimliği ekler ve içindekiler listesini döndürür (vitrindeki belge sayfası).
     *
     * @return array{0: HtmlString, 1: list<array{id: string, title: string}>}
     */
    public static function withAnchors(HtmlString $html): array
    {
        $toc = [];
        $out = preg_replace_callback('#<h2>(.*?)</h2>#s', function ($m) use (&$toc) {
            $title = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8'));
            $id = 'b-'.(count($toc) + 1).'-'.Str::slug($title);
            $toc[] = ['id' => $id, 'title' => $title];

            return '<h2 id="'.$id.'">'.$m[1].'</h2>';
        }, (string) $html);

        return [new HtmlString($out), $toc];
    }

    /** Yeni sürüm kaydeder (eski sürümler ve onlara verilmiş onaylar korunur). */
    public function publish(string $key, string $title, string $body): StoreLegalDocument
    {
        return DB::transaction(function () use ($key, $title, $body) {
            $version = (int) StoreLegalDocument::where('key', $key)->lockForUpdate()->max('version') + 1;
            $actor = $this->actor->get();
            $doc = StoreLegalDocument::create([
                'key' => $key, 'version' => $version, 'title' => trim($title), 'body' => $body,
                'created_by_type' => $actor['type'], 'created_by_id' => $actor['id'],
            ]);
            $this->audit->log('store.legal_published', $doc, [], ['key' => $key, 'version' => $version]);

            return $doc;
        });
    }
}
