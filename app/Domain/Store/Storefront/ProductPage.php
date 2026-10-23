<?php

namespace App\Domain\Store\Storefront;

use App\Domain\Store\Content\StoreFooter;
use App\Domain\Support\Decimal;
use App\Models\Store\StoreSetting;

/**
 * Ürün sayfasının yan bilgileri: teslimat ve iade özeti, güven maddeleri, WhatsApp numarası.
 * Hepsi ayarlardan gelir; ayar boşsa ilgili satır gösterilmez (uydurma süre veya söz yok).
 */
class ProductPage
{
    /** @return array{free_threshold: ?string, cargo_fee: ?string, cutoff: ?string, local: bool, return_days: int, perks: array, whatsapp: ?string} */
    public static function info(): array
    {
        $fee = Decimal::parse(StoreSetting::get('shipping_cargo_fee'), 2);

        return [
            'free_threshold' => Decimal::parse(StoreSetting::get('shipping_free_threshold'), 2),
            'cargo_fee' => $fee && bccomp($fee, '0', 2) > 0 ? $fee : null,
            'cutoff' => StoreSetting::get('cargo_cutoff'),
            'local' => StoreSetting::flag('local_delivery_enabled'),
            'return_days' => (int) StoreSetting::get('return_days'),
            'perks' => StoreFooter::config()['strip'],
            'whatsapp' => self::whatsapp(StoreSetting::get('whatsapp')),
        ];
    }

    /** WhatsApp bağlantısı için uluslararası biçim (0532… → 90532…). */
    public static function whatsapp(?string $raw): ?string
    {
        $digits = $raw ? preg_replace('/\D/', '', $raw) : '';
        if ($digits === '') {
            return null;
        }

        return str_starts_with($digits, '90') ? $digits : (str_starts_with($digits, '0') ? '9'.$digits : '90'.$digits);
    }
}
