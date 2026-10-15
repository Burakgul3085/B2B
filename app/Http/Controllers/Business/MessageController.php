<?php

namespace App\Http\Controllers\Business;

use App\Domain\Messaging\MessagingService;
use App\Http\Controllers\Concerns\HandlesMessaging;
use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Conversation;
use App\Models\DealerRelationship;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MessageController extends Controller
{
    use HandlesMessaging;

    public function __construct(private MessagingService $messaging) {}

    protected function side(): string
    {
        return 'business';
    }

    protected function conversations(): Builder
    {
        return Conversation::query(); // işletme kapsamı global scope ile
    }

    protected function routePrefix(): string
    {
        return 'business';
    }

    public function index(Request $request): View
    {
        return view('messages.index', $this->listConversations($request) + ['area' => \App\Domain\Auth\Area::Business, 'prefix' => 'business']);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $context = $request->validate([
            'dealer' => ['nullable', 'integer'],
            'context' => ['nullable', Rule::in(['order', 'product_request'])],
            'id' => ['nullable', 'integer'],
        ]);

        if (($context['context'] ?? null) && ($existing = Conversation::where('context_type', $context['context'])->where('context_id', $context['id'] ?? 0)->first())) {
            return redirect()->route('business.messages.show', $existing);
        }

        return view('messages.create', [
            'area' => \App\Domain\Auth\Area::Business, 'prefix' => 'business', 'context' => $context,
            'dealers' => DealerRelationship::with('dealer:id,legal_name')->where('status', 'active')->orderBy('dealer_code')->get()
                ->mapWithKeys(fn ($r) => [$r->id => $r->dealer_code.' · '.$r->dealer->legal_name]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateMessage($request) + $request->validate([
            'dealer_relationship_id' => ['required', 'integer', Rule::exists('dealer_relationships', 'id')->where('business_id', $request->user()->business_id)],
            'subject' => ['required', 'string', 'max:160'],
            'context' => ['nullable', Rule::in(['order', 'product_request'])],
            'context_id' => ['nullable', 'integer'],
        ], [], ['dealer_relationship_id' => 'bayi', 'subject' => 'konu']);

        try {
            $conversation = $this->messaging->start(DealerRelationship::findOrFail($data['dealer_relationship_id']), $data['subject'], $data['body'],
                'business', $request->user()->id, $request->file('files', []), $data['context'] ?? null, $data['context_id'] ?? null);
        } catch (DomainException $e) {
            return back()->withErrors(['body' => $e->getMessage()])->withInput();
        }

        return redirect()->route('business.messages.show', $conversation);
    }

    public function show(Conversation $conversation): View
    {
        $this->findConversation($conversation);
        $this->messaging->markRead($conversation, 'business');

        return view('messages.show', [
            'area' => \App\Domain\Auth\Area::Business, 'prefix' => 'business',
            'conversation' => $conversation->load(['relationship.dealer', 'messages.attachments']),
            'counterpart' => $conversation->relationship->dealer->legal_name,
            'counterpartRead' => (int) $conversation->reads()->where('side', 'dealer')->value('last_read_message_id'),
        ]);
    }

    public function reply(Request $request, Conversation $conversation): RedirectResponse
    {
        return $this->replyTo($request, $conversation, $this->messaging);
    }

    public function attachment(Conversation $conversation, Attachment $attachment): StreamedResponse
    {
        return $this->download($conversation, $attachment);
    }
}
