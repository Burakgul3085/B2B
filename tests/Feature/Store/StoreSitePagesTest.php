<?php

namespace Tests\Feature\Store;

use App\Domain\Store\Content\MapLink;
use App\Domain\Store\Content\OpeningHours;
use App\Domain\Store\Content\SitePages;
use App\Domain\Store\Mail\StoreMailer;
use App\Models\Store\StoreAdmin;
use App\Models\Store\StoreContactMessage;
use App\Models\Store\StorePage;
use App\Models\Store\StoreSetting;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Sabit sayfalar (Hakkımızda, İletişim): yönetimde düzenleme, vitrinde gösterim, harita, iletişim formu ve Mesajlar. */
class StoreSitePagesTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'http://magaza.localhost';

    /** @var list<array{to: string, subject: string}> */
    public array $mails = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        StoreSetting::put(['is_open' => '1', 'phone' => '0222 123 45 67', 'whatsapp' => '0532 123 45 67', 'email' => 'info@yapboz.test',
            'address' => 'Hoşnudiye Mah. İsmet İnönü Cad. No 5, Tepebaşı / Eskişehir', 'company_title' => 'Yapboz Kırtasiye Ltd. Şti.', 'company_mersis' => '0123456789012345']);
        $test = $this;
        $this->instance(StoreMailer::class, new class($test) extends StoreMailer
        {
            public function __construct(private $test) {}

            public function send(string $to, string $subject, array $content): bool
            {
                $this->test->mails[] = compact('to', 'subject');

                return true;
            }

            public function sendHtml(string $to, string $subject, string $html, string $text, array $headers = [], ?string $logUrl = null, array $inline = []): bool
            {
                $this->test->mails[] = compact('to', 'subject', 'html', 'headers');

                return true;
            }
        });
        $this->actingAs(StoreAdmin::create(['name' => 'Vitrin', 'email' => 'v@magaza.local', 'password' => self::PASSWORD]), 'store_admin');
    }

    private function image(): UploadedFile
    {
        $file = tempnam(sys_get_temp_dir(), 'pg');
        $gd = imagecreatetruecolor(1600, 900);
        imagefill($gd, 0, 0, imagecolorallocate($gd, 40, 90, 200));
        imagepng($gd, $file);

        return new UploadedFile($file, 'foto.png', 'image/png', null, true);
    }

    private function hours(array $override = []): array
    {
        $hours = [];
        foreach (array_keys(OpeningHours::DAYS) as $day) {
            $hours[$day] = ['open' => '09:00', 'close' => '19:00', 'closed' => $day === 'sun' ? '1' : '0'];
        }

        return array_replace_recursive($hours, $override);
    }

    public function test_about_page_is_edited_with_images_and_published(): void
    {
        $this->get(route('store_admin.site-pages.about'))->assertOk()->assertSee('Vitrin → Hakkımızda')->assertSee('Yayında değil');
        $this->get(self::HOST.'/hakkimizda')->assertNotFound();

        $this->put(route('store_admin.site-pages.about.update'), [
            'headline' => 'Biz Kimiz', 'tagline' => '1998\'den beri Eskişehir\'de', 'story' => "## Hikâyemiz\n\n**Yapboz** küçük bir dükkânla başladı.",
            'stats' => [['value' => '1998', 'label' => 'Kuruluş'], ['value' => '5.000+', 'label' => '']],
            'features' => [['icon' => 'truck', 'title' => 'Aynı gün teslimat', 'text' => 'Eskişehir içi']],
        ])->assertSessionHasErrors('stats.1');

        $this->put(route('store_admin.site-pages.about.update'), [
            'headline' => 'Biz Kimiz', 'tagline' => '1998\'den beri Eskişehir\'de', 'story' => "## Hikâyemiz\n\n**Yapboz** küçük bir dükkânla başladı.\n\n<script>alert(1)</script>",
            'stats' => [['value' => '1998', 'label' => 'Kuruluş'], ['value' => '', 'label' => '']],
            'features' => [['icon' => 'truck', 'title' => 'Aynı gün teslimat', 'text' => 'Eskişehir içi'], ['icon' => 'star', 'title' => '', 'text' => '']],
            'show_cta' => '1', 'is_published' => '1', 'seo_title' => 'Yapboz Kırtasiye Hakkında',
            'cover' => $this->image(), 'gallery' => [$this->image(), $this->image()],
        ])->assertSessionHasNoErrors();

        $page = StorePage::where('key', 'about')->sole();
        $this->assertTrue($page->is_published);
        $this->assertEquals([['value' => '1998', 'label' => 'Kuruluş', 'icon' => 'star']], $page->content['stats']);
        $this->assertCount(1, $page->content['features']);
        $this->assertCount(2, $page->content['gallery']);
        Storage::disk('local')->assertExists($page->content['cover']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'store.site_page_updated', 'entity_id' => $page->id]);

        $html = $this->get(self::HOST.'/hakkimizda')->assertOk()
            ->assertSee('Biz Kimiz')->assertSee('<h2>Hikâyemiz</h2>', false)->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('Kuruluş')->assertSee('Aynı gün teslimat')->assertSee('Ürünlere göz atın')->assertSee('<title>Yapboz Kırtasiye Hakkında', false)
            ->getContent();
        $this->assertStringContainsString('/hakkimizda"', $this->get(self::HOST.'/')->getContent(), 'alt bilgide bağlantı');

        // Görseller yalnızca sayfaya aitse sunulur.
        $cover = basename($page->content['cover']);
        $this->assertStringContainsString('/gorsel/sayfa/'.$cover, $html);
        $this->get(self::HOST.'/gorsel/sayfa/'.$cover)->assertOk();
        $this->get(self::HOST.'/gorsel/sayfa/'.str_repeat('a', 40).'.webp')->assertNotFound();
        $this->get(self::HOST.'/gorsel/sayfa/..%2F..%2F.env')->assertNotFound();

        // Galeriden kaldırılan fotoğraf silinir; yayından kaldırılan sayfa 404 verir ama önizlenir.
        $removed = $page->content['gallery'][0];
        $this->put(route('store_admin.site-pages.about.update'), ['headline' => 'Biz Kimiz', 'remove_gallery' => [basename($removed)], 'is_published' => '0'])
            ->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($removed);
        $this->assertCount(1, $page->fresh()->content['gallery']);
        $this->get(self::HOST.'/hakkimizda')->assertNotFound();
        $this->get(self::HOST.'/gorsel/sayfa/'.$cover)->assertNotFound();
        $this->get(route('store_admin.site-pages.preview', 'about'))->assertOk()->assertSee('Önizleme')->assertSee('Biz Kimiz')
            ->assertSee(route('store_admin.site-pages.image', $cover));
        $this->get(route('store_admin.site-pages.image', $cover))->assertOk();
    }

    public function test_about_page_sections_gallery_team_and_toggles(): void
    {
        $base = ['headline' => 'Biz Kimiz', 'is_published' => '1', 'story' => 'Küçük bir dükkânla başladık.'];
        $this->put(route('store_admin.site-pages.about.update'), $base + ['timeline' => [['year' => '1998', 'title' => '']]])->assertSessionHasErrors('timeline.0');
        $this->put(route('store_admin.site-pages.about.update'), $base + ['cta_url' => 'javascript:alert(1)'])->assertSessionHasErrors('cta_url');

        $this->put(route('store_admin.site-pages.about.update'), $base + [
            'stats' => [['icon' => 'users', 'value' => '10.000+', 'label' => 'Mutlu müşteri']],
            'story_title' => 'Nasıl başladık', 'badge_value' => '28', 'badge_label' => 'yıldır hizmetinizde',
            'pillars' => ['mission' => ['title' => 'Misyonumuz', 'text' => 'Her öğrenciye kaliteli kırtasiye.'], 'vision' => ['title' => 'Vizyonumuz', 'text' => ''], 'values' => ['title' => 'Değerlerimiz', 'text' => 'Dürüstlük.']],
            'timeline' => [['year' => '1998', 'title' => 'İlk dükkân', 'text' => 'Tepebaşı'], ['year' => '2026', 'title' => 'Online mağaza', 'text' => '']],
            'team' => [['name' => 'Onur Birbilen', 'role' => 'Mağaza yöneticisi'], ['name' => '', 'role' => '']],
            'cta_title' => 'Okula hazır mısınız?', 'cta_label' => 'Bize ulaşın', 'cta_url' => '/iletisim',
            'sections' => array_fill_keys(array_keys(SitePages::ABOUT_SECTIONS), '1'), 'show_cta' => '1',
            'story_images' => [0 => $this->image(), 1 => $this->image()], 'gallery' => [$this->image(), $this->image()], 'team_photo' => [0 => $this->image()],
        ])->assertSessionHasNoErrors();

        $c = StorePage::where('key', 'about')->sole()->content;
        $this->assertCount(2, $c['story_images']);
        $this->assertCount(1, $c['team']);
        $this->assertNotNull($c['team'][0]['photo']);
        [$first, $second] = array_map('basename', $c['gallery']);

        $this->get(self::HOST.'/hakkimizda')->assertOk()
            ->assertSee('10.000+')->assertSee('Mutlu müşteri')->assertSee('Nasıl başladık')->assertSee('yıldır hizmetinizde')
            ->assertSee('Her öğrenciye kaliteli kırtasiye.')->assertSee('Değerlerimiz')->assertDontSee('Vizyonumuz')
            ->assertSee('İlk dükkân')->assertSee('Online mağaza')->assertSee('Onur Birbilen')->assertSee('Mağaza yöneticisi')
            ->assertSee('Okula hazır mısınız?')->assertSee('hakkimizda/galeri', false);
        // Galerinin tamamı ayrı sayfada
        $this->get(self::HOST.'/hakkimizda/galeri')->assertOk()->assertSee('2 fotoğraf')->assertSee(basename($c['gallery'][1]), false);
        // Hikâye ve ekip fotoğrafları da sayfaya ait sayılır
        $this->get(self::HOST.'/gorsel/sayfa/'.basename($c['team'][0]['photo']))->assertOk();
        $this->get(self::HOST.'/gorsel/sayfa/'.basename($c['story_images'][1]))->assertOk();

        // Galeri sırası ve açıklaması; bölüm kapatma; ekipten çıkarılan kişinin fotoğrafı silinir
        $this->put(route('store_admin.site-pages.about.update'), $base + [
            'gallery_order' => [$first => 2, $second => 1], 'gallery_captions' => [$first => 'Vitrinimiz'],
            'team' => [['name' => '', 'role' => '']], 'show_cta' => '0',
            'sections' => ['stats' => '0'] + array_fill_keys(array_keys(SitePages::ABOUT_SECTIONS), '1'),
        ])->assertSessionHasNoErrors();
        $fresh = StorePage::where('key', 'about')->sole()->content;
        $this->assertSame([$second, $first], array_map('basename', $fresh['gallery']));
        $this->assertSame(['stats'], $fresh['hidden_sections']);
        $this->assertSame([], $fresh['team']);
        Storage::disk('local')->assertMissing($c['team'][0]['photo']);
        $this->get(self::HOST.'/hakkimizda')->assertSee('Vitrinimiz')->assertDontSee('Mutlu müşteri')->assertDontSee('Okula hazır mısınız?');

        // Galeri bölümü kapatılınca galeri sayfası da kapanır
        $this->put(route('store_admin.site-pages.about.update'), $base + ['sections' => ['gallery' => '0'] + array_fill_keys(array_keys(SitePages::ABOUT_SECTIONS), '1')])->assertSessionHasNoErrors();
        $this->get(self::HOST.'/hakkimizda/galeri')->assertNotFound();
    }

    public function test_contact_page_hours_map_and_details(): void
    {
        Http::fake([
            'https://maps.app.goo.gl/*' => Http::response('', 302, ['Location' => 'https://www.google.com/maps/place/Yapboz+K%C4%B1rtasiye/@39.7845,30.5101,17z/data=!3m1!4b1!4m6!3m5!1s0x0:0x1!8m2!3d39.7843216!4d30.5123456']),
        ]);

        $this->put(route('store_admin.site-pages.contact.update'), ['hours' => $this->hours(['mon' => ['open' => '19:00', 'close' => '09:00']])])
            ->assertSessionHasErrors('hours.mon');
        $this->put(route('store_admin.site-pages.contact.update'), ['hours' => $this->hours(), 'map_input' => 'https://kotu.site/maps'])
            ->assertSessionHasErrors('map_input');
        $this->put(route('store_admin.site-pages.contact.update'), [
            'intro' => 'Bize her zaman ulaşabilirsiniz.', 'hours' => $this->hours(), 'hours_note' => 'Resmi tatillerde kapalıyız.',
            'map_input' => 'https://maps.app.goo.gl/AbCdEf123', 'form_enabled' => '1', 'is_published' => '1',
        ])->assertSessionHasNoErrors();

        $content = StorePage::where('key', 'contact')->sole()->content;
        $this->assertSame('https://www.google.com/maps?q=39.7843216,30.5123456&z=17&hl=tr&output=embed', $content['map_embed']);
        $this->assertSame('https://www.google.com/maps/dir/?api=1&destination=39.7843216,30.5123456', $content['map_directions']);
        $this->get(route('store_admin.site-pages.contact'))->assertOk()->assertSee($content['map_embed'])->assertSee('0222 123 45 67');

        Carbon::setTestNow(Carbon::parse('2026-10-26 10:30', 'Europe/Istanbul')); // pazartesi
        $response = $this->get(self::HOST.'/iletisim')->assertOk()
            ->assertSee('Bize her zaman ulaşabilirsiniz.')->assertSee('Şu an açık')->assertSee('Pazartesi – Cumartesi')->assertSee('Resmi tatillerde kapalıyız.')
            ->assertSee($content['map_embed'])->assertSee('Yol tarifi al')->assertSee('tel:02221234567', false)->assertSee('https://wa.me/905321234567', false)
            ->assertSee('Yapboz Kırtasiye Ltd. Şti.')->assertSee('0123456789012345')->assertSee('"@type":"Store"', false)->assertSee('"opens":"09:00"', false);
        $this->assertStringContainsString('frame-src https://www.google.com', $response->headers->get('Content-Security-Policy'));

        Carbon::setTestNow(Carbon::parse('2026-10-25 12:00', 'Europe/Istanbul')); // pazar
        $this->get(self::HOST.'/iletisim')->assertSee('Şu an kapalı');
        Carbon::setTestNow();

        $this->get(self::HOST.'/sitemap.xml')->assertSee(route('store.contact'));
    }

    public function test_map_link_accepts_embed_code_and_rejects_others(): void
    {
        $maps = app(MapLink::class);
        $embed = $maps->resolve('<iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3066.1!2d30.51!3d39.78!2m3!1f0!2f0!3f0" width="600" height="450"></iframe>');
        $this->assertSame('https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3066.1!2d30.51!3d39.78!2m3!1f0!2f0!3f0', $embed['embed']);

        $direct = $maps->resolve('https://www.google.com/maps/@39.78,30.51,15z');
        $this->assertSame(39.78, $direct['lat']);

        foreach (['<iframe src="https://kotu.site/embed"></iframe>', 'http://maps.app.goo.gl/x', 'https://goo.gl/abc', 'javascript:alert(1)'] as $bad) {
            try {
                $maps->resolve($bad);
                $this->fail("kabul edilmemeli: {$bad}");
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }

        // Yönlendirme Google dışına giderse izlenmez.
        Http::fake(['https://maps.app.goo.gl/*' => Http::response('', 302, ['Location' => 'https://kotu.site/?q=39.7,30.5'])]);
        $this->expectException(DomainException::class);
        $maps->resolve('https://maps.app.goo.gl/Kotu');
    }

    public function test_contact_form_creates_message_and_admin_handles_it(): void
    {
        StorePage::create(['key' => 'contact', 'is_published' => true, 'content' => ['form_enabled' => true]]);
        $this->flushSession();

        $this->post(self::HOST.'/iletisim', ['name' => 'Ayşe Yılmaz', 'subject' => 'Ürün', 'message' => 'Defterlerin stoğu ne zaman gelir?'])
            ->assertSessionHasErrors('email');
        $this->post(self::HOST.'/iletisim', ['name' => 'Ayşe Yılmaz', 'phone' => '0532 111 22 33', 'subject' => 'Ürün', 'message' => 'Defterlerin stoğu ne zaman gelir?'])
            ->assertRedirect(self::HOST.'/iletisim#form')->assertSessionHas('contact_sent');
        $message = StoreContactMessage::sole();
        $this->assertSame(['05321112233', 'new'], [$message->phone, $message->status]);
        $this->app->terminate();
        $this->assertNotEmpty(array_filter($this->mails, fn ($m) => str_starts_with($m['subject'], 'Yeni iletişim mesajı')));

        $this->actingAs(StoreAdmin::sole(), 'store_admin');
        $this->get(route('store_admin.messages.index'))->assertOk()->assertSee('Ayşe Yılmaz')->assertSee('Yeni');
        $this->get(route('store_admin.messages.show', $message))->assertOk()->assertSee('Defterlerin stoğu')->assertSee('wa.me/905321112233', false);
        $this->assertSame('read', $message->fresh()->status);
        $this->post(route('store_admin.messages.replied', $message))->assertSessionHasNoErrors();
        $this->assertSame('replied', $message->fresh()->status);
        $this->delete(route('store_admin.messages.destroy', $message))->assertRedirect(route('store_admin.messages.index'));
        $this->assertSame(0, StoreContactMessage::count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'store.contact_message_deleted']);

        // Form kapalıyken gönderim kabul edilmez.
        StorePage::where('key', 'contact')->update(['content' => json_encode(['form_enabled' => false])]);
        $this->post(self::HOST.'/iletisim', ['name' => 'X', 'email' => 'x@ornek.com', 'subject' => 'Diğer', 'message' => 'Merhaba dünya'])->assertNotFound();
    }

    public function test_contact_form_sends_acknowledgement_and_admin_replies_by_email(): void
    {
        StorePage::create(['key' => 'contact', 'is_published' => true, 'content' => ['form_enabled' => true]]);
        $this->flushSession();

        $this->post(self::HOST.'/iletisim', ['name' => 'Ayşe Yılmaz', 'email' => 'Ayse@Ornek.com', 'subject' => 'Ürün', 'message' => 'Defterlerin stoğu ne zaman gelir?'])
            ->assertRedirect(self::HOST.'/iletisim#form');
        $this->app->terminate();
        $message = StoreContactMessage::sole();

        // Müşteriye otomatik "mesajınız ulaştı" e-postası; ad ve mesaj metni e-postaya konmaz. Yöneticiye bildirim de gider.
        $ack = collect($this->mails)->firstWhere('to', 'ayse@ornek.com');
        $this->assertNotNull($ack);
        $this->assertStringStartsWith('Mesajınızı aldık', $ack['subject']);
        $this->assertStringContainsString('Mesajınız bize ulaştı', $ack['html']);
        $this->assertStringNotContainsString('Defterlerin stoğu', $ack['html']);
        $this->assertSame(['Reply-To' => 'info@yapboz.test'], $ack['headers']);
        $this->assertNotEmpty(array_filter($this->mails, fn ($m) => str_starts_with($m['subject'], 'Yeni iletişim mesajı')));

        // Yönetici Mesajlar ekranından e-postayla yanıtlar.
        $this->mails = [];
        $this->actingAs(StoreAdmin::sole(), 'store_admin');
        $this->get(route('store_admin.messages.show', $message))->assertOk()->assertSee('E-postayla yanıtla');
        $this->post(route('store_admin.messages.reply', $message), ['body' => ''])->assertSessionHasErrors('body');
        $this->post(route('store_admin.messages.reply', $message), ['body' => "Merhaba,\nDefterler cuma günü stokta olacak."])
            ->assertSessionHasNoErrors()->assertSessionHas('status');

        // Testte her istek sonunda önceki afterResponse işleri yeniden çalışır; yanıt e-postası konusundan bulunur.
        $replies = array_values(array_filter($this->mails, fn ($m) => str_starts_with($m['subject'], 'Mesajınıza yanıt')));
        $this->assertCount(1, $replies);
        $this->assertSame('ayse@ornek.com', $replies[0]['to']);
        $this->assertStringContainsString('Defterler cuma günü stokta olacak.', $replies[0]['html']);
        $this->assertSame(['Reply-To' => 'info@yapboz.test'], $replies[0]['headers']);
        $this->assertSame('replied', $message->fresh()->status);
        $this->assertSame(['Vitrin', "Merhaba,\nDefterler cuma günü stokta olacak."], [$message->replies()->sole()->admin_name, $message->replies()->sole()->body]);
        $this->get(route('store_admin.messages.show', $message))->assertSee('Defterler cuma günü stokta olacak.')->assertSee('Yanıtınız · Vitrin');

        // E-postası olmayan mesaja e-postayla yanıt verilemez; mesaj silinince yanıtları da silinir.
        $phoneOnly = StoreContactMessage::create(['name' => 'Ali', 'phone' => '05321112233', 'subject' => 'Diğer', 'message' => 'Beni arar mısınız?']);
        $this->get(route('store_admin.messages.show', $phoneOnly))->assertDontSee('E-postayla yanıtla');
        $this->post(route('store_admin.messages.reply', $phoneOnly), ['body' => 'Merhaba'])->assertSessionHasErrors('body');
        $this->delete(route('store_admin.messages.destroy', $message));
        $this->assertDatabaseCount('store_contact_replies', 0);
    }

    public function test_second_phone_is_shown_in_footer_and_contact_page(): void
    {
        StorePage::create(['key' => 'contact', 'is_published' => true, 'content' => ['hours' => OpeningHours::defaults()]]);
        $this->put(route('store_admin.settings.update'), ['phone' => 'ara beni'])->assertSessionHasErrors('phone');
        $this->put(route('store_admin.settings.update'), ['phone' => '0222 111 22 33', 'phone_label' => 'Dükkân', 'phone2' => '0532 765 43 21', 'phone2_label' => 'Cep'])
            ->assertSessionHasNoErrors();
        $this->assertSame('0532 765 43 21', StoreSetting::get('phone2'));

        $this->get(self::HOST.'/')->assertOk()->assertSeeInOrder(['Dükkân:', '0222 111 22 33', 'Cep:', '0532 765 43 21'])->assertSee('tel:05327654321', false);
        $this->get(self::HOST.'/iletisim')->assertOk()->assertSeeInOrder(['Dükkân', '0222 111 22 33', 'Cep', '0532 765 43 21']);

        // Tek numara varsa etiket yazılmaz
        $this->put(route('store_admin.settings.update'), ['phone' => '0222 111 22 33', 'phone_label' => 'Dükkân', 'phone2' => '', 'phone2_label' => ''])->assertSessionHasNoErrors();
        $this->get(self::HOST.'/')->assertSee('0222 111 22 33')->assertDontSee('0532 765 43 21')->assertDontSee('Dükkân:');
    }

    public function test_footer_is_configurable_and_uses_store_data(): void
    {
        StorePage::create(['key' => 'contact', 'is_published' => true, 'content' => ['hours' => OpeningHours::defaults()]]);
        $this->get(route('store_admin.footer.edit'))->assertOk()->assertSee('Güven şeridi')->assertSee('Hızlı teslimat');

        $this->put(route('store_admin.footer.update'), ['shop_title' => 'Alışveriş', 'category_count' => 3, 'info_title' => 'Bilgi', 'store_title' => 'Mağazamız',
            'strip' => [['icon' => 'truck', 'title' => '', 'text' => 'Başlıksız']]])->assertSessionHasErrors('strip.0');
        $this->put(route('store_admin.footer.update'), [
            'footer_about' => "1998'den bu yana Eskişehir'in kırtasiyesi.", 'tagline' => 'Kırtasiye & Ofis',
            'strip_enabled' => '1', 'strip' => [['icon' => 'truck', 'title' => 'Aynı gün teslimat', 'text' => 'Eskişehir içi'], ['icon' => 'star', 'title' => '', 'text' => '']],
            'shop_title' => 'Alışveriş', 'category_count' => 3, 'info_title' => 'Kurumsal', 'store_title' => 'Dükkânımız',
            'show_map' => '1', 'show_hours' => '1', 'show_b2b' => '0', 'whatsapp_button' => '1', 'bottom_note' => 'EST. 1998 · Eskişehir',
        ])->assertSessionHasNoErrors();
        $this->assertSame("1998'den bu yana Eskişehir'in kırtasiyesi.", StoreSetting::get('footer_about'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'store.footer_updated']);

        $this->get(self::HOST.'/')->assertOk()
            ->assertSee('Aynı gün teslimat')->assertSee('Kırtasiye &amp; Ofis', false)->assertSee("1998'den bu yana")
            ->assertSee('Kurumsal')->assertSee('Dükkânımız')->assertSee('Pazartesi – Cuma')->assertSee('Tepebaşı / Eskişehir')
            ->assertSee('https://www.google.com/maps/dir/?api=1&amp;destination=', false)->assertSee('https://wa.me/905321234567', false)
            ->assertSee('EST. 1998 · Eskişehir')->assertDontSee('Bayi girişi')->assertSee('Koyu temaya geç')->assertSee('Sayfanın başına dön')->assertSee('/iletisim"', false);
    }

    public function test_launch_checklist_follows_published_pages(): void
    {
        $this->get(route('store_admin.launch'))->assertOk()->assertSee('Yayında olmayan sayfa: Hakkımızda, İletişim');
        StorePage::create(['key' => 'about', 'is_published' => true]);
        StorePage::create(['key' => 'contact', 'is_published' => true]);
        $this->get(route('store_admin.launch'))->assertOk()->assertDontSee('Yayında olmayan sayfa');
    }
}
