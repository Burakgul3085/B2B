<?php

namespace App\Http\Controllers\Dealer;

use App\Domain\Messaging\MessagingService;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Concerns\HandlesMessaging;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Dealer\Concerns\UsesDealerContext;
use App\Models\Attachment;
use App\Models\Conversation;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MessageController extends Controller
{
    use HandlesMessaging, UsesDealerContext;

    public function __construct(private MessagingService $messaging) {}

    protected function side(): string
    {
        return 'dealer';
    }

    /** Yalnızca seçili toptancı ile bu bayinin konuşmaları. */
    protected function conversations(): Builder
    {
        return Conversation::where('dealer_relationship_id', app(TenantContext::class)->dealerRelationshipId());
    }

    protected function routePrefix(): string
    {
        return 'dealer';
    }

    public function index(Request $request): View
    {
        return view('messages.index', $this->listConversations($request) + ['area' => \App\Domain\Auth\Area::Dealer, 'prefix' => 'dealer']);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $context = $request->validate(['context' => ['nullable', Rule::in(['order', 'product_request'])], 'id' => ['nullable', 'integer']]);

        if (($context['context'] ?? null) && ($existing = $this->conversations()->where('context_type', $context['context'])->where('context_id', $context['id'] ?? 0)->first())) {
            return redirect()->route('dealer.messages.show', $existing);
        }

        return view('messages.create', ['area' => \App\Domain\Auth\Area::Dealer, 'prefix' => 'dealer', 'context' => $context, 'dealers' => collect()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateMessage($request) + $request->validate([
            'subject' => ['required', 'string', 'max:160'],
            'context' => ['nullable', Rule::in(['order', 'product_request'])],
            'context_id' => ['nullable', 'integer'],
        ], [], ['subject' => 'konu']);

        try {
            $conversation = $this->messaging->start($this->relationship(), $data['subject'], $data['body'], 'dealer', $request->user()->id,
                $request->file('files', []), $data['context'] ?? null, $data['context_id'] ?? null);
        } catch (DomainException $e) {
            return back()->withErrors(['body' => $e->getMessage()])->withInput();
        }

        return redirect()->route('dealer.messages.show', $conversation);
    }

    public function show(Conversation $conversation): View
    {
        $this->findConversation($conversation);
        $this->messaging->markRead($conversation, 'dealer');

        return view('messages.show', [
            'area' => \App\Domain\Auth\Area::Dealer, 'prefix' => 'dealer',
            'conversation' => $conversation->load('messages.attachments'),
            'counterpart' => app(TenantContext::class)->hasBusiness() ? \App\Models\Business::find(app(TenantContext::class)->businessId())->display_name : '',
            'counterpartRead' => (int) $conversation->reads()->where('side', 'business')->value('last_read_message_id'),
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
