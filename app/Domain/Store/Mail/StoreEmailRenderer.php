<?php

namespace App\Domain\Store\Mail;

use App\Domain\Mail\EmailLogo;

/**
 * Mağaza e-postalarının HTML/düz metin gövdesi ve gömülü logosu (B2B e-postalarıyla aynı düzen, emails.store).
 * Logo bağlantı olarak değil, e-postanın içine gömülü (cid) eklenir; tarayıcı önizlemesinde data: adresi kullanılır.
 */
class StoreEmailRenderer
{
    public const LOGO_CID = 'magaza-logo';

    /** E-postada logonun gösterileceği yükseklik (px); görsel 2 katı çözünürlükte gömülür. */
    public const LOGO_HEIGHT = 48;

    /**
     * @param  array{title: string, intro: string, rows?: list<array{0: string, 1: string}>, action_url?: string, action_label?: string, outro?: string, eyebrow?: string, tagline?: string, documents?: list<array{title: string, html: string}>}  $content
     * @return array{html: string, text: string, inline: list<array{cid: string, data: string, name: string, mime: string}>}
     */
    public function render(string $subject, array $content): array
    {
        $html = view('emails.store', $content + ['subject' => $subject] + $this->logoView(true))->render();

        return ['html' => $html, 'text' => $this->text($subject, $content), 'inline' => [$this->inlineLogo()]];
    }

    /**
     * Şablona verilecek logo değişkenleri. $embedded: gönderimde cid, önizlemede data: adresi.
     *
     * @return array{logo_src: string, logo_height: int}
     */
    public function logoView(bool $embedded): array
    {
        $src = $embedded ? 'cid:'.self::LOGO_CID : 'data:image/png;base64,'.base64_encode(EmailLogo::puzzle(self::LOGO_HEIGHT));

        return ['logo_src' => $src, 'logo_height' => self::LOGO_HEIGHT];
    }

    /** @return array{cid: string, data: string, name: string, mime: string} */
    public function inlineLogo(): array
    {
        return ['cid' => self::LOGO_CID, 'data' => EmailLogo::puzzle(self::LOGO_HEIGHT), 'name' => 'logo.png', 'mime' => 'image/png'];
    }

    /** HTML göremeyen istemciler için okunaklı düz metin. */
    private function text(string $subject, array $content): string
    {
        $name = config('store.name');
        $lines = [$name, str_repeat('=', mb_strlen($name)), '', $content['title'] ?? $subject, '', strip_tags($content['intro'])];
        if (! empty($content['code'])) {
            array_push($lines, '', 'Doğrulama kodunuz: '.$content['code']);
        }

        foreach ($content['rows'] ?? [] as [$label, $value]) {
            $lines[] = "{$label}: {$value}";
        }
        if (! empty($content['action_url'])) {
            array_push($lines, '', ($content['action_label'] ?? 'Devam et').': '.$content['action_url']);
        }
        if (! empty($content['outro'])) {
            array_push($lines, '', $content['outro']);
        }

        return implode("\n", $lines);
    }
}
