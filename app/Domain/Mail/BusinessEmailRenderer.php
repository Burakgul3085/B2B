<?php

namespace App\Domain\Mail;

use App\Domain\Branding\PuzzleLogo;
use App\Models\Business;
use GdImage;
use Illuminate\Support\Facades\Storage;

/**
 * İşletme e-postasının HTML/düz metin gövdesini ve gömülü logosunu hazırlar.
 * Logo bağlantı olarak değil, e-postanın içine gömülü (cid) eklenir: alıcının e-posta istemcisi
 * sunucumuza erişemese de (ör. yerel kurulum, güvenlik duvarı) logo görünür.
 * İşletme logosunu değiştirdiğinde sonraki bütün e-postalar yeni logoyla gider; logo yoksa yapboz logosu kullanılır.
 */
class BusinessEmailRenderer
{
    public const LOGO_CID = 'isletme-logo';

    /** E-postada logonun gösterileceği yükseklik (px); görsel 2 katı çözünürlükte gömülür. */
    private const LOGO_HEIGHT = 56;

    /**
     * @param  array{intro: string, action_url?: ?string, action_label?: ?string, details?: array<string, string>}  $content
     * @return array{html: string, text: string, inline: list<array{cid: string, data: string, name: string, mime: string}>}
     */
    public function render(Business $business, ?string $templateKey, string $subject, array $content): array
    {
        [$logo, $width, $isDefault] = $this->logo($business);

        $html = view('emails.business', [
            'business' => $business,
            'subject' => $subject,
            'intro' => $content['intro'],
            'details' => $content['details'] ?? [],
            'code' => $content['code'] ?? null,
            'action_url' => $content['action_url'] ?? null,
            'action_label' => $content['action_label'] ?? null,
            'logo_src' => 'cid:'.self::LOGO_CID,
            'logo_width' => $width,
            'logo_height' => self::LOGO_HEIGHT,
            'logo_is_default' => $isDefault,
        ] + EmailTemplates::meta($templateKey))->render();

        return [
            'html' => $html,
            'text' => $this->text($business, $subject, $content, EmailTemplates::meta($templateKey)['note']),
            'inline' => [['cid' => self::LOGO_CID, 'data' => $logo, 'name' => 'logo.png', 'mime' => 'image/png']],
        ];
    }

    /** @return array{0: string, 1: int, 2: bool} PNG verisi, gösterim genişliği, varsayılan logo mu */
    private function logo(Business $business): array
    {
        $image = null;
        if ($business->logo_path && Storage::disk('local')->exists($business->logo_path)) {
            $image = @imagecreatefromstring((string) Storage::disk('local')->get($business->logo_path)) ?: null;
        }

        $isDefault = $image === null;
        $image ??= PuzzleLogo::icon(self::LOGO_HEIGHT * 2);
        $image = $this->fitHeight($image, self::LOGO_HEIGHT * 2);

        // Çok geniş logolar e-posta başlığını taşırmasın.
        $width = (int) min(260, round(imagesx($image) * self::LOGO_HEIGHT / imagesy($image)));

        return [PuzzleLogo::png($image), $width, $isDefault];
    }

    private function fitHeight(GdImage $image, int $height): GdImage
    {
        if (imagesy($image) <= $height) {
            imagesavealpha($image, true);

            return $image;
        }

        $width = max(1, (int) round(imagesx($image) * $height / imagesy($image)));
        $out = imagecreatetruecolor($width, $height);
        imagealphablending($out, false);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
        imagesavealpha($out, true);
        imagecopyresampled($out, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));

        return $out;
    }

    /** HTML göremeyen istemciler için okunaklı düz metin. */
    private function text(Business $business, string $subject, array $content, ?string $note): string
    {
        $lines = [$business->display_name, str_repeat('=', mb_strlen($business->display_name)), '', $subject, '', $content['intro']];
        if (! empty($content['code'])) {
            array_push($lines, '', 'Doğrulama kodunuz: '.$content['code']);
        }

        foreach ($content['details'] ?? [] as $label => $value) {
            $lines[] = "{$label}: {$value}";
        }
        if (! empty($content['action_url'])) {
            array_push($lines, '', ($content['action_label'] ?: 'Panele git').': '.$content['action_url']);
        }
        if ($note) {
            array_push($lines, '', $note);
        }

        array_push($lines, '', '—', $business->legal_name, implode(' · ', array_filter([$business->phone, $business->email])));

        return implode("\n", $lines);
    }
}
