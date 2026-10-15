<?php

namespace App\Domain\Auth;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Cache;
use PragmaRX\Google2FA\Google2FA;

/** TOTP (Google Authenticator vb.) iki adımlı doğrulama. */
class TwoFactor
{
    public function __construct(private Google2FA $engine = new Google2FA) {}

    public function generateSecret(): string
    {
        return $this->engine->generateSecretKey(32);
    }

    public function qrSvg(string $issuer, string $email, string $secret): string
    {
        $uri = $this->engine->getQRCodeUrl($issuer, $email, $secret);

        return (new Writer(new ImageRenderer(new RendererStyle(200, 1), new SvgImageBackEnd)))->writeString($uri);
    }

    /**
     * Kodu doğrular. Aynı kod geçerlilik penceresi içinde ikinci kez kullanılamaz (tekrar oynatma koruması).
     */
    public function verify(string $secret, string $code, string $replayKey): bool
    {
        $code = preg_replace('/\D/', '', $code);

        if (strlen($code) !== 6) {
            return false;
        }

        $timestamp = $this->engine->verifyKeyNewer($secret, $code, Cache::get($replayKey), 1);

        if ($timestamp === false) {
            return false;
        }

        Cache::put($replayKey, $timestamp === true ? $this->engine->getTimestamp() : $timestamp, 300);

        return true;
    }
}
