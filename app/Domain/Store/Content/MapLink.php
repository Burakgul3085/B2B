<?php

namespace App\Domain\Store\Content;

use DomainException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Google Haritalar bağlantısından sayfaya gömülecek harita ve "Yol tarifi al" adresini üretir. Kabul edilenler:
 *  - Paylaş → Bağlantıyı kopyala (maps.app.goo.gl/… kısa bağlantısı): sunucu yönlendirmeleri izleyip konumu çıkarır,
 *  - tarayıcı adres çubuğundaki google.com/maps/… adresi,
 *  - Paylaş → Harita yerleştir kodu (<iframe src="https://www.google.com/maps/embed?pb=…">).
 * Güvenlik: yalnızca Google alan adlarına ve https'e gidilir (her yönlendirme adımında denetlenir); dönen içerik
 * sayfaya konmaz, yalnızca adresten koordinat okunur. Harita adresini biz kurarız, kullanıcının yazdığı HTML kullanılmaz.
 */
class MapLink
{
    private const HOSTS = ['maps.app.goo.gl', 'goo.gl', 'google.com', 'www.google.com', 'maps.google.com', 'google.com.tr', 'www.google.com.tr', 'consent.google.com'];

    private const MAX_REDIRECTS = 6;

    /**
     * @return array{embed: string, directions: ?string, lat: ?float, lng: ?float}
     *
     * @throws DomainException
     */
    public function resolve(string $input): array
    {
        $input = trim($input);

        // Harita yerleştir kodu: yalnızca src adresi alınır ve biçimi denetlenir.
        if (str_contains($input, '<iframe')) {
            if (! preg_match('/\ssrc=["\']([^"\']+)["\']/i', $input, $m)) {
                throw new DomainException('Yerleştirme kodunda harita adresi bulunamadı.');
            }
            $src = html_entity_decode($m[1]);
            if (! preg_match('#^https://www\.google\.com/maps/embed\?pb=[A-Za-z0-9!._%:*+,\-]+$#', $src)) {
                throw new DomainException('Bu kod bir Google Haritalar yerleştirme kodu değil. Google Haritalar → Paylaş → Harita yerleştir → HTML\'yi kopyala.');
            }
            [$lat, $lng] = $this->coordinates(urldecode($src)) ?? [null, null];

            return ['embed' => $src, 'directions' => $lat !== null ? self::directions($lat, $lng) : null, 'lat' => $lat, 'lng' => $lng];
        }

        $url = $this->checkedUrl($input);
        $url = $this->follow($url);

        if ($coords = $this->coordinates($url)) {
            [$lat, $lng] = $coords;

            return [
                'embed' => 'https://www.google.com/maps?q='.$lat.','.$lng.'&z=17&hl=tr&output=embed',
                'directions' => self::directions($lat, $lng), 'lat' => $lat, 'lng' => $lng,
            ];
        }
        if ($place = $this->placeName($url)) {
            return [
                'embed' => 'https://www.google.com/maps?q='.rawurlencode($place).'&z=17&hl=tr&output=embed',
                'directions' => 'https://www.google.com/maps/dir/?api=1&destination='.rawurlencode($place), 'lat' => null, 'lng' => null,
            ];
        }

        throw new DomainException('Bağlantıdan konum okunamadı. Google Haritalar\'da dükkanı açıp Paylaş → Harita yerleştir kodunu yapıştırın.');
    }

    public static function directions(float $lat, float $lng): string
    {
        return 'https://www.google.com/maps/dir/?api=1&destination='.$lat.','.$lng;
    }

    /** Adres yazısıyla yol tarifi (harita koordinatı yoksa). */
    public static function directionsToAddress(string $address): string
    {
        return 'https://www.google.com/maps/dir/?api=1&destination='.rawurlencode($address);
    }

    /** @throws DomainException */
    private function checkedUrl(string $url): string
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        if (($parts['scheme'] ?? '') !== 'https' || ! in_array($host, self::HOSTS, true)) {
            throw new DomainException('Google Haritalar bağlantısı girin (https://maps.app.goo.gl/… veya https://www.google.com/maps/…) ya da harita yerleştirme kodunu yapıştırın.');
        }
        if ($host === 'goo.gl' && ! str_starts_with($parts['path'] ?? '', '/maps')) {
            throw new DomainException('Bu bir Google Haritalar bağlantısı değil.');
        }

        return $url;
    }

    /** Kısa bağlantının yönlendirmelerini izler; her adımda alan adı denetlenir. Çerez onayı sayfası atlanır. */
    private function follow(string $url): string
    {
        for ($i = 0; $i < self::MAX_REDIRECTS; $i++) {
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            if ($host === 'consent.google.com') {
                parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
                $url = $this->checkedUrl((string) ($query['continue'] ?? ''));

                continue;
            }
            if ($this->coordinates($url) || ! in_array($host, ['maps.app.goo.gl', 'goo.gl'], true)) {
                return $url;
            }
            try {
                $response = Http::withoutRedirecting()->timeout(8)->withHeaders(['Accept-Language' => 'tr'])->get($url);
            } catch (Throwable) {
                throw new DomainException('Google Haritalar bağlantısı açılamadı. Bağlantıyı kontrol edin veya harita yerleştirme kodunu yapıştırın.');
            }
            $location = $response->header('Location');
            if (! $response->redirect() || $location === '') {
                return $url;
            }
            $url = $this->checkedUrl($location);
        }

        return $url;
    }

    /** @return array{0: float, 1: float}|null */
    private function coordinates(string $url): ?array
    {
        $url = urldecode($url);
        foreach (['/!3d(-?\d{1,2}\.\d+)!4d(-?\d{1,3}\.\d+)/', '/@(-?\d{1,2}\.\d+),(-?\d{1,3}\.\d+)/', '/[?&](?:q|ll|query|destination)=(-?\d{1,2}\.\d+),\s*(-?\d{1,3}\.\d+)/'] as $pattern) {
            if (preg_match($pattern, $url, $m) && abs((float) $m[1]) <= 90 && abs((float) $m[2]) <= 180) {
                return [round((float) $m[1], 7), round((float) $m[2], 7)];
            }
        }

        return null;
    }

    private function placeName(string $url): ?string
    {
        if (preg_match('#/maps/place/([^/@?]+)#', $url, $m)) {
            $name = trim(str_replace('+', ' ', urldecode($m[1])));

            return $name !== '' ? mb_substr($name, 0, 200) : null;
        }
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $q = trim((string) ($query['q'] ?? $query['query'] ?? ''));

        return $q !== '' ? mb_substr($q, 0, 200) : null;
    }
}
