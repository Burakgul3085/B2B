<?php

namespace Tests\Feature\Communication;

use App\Domain\Branding\PuzzleLogo;
use App\Domain\Files\ImageUploader;
use App\Domain\Mail\BusinessEmailRenderer;
use App\Domain\Mail\MailTransport;
use App\Domain\Mail\SecretBox;
use App\Models\EmailSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesOrders;
use Tests\Fakes\FakeMailTransport;
use Tests\TestCase;

/** E-posta markası: logo e-postaya gömülür (cid); işletme logosu değişince e-postalar yeni logoyla gider. */
class EmailBrandingTest extends TestCase
{
    use CreatesOrders, RefreshDatabase;

    public function test_logo_is_embedded_and_follows_the_business_logo(): void
    {
        Storage::fake('local');
        $transport = new FakeMailTransport;
        $this->app->instance(MailTransport::class, $transport);

        $business = $this->makeBusiness();
        $this->inTenant($business, fn () => EmailSetting::create([
            'from_email' => 'ana@gmail.com', 'status' => 'verified', 'app_password_encrypted' => app(SecretBox::class)->encrypt('abcdefghijklmnop'),
        ]));
        $this->actingAs($business->user, 'business');

        // Logo yokken: yapboz logosu gömülür, işletme adı metin olarak yazılır.
        $this->post(route('business.email.test'))->assertSessionHasNoErrors();
        $first = $transport->sent[0]['mail'];
        $this->assertStringContainsString('cid:'.BusinessEmailRenderer::LOGO_CID, $first['html']);
        $this->assertStringNotContainsString('/logo/', $first['html'], 'Logo dış bağlantıyla çekilmez.');
        $this->assertSame(BusinessEmailRenderer::LOGO_CID, $first['inline'][0]['cid']);
        $this->assertStringStartsWith("\x89PNG", $first['inline'][0]['data']);
        $this->assertStringContainsString($business->display_name, $first['html']);

        // Toptancı logosunu değiştirir: sonraki e-postada gömülü görsel değişir.
        $tmp = tempnam(sys_get_temp_dir(), 'logo').'.png';
        file_put_contents($tmp, PuzzleLogo::png(PuzzleLogo::withWordmark('Deneme', 'Toptan')));
        $business->update(['logo_path' => app(ImageUploader::class)->store(new UploadedFile($tmp, 'logo.png', 'image/png', null, true), 'businesses/'.$business->id.'/logo', 480)]);

        $this->post(route('business.email.test'))->assertSessionHasNoErrors();
        $second = $transport->sent[1]['mail'];
        $this->assertNotSame($first['inline'][0]['data'], $second['inline'][0]['data']);
        $this->assertSame(56 * 2, getimagesizefromstring($second['inline'][0]['data'])[1], 'Logo e-posta için 2x yüksekliğe ölçeklenir.');
    }
}
