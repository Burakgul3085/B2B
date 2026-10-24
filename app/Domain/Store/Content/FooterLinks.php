<?php

namespace App\Domain\Store\Content;

use App\Domain\Store\Support\PerRequest;
use App\Models\Store\StoreBlogPost;
use App\Models\Store\StoreLegalDocument;
use App\Models\Store\StorePage;

/**
 * Alt bilgi sütunlarında ve Yardım Merkezi kutularında seçilebilen bağlantılar. Her bağlantı bir anahtardır
 * (ör. "about", "legal:kvkk", "faq"); yayında olmayan sayfanın bağlantısı kendiliğinden gizlenir.
 * Anahtar yerine https:// ile başlayan bir adres de yazılabilir.
 */
final class FooterLinks
{
    /** Yönetimde seçim listesi: grup => [anahtar => etiket] */
    public static function options(): array
    {
        $legal = [];
        foreach (StoreLegalDocument::KEYS as $key => $title) {
            $legal['legal:'.$key] = $title;
        }

        return [
            'Kurumsal' => ['about' => 'Hakkımızda', 'contact' => 'İletişim', 'blog' => 'Blog', 'legal' => 'Yasal Haklar ve Sözleşmeler'],
            'Yasal metinler' => $legal + ['cookies' => 'Çerez tercihleri'],
            'Üyelik' => ['register' => 'Üye ol', 'login' => 'Giriş yap', 'password' => 'Şifremi unuttum', 'account' => 'Hesabım', 'orders' => 'Siparişlerim', 'favorites' => 'Favorilerim'],
            'Yardım' => ['help' => 'Yardım Merkezi', 'faq' => 'Sıkça Sorulan Sorular', 'shipping' => 'Sipariş ve Teslimat', 'payment' => 'Ödeme Seçenekleri', 'tracking' => 'Kargo Takip', 'newsletter' => 'E-bülten'],
            'Alışveriş' => ['products' => 'Tüm ürünler', 'deals' => 'İndirimler', 'new' => 'Yeni gelenler', 'brands' => 'Markalar'],
            'Diğer' => ['b2b' => 'Bayi girişi'],
        ];
    }

    public static function label(string $key): ?string
    {
        foreach (self::options() as $group) {
            if (isset($group[$key])) {
                return $group[$key];
            }
        }

        return null;
    }

    public static function isUrl(string $value): bool
    {
        return str_starts_with($value, 'https://') && filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Anahtarın adresi; sayfa yayında değilse null. "cookies" için "#cerez" (düğme olarak çizilir).
     */
    public static function resolve(string $key): ?string
    {
        if (str_starts_with($key, 'legal:')) {
            $doc = substr($key, 6);

            return isset(StoreLegalDocument::KEYS[$doc]) ? route('store.legal', $doc) : null;
        }
        if (isset(HelpPages::PAGES[$key])) {
            return HelpPages::isPublished($key) ? route('store.help.'.$key) : null;
        }

        return match ($key) {
            'about', 'contact' => self::sitePageLive($key) ? route('store.'.$key) : null,
            'blog' => StoreBlogPost::anyLive() ? route('store.blog') : null,
            'register' => route('store.register'),
            'login' => route('store.login'),
            'password' => route('store.password.request'),
            'account' => route('store.account'),
            'orders' => route('store.orders'),
            'favorites' => route('store.favorites'),
            'newsletter' => route('store.home').'#e-bulten',
            'products' => route('store.products'),
            'deals' => route('store.products', ['indirim' => 1]),
            'new' => route('store.products', ['sirala' => 'yeni']),
            'brands' => route('store.brands'),
            'b2b' => null, // adres alt bilgiye dışarıdan verilir (render)
            'cookies' => '#cerez',
            default => null,
        };
    }

    /**
     * Sütun bağlantılarını çizime hazırlar: yayında olmayanlar atılır.
     *
     * @param  list<array{key: string, label?: ?string}>  $links
     * @return list<array{label: string, url: string, cookies: bool, external: bool}>
     */
    public static function render(array $links, ?string $b2bLogin = null): array
    {
        $out = [];
        foreach ($links as $link) {
            $key = (string) ($link['key'] ?? '');
            $isUrl = self::isUrl($key);
            $url = $isUrl ? $key : ($key === 'b2b' && $b2bLogin ? $b2bLogin : self::resolve($key));
            $label = trim((string) ($link['label'] ?? '')) ?: ($isUrl ? null : self::label($key));
            if ($url === null || $label === null) {
                continue;
            }
            $out[] = ['label' => $label, 'url' => $url, 'cookies' => $key === 'cookies', 'external' => $isUrl];
        }

        return $out;
    }

    private static function sitePageLive(string $key): bool
    {
        $live = PerRequest::remember('footer.site_pages', fn () => StorePage::whereIn('key', array_keys(SitePages::PAGES))->where('is_published', true)->pluck('key')->all());

        return in_array($key, $live, true);
    }
}
