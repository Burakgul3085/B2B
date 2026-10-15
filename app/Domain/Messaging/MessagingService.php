<?php

namespace App\Domain\Messaging;

use App\Domain\Files\DocumentUploader;
use App\Domain\Notifications\Notifier;
use App\Domain\Tenancy\TenantContext;
use App\Models\Conversation;
use App\Models\ConversationRead;
use App\Models\DealerRelationship;
use App\Models\Message;
use App\Models\Order;
use App\Models\ProductRequest;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Toptancı ↔ bayi mesajlaşması. Katılımcılar sabittir (işletme + ilişkideki bayi); başka işletme veya
 * başka bayi konuşmayı göremez. Mesajlar düz metindir ve değiştirilemez.
 */
class MessagingService
{
    public const MAX_FILES = 3;

    public function __construct(private TenantContext $tenant, private DocumentUploader $documents, private Notifier $notifier) {}

    /**
     * Yeni konuşma başlatır. Siparişe/talebe bağlı konuşma zaten varsa mesaj ona eklenir.
     *
     * @param  UploadedFile[]  $files
     */
    public function start(DealerRelationship $rel, string $subject, string $body, string $side, int $senderId, array $files = [], ?string $contextType = null, ?int $contextId = null): Conversation
    {
        $this->assertContext($rel, $contextType, $contextId);

        if ($contextType && ($existing = Conversation::where('dealer_relationship_id', $rel->id)->where('context_type', $contextType)->where('context_id', $contextId)->first())) {
            $this->reply($existing, $side, $senderId, $body, $files);

            return $existing;
        }

        $conversation = Conversation::create([
            'dealer_relationship_id' => $rel->id, 'subject' => $subject,
            'context_type' => $contextType, 'context_id' => $contextId,
        ]);
        $this->reply($conversation, $side, $senderId, $body, $files);

        return $conversation;
    }

    /** @param  UploadedFile[]  $files */
    public function reply(Conversation $conversation, string $side, int $senderId, string $body, array $files = []): Message
    {
        if (trim($body) === '') {
            throw new DomainException('Mesaj boş olamaz.');
        }
        if (count($files) > self::MAX_FILES) {
            throw new DomainException('Bir mesaja en fazla '.self::MAX_FILES.' dosya eklenebilir.');
        }

        $message = DB::transaction(function () use ($conversation, $side, $senderId, $body, $files) {
            $message = Message::create(['conversation_id' => $conversation->id, 'sender_side' => $side, 'sender_id' => $senderId, 'body' => trim($body)]);

            foreach ($files as $file) {
                $this->documents->store($file, 'businesses/'.$this->tenant->businessId().'/messages/'.$conversation->id, 'message', $message->id, $side, $senderId);
            }

            $conversation->update(['last_message_at' => now()]);
            $this->markRead($conversation, $side, $message->id);

            return $message;
        });

        $this->notifier->newMessage($message);

        return $message;
    }

    public function markRead(Conversation $conversation, string $side, ?int $upTo = null): void
    {
        $upTo ??= (int) Message::where('conversation_id', $conversation->id)->max('id');

        ConversationRead::updateOrCreate(['conversation_id' => $conversation->id, 'side' => $side], ['last_read_message_id' => $upTo]);
    }

    /**
     * Okunmamış mesajı olan konuşmalar: karşı taraftan gelen ve okunma işaretinden sonraki mesajlar.
     */
    public static function unreadScope(Builder $query, string $side): Builder
    {
        $other = $side === 'business' ? 'dealer' : 'business';

        return $query->whereExists(fn ($q) => $q->selectRaw('1')->from('messages')
            ->whereColumn('messages.conversation_id', 'conversations.id')
            ->where('messages.sender_side', $other)
            ->whereRaw('messages.id > COALESCE((SELECT cr.last_read_message_id FROM conversation_reads cr WHERE cr.conversation_id = conversations.id AND cr.side = ?), 0)', [$side]));
    }

    private function assertContext(DealerRelationship $rel, ?string $type, ?int $id): void
    {
        if ($type === null) {
            return;
        }

        $valid = match ($type) {
            'order' => Order::whereKey($id)->where('dealer_relationship_id', $rel->id)->exists(),
            'product_request' => ProductRequest::whereKey($id)->where('dealer_relationship_id', $rel->id)->exists(),
            default => false,
        };

        if (! $valid) {
            throw new DomainException('Mesajın bağlı olduğu kayıt bulunamadı.');
        }
    }
}
