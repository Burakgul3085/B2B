<?php

namespace App\Domain\Store\Content;

use App\Domain\Audit\AuditLogger;
use App\Domain\Store\Support\PerRequest;
use App\Models\Store\StorePage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Yardım sayfaları (Vitrin → Yardım Sayfaları): Yardım Merkezi, SSS, Sipariş ve Teslimat, Ödeme Seçenekleri,
 * Kargo Takip, Yasal Haklar ve Sözleşmeler. Her sayfanın başlığı, giriş metni ve gövdesi yönetimden düzenlenir;
 * kayıt yoksa varsayılan içerikle yayındadır. Teslimat ve ödeme sayfalarındaki tutarlar ve yöntemler ayarlardan
 * canlı gelir (tek yerden yönetilir).
 */
class HelpPages
{
    /** anahtar => [başlık, adres, açıklama] */
    public const PAGES = [
        'help' => ['Yardım Merkezi', 'yardim', 'Yardım konularına giden kutucuklar.'],
        'faq' => ['Sıkça Sorulan Sorular', 'sss', 'Soru ve cevaplar; gruplara ayrılır.'],
        'shipping' => ['Sipariş ve Teslimat', 'siparis-ve-teslimat', 'Kargo ücreti, ücretsiz kargo sınırı ve teslimat seçenekleri ayarlardan otomatik yazılır.'],
        'payment' => ['Ödeme Seçenekleri', 'odeme-secenekleri', 'Kart, taksit, havale ve kapıda ödeme ayarlardan otomatik yazılır.'],
        'tracking' => ['Kargo Takip', 'kargo-takip', 'Sipariş numarası ve e-postayla sipariş durumu.'],
        'legal' => ['Yasal Haklar ve Sözleşmeler', 'yasal-haklar', 'Tüm yasal metinlerin listesi.'],
    ];

    public const MAX_TILES = 9;

    public const MAX_FAQ = 60;

    public function __construct(private AuditLogger $audit) {}

    public static function path(string $key): string
    {
        return self::PAGES[$key][1];
    }

    /** @return array{published: bool, content: array} */
    public static function get(string $key): array
    {
        abort_unless(isset(self::PAGES[$key]), 404);
        $page = PerRequest::remember('help.pages', fn () => StorePage::whereIn('key', array_keys(self::PAGES))->get()->keyBy('key'))[$key] ?? null;

        return [
            'published' => $page ? $page->is_published : true,
            'content' => array_replace(self::defaults($key), $page?->content ?? []),
        ];
    }

    public static function isPublished(string $key): bool
    {
        return self::get($key)['published'];
    }

    public static function defaults(string $key): array
    {
        $base = ['title' => self::PAGES[$key][0], 'intro' => null, 'body' => null];

        return $base + match ($key) {
            'help' => [
                'intro' => 'Size nasıl yardımcı olabiliriz? Aşağıdaki konulardan birini seçin ya da bize ulaşın.',
                'tiles' => [
                    ['icon' => 'message-circle', 'title' => 'Sıkça sorulan sorular', 'text' => 'Sipariş, kargo, ödeme ve iade hakkında merak edilenler.', 'link' => 'faq'],
                    ['icon' => 'truck', 'title' => 'Sipariş ve teslimat', 'text' => 'Kargo ücreti, teslim süresi ve Eskişehir teslimatı.', 'link' => 'shipping'],
                    ['icon' => 'percent', 'title' => 'Ödeme seçenekleri', 'text' => 'Kart, taksit, havale ve kapıda ödeme.', 'link' => 'payment'],
                    ['icon' => 'package', 'title' => 'Kargo takip', 'text' => 'Siparişinizin nerede olduğunu öğrenin.', 'link' => 'tracking'],
                    ['icon' => 'refresh', 'title' => 'İptal ve iade', 'text' => 'İade koşulları ve iade talebi.', 'link' => 'legal:iptal-iade'],
                    ['icon' => 'phone', 'title' => 'Bize ulaşın', 'text' => 'Telefon, WhatsApp ve iletişim formu.', 'link' => 'contact'],
                ],
            ],
            'faq' => [
                'intro' => 'Aradığınız cevabı bulamazsanız bize ulaşın; en kısa sürede dönüş yapalım.',
                'items' => [
                    ['group' => 'Sipariş', 'question' => 'Siparişimi nasıl takip edebilirim?', 'answer' => 'Siparişiniz kargoya verildiğinde takip numarası e-postayla gönderilir. **Kargo Takip** sayfasından sipariş numaranız ve e-posta adresinizle de sorgulayabilirsiniz.'],
                    ['group' => 'Sipariş', 'question' => 'Üye olmadan sipariş verebilir miyim?', 'answer' => 'Evet. Ödeme adımında bilgilerinizi girerek üye olmadan sipariş verebilirsiniz.'],
                    ['group' => 'Teslimat', 'question' => 'Kargo ücreti ne kadar?', 'answer' => 'Güncel kargo ücreti ve ücretsiz kargo sınırı **Sipariş ve Teslimat** sayfasında yazar.'],
                    ['group' => 'Ödeme', 'question' => 'Taksitle ödeyebilir miyim?', 'answer' => 'Evet. Anlaşmalı kredi kartlarıyla ödeme sayfasında taksit seçeneklerini görebilirsiniz.'],
                    ['group' => 'İade', 'question' => 'Ürünü iade edebilir miyim?', 'answer' => 'Teslim aldığınız ürünü yasal süre içinde, kullanılmamış ve yeniden satılabilir durumda iade edebilirsiniz. İade talebini sipariş sayfanızdan açabilirsiniz.'],
                ],
            ],
            'shipping' => ['intro' => 'Siparişleriniz özenle paketlenir ve en kısa sürede yola çıkar.', 'show_rates' => true],
            'payment' => ['intro' => 'Güvenli ödeme altyapımızla dilediğiniz yöntemle ödeyin.', 'show_methods' => true],
            'tracking' => ['intro' => 'Sipariş numaranızı ve siparişte kullandığınız e-posta adresini yazın.'],
            'legal' => ['intro' => 'Alışverişinizle ilgili tüm yasal metinler. Her metnin güncel sürümü aşağıdadır.'],
        };
    }

