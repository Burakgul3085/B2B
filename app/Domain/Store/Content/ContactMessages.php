<?php

namespace App\Domain\Store\Content;

use App\Domain\Audit\AuditLogger;
use App\Domain\Store\Customers\PhoneNumber;
use App\Domain\Store\Mail\StoreEmailRenderer;
use App\Domain\Store\Mail\StoreMailer;
use App\Domain\Store\Notifications\AdminNotifier;
use App\Models\Store\StoreAdmin;
use App\Models\Store\StoreContactMessage;
use App\Models\Store\StoreContactReply;
use App\Models\Store\StoreSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * İletişim formu: ziyaretçi mesajı yönetimdeki Mesajlar kutusuna düşer, bildirim adreslerine e-posta gider ve
 * ziyaretçi e-posta yazdıysa ona "mesajınız ulaştı" e-postası gönderilir. Yönetici Mesajlar ekranından e-postayla
 * yanıt yazabilir (yanıtlar saklanır) ya da telefon/WhatsApp ile döndükten sonra yanıtlandı olarak işaretler.
 * Müşteriye giden e-postalarda Yanıtla adresi mağazanın iletişim e-postasıdır.
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
        $this->acknowledge($message);

        return $message;
    }

    /**
     * Ziyaretçiye otomatik "mesajınız ulaştı" e-postası. Formdan yazılan ad ve mesaj metni e-postaya konmaz:
     * form, başkasının adresine istenmeyen içerik göndermek için kullanılamasın.
     */
    private function acknowledge(StoreContactMessage $message): void
    {
        if (! $message->email) {
            return;
        }
        $name = config('store.name');
        $email = $message->email;
        $content = [
            'eyebrow' => 'İletişim formu',
            'title' => 'Mesajınız bize ulaştı',
            'intro' => "Merhaba,\n\n{$name} iletişim formundan gönderdiğiniz mesaj bize ulaştı. Ekibimiz mesajınızı inceleyip en kısa sürede size dönüş yapacak.",
            'rows' => [['Konu', StoreContactMessage::SUBJECTS[$message->subject] ?? $message->subject], ['Tarih', $message->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i')]],
            'outro' => 'Bu e-posta mesajınızın alındığını bildirmek için otomatik gönderildi. Bu formu siz doldurmadıysanız e-postayı yok sayabilirsiniz.',
        ];

        dispatch(fn () => app(self::class)->mail($email, "Mesajınızı aldık — {$name}", $content))->afterResponse();
    }

    /**
     * Yöneticinin e-postayla yanıtı: müşteriye gönderilir, gönderilirse saklanır ve mesaj yanıtlandı olur.
     *
     * @throws ValidationException
     */
    public function reply(StoreContactMessage $message, StoreAdmin $admin, array $input): StoreContactReply
    {
        $data = Validator::make($input, ['body' => ['required', 'string', 'min:2', 'max:5000']], [], ['body' => 'yanıt'])->validate();
        if (! $message->email) {
            throw ValidationException::withMessages(['body' => 'Bu mesajda e-posta adresi yok; telefon veya WhatsApp ile yanıtlayın.']);
        }

        $body = trim($data['body']);
        $subject = StoreContactMessage::SUBJECTS[$message->subject] ?? $message->subject;
        $sent = $this->mail($message->email, 'Mesajınıza yanıt — '.config('store.name'), [
            'eyebrow' => 'Mesajınıza yanıt',
            'title' => "Merhaba {$message->name}",
            'intro' => $body,
            'rows' => [['Konu', $subject], ['Mesaj tarihi', $message->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i')]],
            'outro' => 'Bu e-postayı yanıtlayarak bize yazmaya devam edebilirsiniz.',
        ]);
        if (! $sent) {
            throw ValidationException::withMessages(['body' => 'E-posta gönderilemedi. Mağaza Ayarları → E-posta gönderimi bölümünü kontrol edin.']);
        }

        return DB::transaction(function () use ($message, $admin, $body) {
            $reply = $message->replies()->create(['admin_id' => $admin->id, 'admin_name' => $admin->name, 'body' => $body]);
            $message->forceFill(['status' => 'replied', 'read_at' => $message->read_at ?? now(), 'replied_at' => now()])->save();
            $this->audit->log('store.contact_message_replied', $message, [], ['name' => $message->name, 'by_email' => true]);

            return $reply;
        });
    }

    /** Müşteriye mağaza düzeninde e-posta; Yanıtla adresi mağazanın iletişim e-postası. */
    public function mail(string $to, string $subject, array $content): bool
    {
        $mail = app(StoreEmailRenderer::class)->render($subject, $content);
        $replyTo = StoreSetting::get('email');
        $headers = filled($replyTo) && filter_var($replyTo, FILTER_VALIDATE_EMAIL) ? ['Reply-To' => $replyTo] : [];

        return app(StoreMailer::class)->sendHtml($to, $subject, $mail['html'], $mail['text'], $headers, null, $mail['inline']);
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
