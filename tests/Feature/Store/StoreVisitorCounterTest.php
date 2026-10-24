<?php

namespace Tests\Feature\Store;

use App\Domain\Store\Visitors\VisitorCounter;
use App\Models\Store\StoreAdmin;
use App\Models\Store\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Vitrin ziyaret sayacı: yalnızca tarayıcıda açılan sayfalar sayılır; ziyaretçi günlük tekildir; yönetim ana sayfasında görünür. */
class StoreVisitorCounterTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'http://magaza.localhost';

    private const CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0 Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();
        StoreSetting::put(['is_open' => '1']);
    }

    private function visit(string $path = '/', string $agent = self::CHROME, array $headers = [])
    {
        return $this->get(self::HOST.$path, $headers + ['User-Agent' => $agent, 'Sec-Fetch-Dest' => 'document']);
    }

    public function test_page_views_are_counted_once_per_visitor_per_day(): void
    {
        $this->visit()->assertOk();
        $this->visit('/urunler')->assertOk();
        $this->visit('/', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) Safari/604.1')->assertOk();

        $stats = app(VisitorCounter::class)->stats();
        $this->assertSame(['visitors' => 2, 'views' => 3], $stats['today']);
        $this->assertSame(2, $stats['online']);
    }

    public function test_bots_background_requests_and_previews_are_not_counted(): void
    {
        $cases = [
            'bot' => fn () => $this->visit('/', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)')->assertOk(),
            'tarayıcısız' => fn () => $this->visit('/', '')->assertOk(),
            'fetch' => fn () => $this->visit('/', self::CHROME, ['Sec-Fetch-Dest' => 'empty'])->assertOk(),
            'ajax' => fn () => $this->visit('/', self::CHROME, ['X-Requested-With' => 'XMLHttpRequest'])->assertOk(),
            'ön yükleme' => fn () => $this->visit('/', self::CHROME, ['Sec-Purpose' => 'prefetch'])->assertOk(),
            'json' => fn () => $this->visit('/ara/oneri?q=kalem')->assertOk(),
            '404' => fn () => $this->visit('/yok-boyle-bir-sayfa')->assertNotFound(),
            'önizleme' => fn () => $this->withCookie('magaza_onizleme', '1')->visit()->assertOk(),
            'robots' => fn () => $this->get(self::HOST.'/robots.txt')->assertOk(),
        ];
        foreach ($cases as $name => $case) {
            $case();
            $this->assertSame(0, DB::table('store_visitor_days')->count(), "{$name} sayılmamalı");
        }
    }

    public function test_dashboard_shows_online_today_yesterday_month_and_total(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-23 12:00', 'Europe/Istanbul'));
        $this->visit();
        $this->visit('/', 'Mozilla/5.0 (Macintosh) Firefox/140.0');
        Carbon::setTestNow(Carbon::parse('2026-10-24 12:00', 'Europe/Istanbul'));
        $this->visit();
        $this->visit('/urunler');

        $stats = app(VisitorCounter::class)->stats();
        $this->assertSame(['visitors' => 1, 'views' => 2], $stats['today']);
        $this->assertSame(['visitors' => 2, 'views' => 2], $stats['yesterday']);
        $this->assertSame(['visitors' => 3, 'views' => 4], $stats['month']);
        $this->assertSame(1, $stats['online']);

        Carbon::setTestNow(Carbon::parse('2026-10-24 12:10', 'Europe/Istanbul'));
        $this->assertSame(0, app(VisitorCounter::class)->stats()['online']);

        $this->actingAs(StoreAdmin::create(['name' => 'Y', 'email' => 'y@magaza.local', 'password' => self::PASSWORD]), 'store_admin')
            ->get(route('store_admin.dashboard'))->assertOk()
            ->assertSee('Şu an sitede')->assertSee('Bugün ziyaretçi')->assertSee('Dün ziyaretçi')->assertSee('Toplam ziyaretçi')
            ->assertSee('4 sayfa görüntüleme')->assertSee('Sayım 23.10.2026 tarihinde başladı.');
        Carbon::setTestNow();
    }
}
