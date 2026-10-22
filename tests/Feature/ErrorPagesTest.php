<?php

namespace Tests\Feature;

use App\Models\ErrorEvent;
use App\Models\Store\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/** Canlıda (APP_DEBUG=false) kullanıcıya Türkçe hata sayfaları; sunucu hatasında referans kodu, ayrıntı yok. */
class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false]);
    }

    public function test_not_found_page_is_turkish_and_links_to_the_right_area(): void
    {
        $this->get('/bayi/olmayan-sayfa')->assertNotFound()->assertSee('Sayfa bulunamadı')->assertSee('href="/bayi"', false)->assertDontSee('Not Found');
        $this->get('/platform/olmayan-sayfa')->assertNotFound()->assertSee('Platform paneline dön');

        StoreSetting::put(['is_open' => '1']);
        $this->get('http://magaza.localhost/urun/olmayan-urun')->assertNotFound()->assertSee('Mağazaya dön')->assertSee(config('store.name'));
    }

    public function test_server_error_shows_reference_code_and_no_details(): void
    {
        Route::get('/bayi/_test-hata', fn () => throw new RuntimeException('gizli ayrıntı: veritabanı parolası'));

        $response = $this->get('/bayi/_test-hata')->assertStatus(500)->assertSee('Beklenmeyen bir hata oluştu')->assertDontSee('gizli ayrıntı');
        $event = ErrorEvent::where('message', 'like', 'gizli ayrıntı%')->sole();
        $response->assertSee('H'.$event->id);

        $this->actingAsPlatformAdmin();
        $this->get(route('platform.errors', ['q' => 'H'.$event->id]))->assertOk()->assertSee('gizli ayrıntı');
    }

    public function test_expired_page_and_too_many_requests_are_turkish(): void
    {
        Route::get('/bayi/_test-419', fn () => throw new TokenMismatchException);
        Route::get('/bayi/_test-429', fn () => abort(429));

        $this->get('/bayi/_test-419')->assertStatus(419)->assertSee('Sayfanın süresi doldu')->assertDontSee('Page Expired');
        $this->get('/bayi/_test-429')->assertStatus(429)->assertSee('Çok fazla deneme');
        $this->assertSame(0, ErrorEvent::count(), 'beklenen hatalar kayda girmez');
    }
}
