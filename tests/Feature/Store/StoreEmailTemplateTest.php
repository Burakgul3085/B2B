<?php

namespace Tests\Feature\Store;

use App\Domain\Store\Mail\StoreEmailRenderer;
use App\Domain\Store\Mail\StoreMailer;
use App\Models\Store\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Mağaza e-postaları B2B e-postalarıyla aynı kurumsal düzendedir: gömülü logo, özet tablosu, yardım ve satıcı künyesi. */
class StoreEmailTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_email_uses_the_corporate_layout_with_embedded_logo(): void
    {
        StoreSetting::put(['company_title' => 'Yapboz Kırtasiye Ltd. Şti.', 'phone' => '0222 123 45 67', 'email' => 'bilgi@yapbozkirtasiye.com',
            'company_tax_office' => 'Tepebaşı', 'company_tax_number' => '1234567890']);

        $mail = app(StoreEmailRenderer::class)->render('Siparişiniz alındı — YK-1', [
            'eyebrow' => 'Sipariş YK-1',
            'title' => 'Siparişiniz için teşekkürler',
            'intro' => 'Siparişinizi aldık.',
            'rows' => [['Jel Kalem × 2', '100,00 ₺'], ['Toplam (KDV dahil)', '100,00 ₺']],
            'action_url' => 'https://magaza.localhost/siparis/YK-1',
            'action_label' => 'Siparişi görüntüle',
            'outro' => 'Sipariş numaranız YK-1.',
        ]);

        $this->assertStringContainsString('src="cid:'.StoreEmailRenderer::LOGO_CID.'"', $mail['html']);
        $this->assertSame(StoreEmailRenderer::LOGO_CID, $mail['inline'][0]['cid']);
        $this->assertStringStartsWith("\x89PNG", $mail['inline'][0]['data']);
        foreach (['Sipariş YK-1', 'Siparişiniz için teşekkürler', 'Jel Kalem × 2', 'Siparişi görüntüle', 'Sipariş numaranız YK-1.',
            'Yardıma mı ihtiyacınız var?', '0222 123 45 67', 'Yapboz Kırtasiye Ltd. Şti.', 'Tepebaşı V.D. · 1234567890'] as $text) {
            $this->assertStringContainsString($text, $mail['html']);
        }
        $this->assertStringContainsString('Siparişi görüntüle: https://magaza.localhost/siparis/YK-1', $mail['text']);
    }

    public function test_store_mailer_sends_the_rendered_layout_with_inline_logo(): void
    {
        $mailer = new class extends StoreMailer
        {
            public array $sent = [];

            public function sendHtml(string $to, string $subject, string $html, string $text, array $headers = [], ?string $logUrl = null, array $inline = []): bool
            {
                $this->sent[] = compact('to', 'subject', 'html', 'inline');

                return true;
            }
        };

        $this->assertTrue($mailer->send('ayse@ornek.com', 'Deneme', ['title' => 'Merhaba', 'intro' => 'Deneme e-postası.']));
        $this->assertStringContainsString('Yardıma mı ihtiyacınız var?', $mailer->sent[0]['html']);
        $this->assertSame(StoreEmailRenderer::LOGO_CID, $mailer->sent[0]['inline'][0]['cid']);
    }
}
