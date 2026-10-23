<?php

namespace App\Domain\Store\Content;

use App\Domain\Audit\AuditLogger;
use App\Domain\Store\Customers\PhoneNumber;
use App\Domain\Store\Notifications\AdminNotifier;
use App\Models\Store\StoreContactMessage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * İletişim formu: ziyaretçi mesajı yönetimdeki Mesajlar kutusuna düşer ve bildirim adreslerine e-posta gider
 * (sistem e-postası tanımlıysa). Yanıt mağazanın kendi e-postası veya telefonuyla verilir; burada yalnızca durum
 * tutulur (yeni → okundu → yanıtlandı).
 */
class ContactMessages
{
    public function __construct(private AuditLogger $audit, private AdminNotifier $notifier) {}

    /** @throws ValidationException */
    public function submit(array $input, ?string $ip, ?string $userAgent): StoreContactMessage
    {
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'subject' => ['required', 'in:'.implode(',', array_keys(StoreContactMessage::SUBJECTS))],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
        ], [], ['name' => 'ad soyad', 'email' => 'e-posta', 'phone' => 'telefon', 'subject' => 'konu', 'message' => 'mesaj'])->validate();

        $phone = null;
        if (filled($data['phone'] ?? null)) {
            $phone = PhoneNumber::normalize($data['phone']);
            if (! $phone) {
                throw ValidationException::withMessages(['phone' => 'Geçerli bir cep telefonu numarası girin (ör. 0532 123 45 67).']);
            }
        }
        if (! $phone && blank($data['email'] ?? null)) {
            throw ValidationException::withMessages(['email' => 'Size dönebilmemiz için e-posta adresinizi veya telefonunuzu yazın.']);
        }

        $message = StoreContactMessage::create([
            'name' => trim($data['name']),
            'email' => filled($data['email'] ?? null) ? mb_strtolower(trim($data['email'])) : null,
            'phone' => $phone,
            'subject' => $data['subject'],
            'message' => trim($data['message']),
            'ip' => $ip,
            'user_agent' => $userAgent ? mb_substr($userAgent, 0, 255) : null,
        ]);
        $this->notifier->contactMessage($message);

        return $message;
    }

    /** Yönetici mesajı açınca okundu sayılır. */
    public function markRead(StoreContactMessage $message): void
    {
        if ($message->status === 'new') {
            $message->forceFill(['status' => 'read', 'read_at' => now()])->save();
        }
    }

    public function markReplied(StoreContactMessage $message): void
    {
        $message->forceFill(['status' => 'replied', 'read_at' => $message->read_at ?? now(), 'replied_at' => now()])->save();
        $this->audit->log('store.contact_message_replied', $message, [], ['name' => $message->name]);
    }

    /** Silme (ör. KVKK silme talebi). İşlem geçmişine yalnızca kimin silindiği yazılır, mesaj içeriği yazılmaz. */
    public function delete(StoreContactMessage $message): void
    {
        $message->delete();
        $this->audit->log('store.contact_message_deleted', $message, ['name' => $message->name, 'subject' => $message->subject]);
    }
}
