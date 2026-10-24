<?php

namespace App\Domain\Store\Storefront;

use App\Http\Controllers\Store\Front\Concerns\CurrentCart;
use App\Http\Middleware\EnsureStoreOpen;

/**
 * Mağazanın kullandığı çerezler, çerez tercihleri penceresinde kategoriye göre listelenir. Yalnızca gerçekten
 * kullanılan çerezler yazılır; analitik ve pazarlama kategorileri şu an boştur — ileride bir betik eklenirse yalnızca
 * ziyaretçi o kategoriye izin verdiyse yüklenmelidir (window.storeConsentAllows('analytics')).
 */
final class CookieCatalog
{
    /**
     * @return array<string, array{title: string, icon: string, text: string, required: bool, cookies: list<array{0: string, 1: string, 2: string}>}>
     */
    public static function categories(): array
    {
        $session = (int) config('session.lifetime');

        return [
            'essential' => [
                'title' => 'Zorunlu çerezler', 'icon' => 'lock', 'required' => true,
                'text' => 'Sitenin güvenli çalışması için gereklidir: oturum, sepet, güvenlik ve çerez tercihinizin hatırlanması. Kapatılamaz.',
                'cookies' => [
                    [config('session.cookie'), 'Oturumunuzu ve güvenliğinizi sağlar.', "{$session} dakika"],
                    ['XSRF-TOKEN', 'Formları sahte isteklere karşı korur.', "{$session} dakika"],
                    [CurrentCart::COOKIE, 'Sepetinizi hatırlar.', '30 gün'],
                    ['remember_customer_*', 'Yalnızca "Beni hatırla" seçerseniz oturumunuzu açık tutar.', '5 yıla kadar'],
                    ['magaza_cerez', 'Bu penceredeki tercihinizi saklar.', '1 yıl'],
                    [EnsureStoreOpen::PREVIEW_COOKIE, 'Yalnızca mağaza yöneticisinin önizlemesinde kullanılır.', '2 saat'],
                ],
            ],
            'functional' => [
                'title' => 'İşlevsel çerezler', 'icon' => 'settings', 'required' => false,
                'text' => 'Seçtiğiniz koyu / açık temayı sonraki ziyaretlerinizde hatırlar. Kapatırsanız tema yalnızca tarayıcı açıkken hatırlanır.',
                'cookies' => [['tema', 'Koyu veya açık tema tercihiniz.', '1 yıl']],
            ],
            'analytics' => [
                'title' => 'Analitik çerezler', 'icon' => 'grid', 'required' => false,
                'text' => 'Sitenin nasıl kullanıldığını anonim olarak ölçmemize yardımcı olur. Şu anda analitik çerez kullanmıyoruz; ileride kullanırsak yalnızca bu izinle etkinleşir.',
                'cookies' => [],
            ],
            'marketing' => [
                'title' => 'Pazarlama çerezleri', 'icon' => 'gift', 'required' => false,
                'text' => 'İlginizi çekebilecek kampanyaları başka sitelerde göstermek için kullanılır. Şu anda pazarlama çerezi kullanmıyoruz; ileride kullanırsak yalnızca bu izinle etkinleşir.',
                'cookies' => [],
            ],
        ];
    }
}
