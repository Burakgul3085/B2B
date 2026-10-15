<?php

namespace App\Http\Controllers\Concerns;

use App\Domain\Messaging\MessagingService;
use App\Models\Attachment;
use App\Models\Conversation;
use App\Models\Message;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Toptancı ve bayi mesaj ekranlarının ortak davranışı. Alan farkı side() ve conversations() ile verilir. */
trait HandlesMessaging
{
    abstract protected function side(): string;

    /** Bu kullanıcının görebileceği konuşmalar. */
    abstract protected function conversations(): Builder;

    abstract protected function routePrefix(): string;

    protected function listConversations(Request $request): array
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'unread' => ['nullable', 'boolean']]);

        $conversations = $this->conversations()
            ->with(['relationship.dealer:id,legal_name', 'latestMessage'])
            ->when($filters['unread'] ?? false, fn ($q) => MessagingService::unreadScope($q, $this->side()))
            ->when($filters['q'] ?? null, function ($q, $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($q) => $q->where('subject', 'like', $like)
                    ->orWhereHas('messages', fn ($m) => $m->where('body', 'like', $like)));
            })
            ->orderByDesc('last_message_at')
            ->paginate(25)->withQueryString();

        $unreadIds = MessagingService::unreadScope($this->conversations(), $this->side())->pluck('id')->flip();

        return compact('conversations', 'filters', 'unreadIds');
    }

    protected function findConversation(Conversation $conversation): Conversation
    {
        abort_unless($this->conversations()->whereKey($conversation->id)->exists(), 404);

        return $conversation;
    }

    protected function replyTo(Request $request, Conversation $conversation, MessagingService $messaging): RedirectResponse
    {
        $this->findConversation($conversation);
        $data = $this->validateMessage($request);

        try {
            $messaging->reply($conversation, $this->side(), $request->user()->id, $data['body'], $request->file('files', []));
        } catch (DomainException $e) {
            return back()->withErrors(['body' => $e->getMessage()])->withInput();
        }

        return redirect()->route($this->routePrefix().'.messages.show', $conversation);
    }

    protected function validateMessage(Request $request): array
    {
        return $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'files' => ['nullable', 'array', 'max:'.MessagingService::MAX_FILES],
            'files.*' => ['file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
        ], [], ['body' => 'mesaj', 'files.*' => 'dosya']);
    }

    protected function download(Conversation $conversation, Attachment $attachment): StreamedResponse
    {
        $this->findConversation($conversation);
        abort_unless($attachment->attachable_type === 'message'
            && Message::whereKey($attachment->attachable_id)->where('conversation_id', $conversation->id)->exists(), 404);
        abort_unless(Storage::disk('local')->exists($attachment->stored_path), 404);

        return Storage::disk('local')->download($attachment->stored_path, $attachment->original_name, [
            'Content-Type' => $attachment->mime, 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
