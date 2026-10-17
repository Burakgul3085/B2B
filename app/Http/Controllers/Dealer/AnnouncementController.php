<?php

namespace App\Http\Controllers\Dealer;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Dealer\Concerns\UsesDealerContext;
use App\Models\Announcement;
use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Bayinin duyuruları: yalnızca herkese veya bayinin grubuna yayımlananlar ve ekleri. */
class AnnouncementController extends Controller
{
    use UsesDealerContext;

    public function index(): View
    {
        return view('dealer.announcements', [
            'announcements' => Announcement::visibleTo($this->relationship())->with('attachments')->latest('published_at')->paginate(15),
        ]);
    }

    public function attachment(Announcement $announcement, Attachment $attachment): StreamedResponse
    {
        abort_unless(Announcement::visibleTo($this->relationship())->whereKey($announcement->id)->exists(), 404);
        abort_unless($attachment->attachable_type === 'announcement' && (int) $attachment->attachable_id === $announcement->id, 404);
        abort_unless(Storage::disk('local')->exists($attachment->stored_path), 404);

        return Storage::disk('local')->download($attachment->stored_path, $attachment->original_name, [
            'Content-Type' => $attachment->mime, 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
