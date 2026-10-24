<?php

namespace App\Domain\Store\Content;

use App\Models\Store\StoreSetting;

/** Sosyal medya hesapları (Mağaza Ayarları). Yalnızca doldurulanlar alt bilgide simge olarak görünür. */
final class SocialLinks
{
    /** anahtar => [etiket, örnek adres] */
    public const NETWORKS = [
        'instagram' => ['Instagram', 'https://instagram.com/...'],
        'facebook' => ['Facebook', 'https://facebook.com/...'],
        'x' => ['X (Twitter)', 'https://x.com/...'],
        'youtube' => ['YouTube', 'https://youtube.com/@...'],
        'tiktok' => ['TikTok', 'https://tiktok.com/@...'],
        'linkedin' => ['LinkedIn', 'https://linkedin.com/company/...'],
        'pinterest' => ['Pinterest', 'https://pinterest.com/...'],
    ];

    /** @return list<array{key: string, label: string, url: string}> */
    public static function filled(): array
    {
        $settings = StoreSetting::values();
        $links = [];
        foreach (self::NETWORKS as $key => [$label]) {
            if (filled($settings[$key] ?? null)) {
                $links[] = ['key' => $key, 'label' => $label, 'url' => $settings[$key]];
            }
        }

        return $links;
    }
}
