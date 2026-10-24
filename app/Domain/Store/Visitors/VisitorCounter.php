<?php

namespace App\Domain\Store\Visitors;

use Illuminate\Support\Facades\DB;

/**
 * Vitrin ziyaret sayacı (mağaza yönetimi ana sayfası). Çerez kullanılmaz, IP saklanmaz: ziyaretçi gün, IP ve tarayıcıdan
 * uygulama anahtarıyla üretilen ve her gün değişen bir özetle tanınır. Bu yüzden "ziyaretçi" günlük tekildir; aylık ve
 * toplam ziyaretçi, günlük tekil ziyaretçilerin toplamıdır. Botlar ve otomatik istemciler sayılmaz.
 */
final class VisitorCounter
{
    public const ONLINE_MINUTES = 5;

    private const TZ = 'Europe/Istanbul';

    private const TABLE = 'store_visitor_days';

    private const BOTS = '/bot|crawl|spider|slurp|scrap|preview|monitor|lighthouse|headless|curl|wget|python|java\/|go-http|okhttp|axios|node|php|powershell|httpclient|facebookexternalhit|whatsapp|telegram/i';

    public function record(string $ip, string $userAgent): void
    {
        if (trim($userAgent) === '' || preg_match(self::BOTS, $userAgent)) {
            return;
        }

        $now = now();
        $day = $now->copy()->tz(self::TZ)->toDateString();
        $visitor = hash_hmac('sha256', "{$day}|{$ip}|{$userAgent}", (string) config('app.key'));

        DB::table(self::TABLE)->upsert(
            [['day' => $day, 'visitor' => $visitor, 'page_views' => 1, 'last_seen_at' => $now]],
            ['day', 'visitor'],
            ['page_views' => DB::raw('page_views + 1'), 'last_seen_at'],
        );
    }

    /**
     * @return array{online: int, today: array{visitors: int, views: int}, yesterday: array{visitors: int, views: int},
     *     month: array{visitors: int, views: int}, total: array{visitors: int, views: int}, since: ?string}
     */
    public function stats(): array
    {
        $today = now(self::TZ);

        return [
            'online' => DB::table(self::TABLE)->where('last_seen_at', '>=', now()->subMinutes(self::ONLINE_MINUTES))->count(),
            'today' => $this->period($today->toDateString(), $today->toDateString()),
            'yesterday' => $this->period($today->copy()->subDay()->toDateString(), $today->copy()->subDay()->toDateString()),
            'month' => $this->period($today->copy()->startOfMonth()->toDateString(), $today->toDateString()),
            'total' => $this->period(null, null),
            'since' => DB::table(self::TABLE)->min('day'),
        ];
    }

    /** @return array{visitors: int, views: int} */
    private function period(?string $from, ?string $to): array
    {
        $row = DB::table(self::TABLE)
            ->when($from, fn ($q) => $q->where('day', '>=', $from))
            ->when($to, fn ($q) => $q->where('day', '<=', $to))
            ->selectRaw('COUNT(*) as visitors, COALESCE(SUM(page_views), 0) as views')
            ->first();

        return ['visitors' => (int) $row->visitors, 'views' => (int) $row->views];
    }
}
