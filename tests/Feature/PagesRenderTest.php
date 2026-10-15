<?php

namespace Tests\Feature;

use App\Http\Middleware\RequirePlatformTwoFactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/** Her panelin tüm GET sayfaları hatasız açılır ve yalnızca kendi menüsünü gösterir. */
class PagesRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_pages_render(): void
    {
        $business = $this->makeBusiness();
        $admin = $this->makePlatformAdmin();
        $admin->forceFill(['two_factor_secret' => (new Google2FA)->generateSecretKey(32), 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($admin, 'platform')->withSession([RequirePlatformTwoFactor::SESSION_KEY => true]);

        foreach (['/platform', '/platform/isletmeler', '/platform/isletmeler/yeni', "/platform/isletmeler/{$business->id}",
            "/platform/isletmeler/{$business->id}/duzenle", '/platform/denetim-kayitlari', '/platform/hesabim'] as $url) {
            $this->get($url)->assertOk()->assertSee('B2B Platform')->assertDontSee('İşletme Ayarları');
        }
    }

    public function test_business_pages_render(): void
    {
        $business = $this->makeBusiness();
        $this->actingAs($business->user, 'business');

        foreach (['/toptanci', '/toptanci/ayarlar', '/toptanci/hesabim'] as $url) {
            $this->get($url)->assertOk()->assertSee('İşletme Ayarları')->assertDontSee('Denetim Kayıtları');
        }
    }

    public function test_business_settings_update(): void
    {
        $business = $this->makeBusiness();
        $this->actingAs($business->user, 'business');

        $this->put('/toptanci/ayarlar', [
            'display_name' => 'Yeni Ad', 'phone' => '0212 111 11 11', 'accent_color' => '#123456',
            'stock_display' => 'exact', 'ledger_post_on' => 'completion', 'default_payment_term_days' => 45, 'price_display' => 'incl_vat',
        ])->assertSessionHasNoErrors();

        $business->refresh()->load('settings');
        $this->assertSame('Yeni Ad', $business->display_name);
        $this->assertSame('completion', $business->settings->ledger_post_on);

        // Geçersiz renk kodu (stil enjeksiyonu denemesi) reddedilir.
        $this->put('/toptanci/ayarlar', [
            'display_name' => 'Yeni Ad', 'accent_color' => 'red;background:url(x)',
            'stock_display' => 'exact', 'ledger_post_on' => 'completion', 'default_payment_term_days' => 45, 'price_display' => 'incl_vat',
        ])->assertSessionHasErrors('accent_color');
    }

    public function test_dealer_pages_render(): void
    {
        $business = $this->makeBusiness();
        $dealer = $this->makeDealer();
        $this->relate($business, $dealer);
        $this->actingAs($dealer, 'dealer');

        foreach (['/bayi', '/bayi/hesabim', '/bayi/toptanci-sec'] as $url) {
            $this->get($url)->assertOk()->assertDontSee('İşletme Ayarları');
        }
    }

    public function test_public_home_does_not_link_platform_login(): void
    {
        $this->get('/')->assertOk()->assertSee('/toptanci/login')->assertSee('/bayi/login')->assertDontSee('/platform/login');
    }
}
