<?php

namespace Tests\Feature\Store;

use App\Domain\Store\Mail\StoreMailer;
use App\Models\Store\StoreAdmin;
use App\Models\Store\StoreOrder;
use App\Models\Store\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Yardım sayfaları, yönetimden düzenlenen alt bilgi sütunları, sosyal medya ve mağaza e-posta hesabı. */
class StoreHelpPagesTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'http://magaza.localhost';

    protected function setUp(): void
    {
        parent::setUp();
        config(['store.bot_protection' => false]);
        StoreSetting::put(['is_open' => '1', 'shipping_cargo_fee' => '59.90', 'shipping_free_threshold' => '750.00', 'transfer_enabled' => '1', 'transfer_days' => '3']);
    }

    private function admin(): void
    {
        $this->actingAs(StoreAdmin::create(['name' => 'V', 'email' => 'v@magaza.local', 'password' => self::PASSWORD]), 'store_admin');
    }

    public function test_help_pages_render_with_defaults_and_live_settings(): void
    {
        $this->get(self::HOST.'/yardim')->assertOk()->assertSee('Yardım Merkezi')->assertSee('Sıkça sorulan sorular')->assertSee('/sss"', false);
        $this->get(self::HOST.'/sss')->assertOk()->assertSee('Siparişimi nasıl takip edebilirim?')->assertSee('"FAQPage"', false);
        $this->get(self::HOST.'/siparis-ve-teslimat')->assertOk()->assertSee('₺59,90')->assertSee('₺750,00 ve üzeri');
        $this->get(self::HOST.'/odeme-secenekleri')->assertOk()->assertSee('Havale / EFT')->assertSee('aria-label="Troy"', false)->assertDontSee('Kapıda ödeme</p>', false);
        $this->get(self::HOST.'/yasal-haklar')->assertOk()->assertSee('Mesafeli Satış Sözleşmesi')->assertSee('KVKK Aydınlatma Metni');
        $this->get(self::HOST.'/kargo-takip')->assertOk()->assertSee('Siparişimi bul');

        // Alt bilgi varsayılan sütunları: Kurumsal / Üyelik / Yardım
        $this->get(self::HOST.'/')->assertSee('Kurumsal')->assertSee('Üyelik')->assertSee('Şifremi unuttum')->assertSee('Sıkça Sorulan Sorular')
            ->assertSee('Yasal Haklar ve Sözleşmeler')->assertDontSee('/hakkimizda"', false) // Hakkımızda yayında değil
            ->assertDontSee('id="e-bulten"', false); // e-posta hesabı yokken abonelik formu gizli
    }

    public function test_tracking_finds_order_by_number_and_email(): void
    {
        $order = StoreOrder::forceCreate([
            'order_no' => 'YB900001', 'access_token' => Str::random(40), 'email' => 'ali@ornek.com', 'phone' => '05321234567', 'status' => 'shipped',
            'payment_method' => 'card', 'payment_status' => 'paid', 'delivery_method' => 'cargo', 'shipping_name' => 'Ali Veli', 'shipping_phone' => '05321234567',
            'shipping_province_id' => 26, 'shipping_district_id' => 1, 'shipping_province' => 'Eskişehir', 'shipping_district' => 'Tepebaşı', 'shipping_address' => 'Adres',
            'billing_type' => 'individual', 'billing_name' => 'Ali Veli', 'billing_address' => 'Adres', 'subtotal' => '10.00', 'total' => '10.00', 'vat_total' => '1.67',
        ]);

        $this->post(self::HOST.'/kargo-takip', ['order_no' => 'YB900001', 'email' => 'baska@ornek.com'])->assertSessionHasErrors('order_no');
        $this->post(self::HOST.'/kargo-takip', ['order_no' => 'yb900001', 'email' => 'ALI@ornek.com'])
            ->assertRedirect(route('store.order', ['orderNo' => 'YB900001', 'token' => $order->access_token]));
    }

    public function test_admin_edits_help_pages_and_unpublished_pages_disappear(): void
    {
        $this->admin();
        $this->get(route('store_admin.help-pages.index'))->assertOk()->assertSee('Kargo Takip');
        $this->get(route('store_admin.help-pages.edit', 'faq'))->assertOk()->assertSee('Sorular ve cevaplar');

        $this->put(route('store_admin.help-pages.update', 'faq'), ['title' => 'SSS', 'is_published' => '1', 'items' => [['group' => 'Kargo', 'question' => 'Kargo kaç gün?', 'answer' => '']]])
            ->assertSessionHasErrors('items.0');
        $this->put(route('store_admin.help-pages.update', 'faq'), ['title' => 'Merak edilenler', 'intro' => 'Kısa cevaplar', 'is_published' => '1',
            'items' => [['group' => 'Kargo', 'question' => 'Kargo kaç gün?', 'answer' => '1–3 iş günü.'], ['group' => '', 'question' => '', 'answer' => '']]])->assertSessionHasNoErrors();
        $this->get(self::HOST.'/sss')->assertSee('Merak edilenler')->assertSee('Kargo kaç gün?')->assertDontSee('Siparişimi nasıl takip edebilirim?');

        $this->put(route('store_admin.help-pages.update', 'help'), ['title' => 'Yardım', 'is_published' => '1',
            'tiles' => [['icon' => 'truck', 'title' => 'Kargo', 'text' => '', 'link' => 'olmayan-sayfa']]])->assertSessionHasErrors('tiles.0.link');

        $this->put(route('store_admin.help-pages.update', 'tracking'), ['title' => 'Kargo Takip'])->assertSessionHasNoErrors(); // yayından kaldırıldı
        $this->get(self::HOST.'/kargo-takip')->assertNotFound();
        $this->get(self::HOST.'/')->assertDontSee('/kargo-takip"', false);
    }

    public function test_footer_columns_and_social_links_are_managed(): void
    {
        $this->admin();
        $base = ['store_title' => 'Mağazamız'];
        $this->put(route('store_admin.footer.update'), $base + ['columns' => [['title' => 'Bilgi', 'links' => [['key' => 'url', 'url' => 'http://kotu', 'label' => 'X']]]]])
            ->assertSessionHasErrors('columns.0.links.0.url');
        $this->put(route('store_admin.footer.update'), $base + ['columns' => [['title' => 'Bilgi', 'links' => [['key' => 'olmayan']]]]])
            ->assertSessionHasErrors('columns.0.links.0.key');
        $this->put(route('store_admin.footer.update'), $base + ['columns' => [
            ['title' => 'Kısayollar', 'links' => [['key' => 'faq', 'label' => 'Sorular'], ['key' => 'url', 'url' => 'https://ornek.com/katalog', 'label' => 'Katalog (PDF)'], ['key' => '']]],
            ['title' => '', 'links' => []],
        ]])->assertSessionHasNoErrors();

        $this->get(self::HOST.'/')->assertSee('Kısayollar')->assertSee('>Sorular</a>', false)->assertSee('https://ornek.com/katalog', false)->assertDontSee('Şifremi unuttum');

        $this->put(route('store_admin.settings.update'), ['youtube' => 'http://youtube.com/x'])->assertSessionHasErrors('youtube');
        $this->put(route('store_admin.settings.update'), ['youtube' => 'https://youtube.com/@yapboz', 'tiktok' => 'https://tiktok.com/@yapboz'])->assertSessionHasNoErrors();
        $this->get(self::HOST.'/')->assertSee('aria-label="YouTube"', false)->assertSee('https://tiktok.com/@yapboz', false)->assertDontSee('aria-label="Pinterest"', false);
    }

    public function test_store_mail_account_is_saved_encrypted_and_used(): void
    {
        $this->admin();
        $this->assertFalse(StoreMailer::hasOwnAccount());
        $this->put(route('store_admin.settings.update'), ['mail_host' => 'smtp gmail', 'mail_port' => '587'])->assertSessionHasErrors('mail_host');
        $this->put(route('store_admin.settings.update'), ['mail_host' => 'smtp.gmail.com', 'mail_port' => '587', 'mail_encryption' => 'tls', 'mail_username' => 'yapboz@gmail.com',
            'mail_password' => 'uygulama-sifresi', 'mail_from_address' => 'yapboz@gmail.com', 'mail_from_name' => 'Yapboz Kırtasiye'])->assertSessionHasNoErrors();

        $this->assertTrue(StoreMailer::hasOwnAccount());
        $this->assertNotSame('uygulama-sifresi', StoreSetting::get('mail_password'));
        $this->assertSame('uygulama-sifresi', StoreSetting::secret('mail_password'));
        $this->get(route('store_admin.settings.edit'))->assertOk()->assertDontSee('uygulama-sifresi')->assertSee('Kendime deneme e-postası gönder');

        // Boş parola eskisini korur
        $this->put(route('store_admin.settings.update'), ['mail_host' => 'smtp.gmail.com', 'mail_username' => 'yapboz@gmail.com', 'mail_from_address' => 'yapboz@gmail.com']);
        $this->assertSame('uygulama-sifresi', StoreSetting::secret('mail_password'));
    }
}
