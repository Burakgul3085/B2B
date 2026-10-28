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

    /**
     * Sipariş e-postalarının hareketli figürleri (resources/images/mail, database/data/mail-figures/figures.py ile üretilir).
     * content['figure'] bu anahtarlardan biriyse e-postanın üstüne gömülü (cid) GIF olarak eklenir.
     */
    public const FIGURES = [
        'siparis-alindi' => 'Siparişinizi hazırlayan Yapboz figürü el sallıyor',
        'kargoda' => 'Yapboz kargo aracı yolda',
    ];

    /** E-postada logonun gösterileceği yükseklik (px); görsel 2 katı çözünürlükte gömülür. */
    public const LOGO_HEIGHT = 48;

    /**
     * @param  array{title: string, intro: string, rows?: list<array{0: string, 1: string}>, action_url?: string, action_label?: string, outro?: string, eyebrow?: string, tagline?: string, documents?: list<array{title: string, html: string}>}  $content
     * @return array{html: string, text: string, inline: list<array{cid: string, data: string, name: string, mime: string}>}
     */
    public function render(string $subject, array $content): array
    {
        $inline = [$this->inlineLogo()];
        $figure = $content['figure'] ?? null;
        if (isset(self::FIGURES[$figure])) {
            $content += ['figure_src' => 'cid:figur-'.$figure, 'figure_alt' => self::FIGURES[$figure]];
            $inline[] = ['cid' => 'figur-'.$figure, 'data' => file_get_contents(resource_path("images/mail/{$figure}.gif")), 'name' => "{$figure}.gif", 'mime' => 'image/gif'];
        }
        $html = view('emails.store', $content + ['subject' => $subject] + $this->logoView(true))->render();

        return ['html' => $html, 'text' => $this->text($subject, $content), 'inline' => $inline];
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
