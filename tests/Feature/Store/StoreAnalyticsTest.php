<?php

namespace Tests\Feature\Store;

use App\Models\Store\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Google Analytics: yalnızca vitrinde, kimlik tanımlıysa; güvenlik politikası yalnızca orada genişler. */
class StoreAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'http://magaza.localhost';

    protected function setUp(): void
    {
        parent::setUp();
        StoreSetting::put(['is_open' => '1']);
    }

    public function test_storefront_loads_analytics_only_when_measurement_id_is_set(): void
    {
        $response = $this->get(self::HOST.'/')->assertOk()->assertDontSee('ga-measurement-id', false)->assertDontSee('_ga_*');
        $this->assertStringNotContainsString('googletagmanager', $response->headers->get('Content-Security-Policy'));

        config(['store.analytics_id' => 'G-TEST123']);
        $response = $this->get(self::HOST.'/')->assertOk()
            ->assertSee('<meta name="ga-measurement-id" content="G-TEST123">', false)->assertSee('_ga_*');
        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("script-src 'self' https://www.googletagmanager.com;", $csp);
        $this->assertStringContainsString("connect-src 'self' https://*.google-analytics.com", $csp);
        // Kartla ödeme: sipariş formunun iyzico ödeme sayfasına yönlenmesine izin verilir.
        $this->assertStringContainsString("form-action 'self' https://*.iyzipay.com https://*.iyzico.com;", $csp);
    }

    public function test_b2b_and_store_admin_never_allow_analytics(): void
    {
        config(['store.analytics_id' => 'G-TEST123']);

        foreach ([route('business.login'), route('store_admin.login')] as $url) {
            $response = $this->get($url);
            $this->assertStringContainsString("script-src 'self';", $response->headers->get('Content-Security-Policy'));
            $this->assertStringNotContainsString('google-analytics', $response->headers->get('Content-Security-Policy'));
            $this->assertStringContainsString("form-action 'self';", $response->headers->get('Content-Security-Policy'));
            $response->assertDontSee('ga-measurement-id', false);
        }
    }
}
