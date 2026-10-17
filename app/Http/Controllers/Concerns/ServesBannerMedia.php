<?php

namespace App\Http\Controllers\Concerns;

use App\Models\HomeBanner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Banner medyasını sunar. Dosya yanıtı aralık (Range) isteklerini destekler: tarayıcı videoyu
 * baştan sona indirmeden oynatabilir ve ileri sarabilir. ?v=mobile | poster ile diğer görseller istenir.
 */
trait ServesBannerMedia
{
    protected function bannerMedia(Request $request, HomeBanner $banner): BinaryFileResponse
    {
        [$path, $mime] = match ($request->query('v')) {
            'mobile' => [$banner->mobile_media_path, 'image/webp'],
            'poster' => [$banner->poster_path, 'image/webp'],
            default => [$banner->media_path, $banner->media_mime],
        };

        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
