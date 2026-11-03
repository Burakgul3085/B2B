<?php

namespace App\Domain\Files;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Görsel yanıtı (mağaza ve B2B ortak): dosya doğrudan diskten gönderilir, ETag ve Last-Modified eklenir.
 * Tarayıcının elindeki kopya değişmediyse gövdesiz 304 döner; görsel yeniden indirilmez.
 */
class ImageResponse
{
    public static function make(Request $request, string $path, string $cacheControl): Response
    {
        $absolute = Storage::disk('local')->path($path);
        $mtime = (int) @filemtime($absolute);

        $response = new BinaryFileResponse($absolute, 200, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => $cacheControl,
            'X-Content-Type-Options' => 'nosniff',
        ], false, null, false, false);
        $response->setEtag(substr(hash('xxh128', $path.'|'.$mtime.'|'.(int) @filesize($absolute)), 0, 24));
        $response->setLastModified((new \DateTimeImmutable)->setTimestamp($mtime));
        $response->isNotModified($request);

        return $response;
    }
}
