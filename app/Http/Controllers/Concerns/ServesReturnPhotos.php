<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Attachment;
use App\Models\OrderReturnRequest;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** İade talebi fotoğrafı: yalnızca o talebe ait dosya, web kökü dışından, yetki kontrolünden sonra gösterilir. */
trait ServesReturnPhotos
{
    protected function streamPhoto(OrderReturnRequest $returnRequest, Attachment $attachment): StreamedResponse
    {
        abort_unless($attachment->attachable_type === 'order_return_request' && $attachment->attachable_id === $returnRequest->id, 404);
        abort_unless(Storage::disk('local')->exists($attachment->stored_path), 404);

        return Storage::disk('local')->response($attachment->stored_path, $attachment->original_name, [
            'Content-Type' => $attachment->mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=600',
        ]);
    }
}
