<?php

namespace Tests\Unit;

use App\Domain\Support\LocalTime;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

/** Takvim sınırları Türkiye saatine göredir; zaman damgaları UTC'dir (gece 00:00–03:00 arası UTC'de hâlâ dündür). */
class LocalTimeTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_today_and_day_range_follow_turkey_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-28 22:30:00', 'UTC'));

        $this->assertSame('2026-10-29', LocalTime::today());
        $this->assertSame('2026-10-29 00:00:00', LocalTime::now()->startOfDay()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-28 21:00:00', LocalTime::utc(LocalTime::now()->startOfDay())->format('Y-m-d H:i:s'));

        [$from, $to] = LocalTime::dayRange('2026-10-01', '2026-10-31');
        $this->assertSame(['2026-09-30 21:00:00', '2026-10-31 20:59:59'], [$from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s')]);
        $this->assertSame('DATE_ADD(orders.created_at, INTERVAL 3 HOUR)', LocalTime::sql('orders.created_at'));
    }
}