    /** @throws ValidationException */
    public function save(string $key, array $input): void
    {
        abort_unless(isset(self::PAGES[$key]), 404);
        $rules = [
            'title' => ['required', 'string', 'max:80'],
            'intro' => ['nullable', 'string', 'max:400'],
            'body' => ['nullable', 'string', 'max:20000'],
        ];
        $rules += match ($key) {
            'help' => [
                'tiles' => ['nullable', 'array', 'max:'.self::MAX_TILES],
                'tiles.*.icon' => ['nullable', Rule::in(array_keys(StoreFooter::ICONS))],
                'tiles.*.title' => ['nullable', 'string', 'max:60'],
                'tiles.*.text' => ['nullable', 'string', 'max:140'],
                'tiles.*.link' => ['nullable', 'string', 'max:255'],
            ],
            'faq' => [
                'items' => ['nullable', 'array', 'max:'.self::MAX_FAQ],
                'items.*.group' => ['nullable', 'string', 'max:40'],
                'items.*.question' => ['nullable', 'string', 'max:200'],
                'items.*.answer' => ['nullable', 'string', 'max:3000'],
            ],
            default => [],
        };
        $data = Validator::make($input, $rules, [], [
            'title' => 'başlık', 'intro' => 'giriş metni', 'body' => 'metin',
            'tiles.*.title' => 'kutu başlığı', 'items.*.question' => 'soru', 'items.*.answer' => 'cevap',
        ])->validate();

        $content = [
            'title' => trim($data['title']),
            'intro' => trim((string) ($data['intro'] ?? '')) ?: null,
            'body' => trim((string) ($data['body'] ?? '')) ?: null,
        ];
        $flag = fn (string $name) => filter_var($input[$name] ?? false, FILTER_VALIDATE_BOOL);

        if ($key === 'help') {
            $tiles = [];
            foreach (array_values($data['tiles'] ?? []) as $i => $tile) {
                $title = trim((string) ($tile['title'] ?? ''));
                if ($title === '') {
                    continue;
                }
                $link = trim((string) ($tile['link'] ?? ''));
                if ($link !== '' && FooterLinks::resolve($link) === null && ! FooterLinks::isUrl($link)) {
                    throw ValidationException::withMessages(["tiles.{$i}.link" => 'Listeden bir sayfa seçin veya https:// ile başlayan bir adres yazın.']);
                }
                $tiles[] = ['icon' => $tile['icon'] ?? 'check-circle', 'title' => $title, 'text' => trim((string) ($tile['text'] ?? '')) ?: null, 'link' => $link ?: null];
            }
            $content['tiles'] = $tiles;
        }
        if ($key === 'faq') {
            $items = [];
            foreach (array_values($data['items'] ?? []) as $i => $item) {
                [$q, $a] = [trim((string) ($item['question'] ?? '')), trim((string) ($item['answer'] ?? ''))];
                if ($q === '' && $a === '') {
                    continue;
                }
                if ($q === '' || $a === '') {
                    throw ValidationException::withMessages(["items.{$i}" => 'Her satırda hem soru hem cevap olmalı.']);
                }
                $items[] = ['group' => trim((string) ($item['group'] ?? '')) ?: 'Genel', 'question' => $q, 'answer' => $a];
            }
            $content['items'] = $items;
        }
        if ($key === 'shipping') {
            $content['show_rates'] = $flag('show_rates');
        }
        if ($key === 'payment') {
            $content['show_methods'] = $flag('show_methods');
        }

        StorePage::updateOrCreate(['key' => $key], ['content' => $content, 'is_published' => $flag('is_published')]);
        $this->audit->log('store.help_page_saved', null, [], ['key' => $key]);
    }
}
