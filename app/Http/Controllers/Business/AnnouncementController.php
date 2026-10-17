<?php

namespace App\Http\Controllers\Business;

use App\Domain\Audit\AuditLogger;
use App\Domain\Files\DocumentUploader;
use App\Domain\Notifications\Notifier;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Attachment;
use App\Models\DealerGroup;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnnouncementController extends Controller
{
    /** Duyuru başına en fazla dosya sayısı ve dosya boyutu. */
    public const MAX_FILES = 5;

    public const MAX_BYTES = 10 * 1024 * 1024;

    /** Tek istekte gönderilebilecek toplam (PHP post_max_size ile uyumlu olmalı). */
    public const MAX_TOTAL_BYTES = 20 * 1024 * 1024;

    public function index(): View
    {
        return view('business.announcements', [
            'announcements' => Announcement::with(['group', 'attachments'])->latest('id')->paginate(20),
            'groups' => DealerGroup::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request, Notifier $notifier, AuditLogger $audit, DocumentUploader $documents): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:5000'],
            'dealer_group_id' => ['nullable', 'integer', Rule::exists('dealer_groups', 'id')->where('business_id', $request->user()->business_id)],
            'files' => ['nullable', 'array', 'max:'.self::MAX_FILES],
            'files.*' => ['file', 'max:'.(self::MAX_BYTES / 1024)],
        ], [], ['title' => 'başlık', 'body' => 'duyuru metni', 'dealer_group_id' => 'bayi grubu', 'files' => 'ekler', 'files.*' => 'ek dosya']);

        // Duyuru ve ekleri birlikte kaydedilir: bir dosya reddedilirse duyuru da yayımlanmaz.
        try {
            $announcement = DB::transaction(function () use ($data, $request, $documents) {
                $announcement = Announcement::create(collect($data)->only(['title', 'body', 'dealer_group_id'])->all() + [
                    'send_email' => $request->boolean('send_email'), 'published_at' => now(), 'created_by' => $request->user()->id,
                ]);

                foreach ($request->file('files', []) as $file) {
                    $documents->store($file, 'businesses/'.$announcement->business_id.'/announcements/'.$announcement->id,
                        'announcement', $announcement->id, 'business', $request->user()->id, DocumentUploader::WITH_OFFICE, self::MAX_BYTES);
                }

                return $announcement;
            });
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['files' => $e->getMessage()]);
        }

        $count = $notifier->announcement($announcement);
        $audit->log('announcement.published', $announcement, [], ['recipients' => $count, 'attachments' => $announcement->attachments()->count()]);

        return back()->with('status', "Duyuru {$count} bayiye yayımlandı.".($announcement->send_email ? ' E-postalar kuyruğa alındı.' : ''));
    }

    public function attachment(Announcement $announcement, Attachment $attachment): StreamedResponse
    {
        abort_unless($attachment->attachable_type === 'announcement' && (int) $attachment->attachable_id === $announcement->id, 404);
        abort_unless(Storage::disk('local')->exists($attachment->stored_path), 404);

        return Storage::disk('local')->download($attachment->stored_path, $attachment->original_name, [
            'Content-Type' => $attachment->mime, 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
