<?php

namespace App\Domain\Notifications;

use App\Domain\Mail\BusinessMailer;
use App\Domain\Support\Money;
use App\Domain\Tenancy\TenantContext;
use App\Models\Announcement;
use App\Models\AppNotification;
use App\Models\BusinessUser;
use App\Models\DealerApplication;
use App\Models\DealerRelationship;
use App\Models\DealerUser;
use App\Models\EmailLog;
use App\Models\Message;
use App\Models\Order;
use App\Models\ProductRequest;
use Illuminate\Support\Str;

/**
 * Olay → uygulama içi bildirim + (ayar varsa) kurumsal e-posta. İşlem commit edildikten sonra çağrılır;
 * e-posta gönderilemese bile iş işlemi geri alınmaz.
 */
class Notifier
{
    public function __construct(private BusinessMailer $mailer) {}

    /* ---------------- Bayilik ---------------- */

    public function applicationReceived(DealerApplication $a): void
    {
        $url = route('business.applications.show', $a);
        $this->toBusiness('application', "Yeni bayilik başvurusu: {$a->legal_name}", "{$a->city} / {$a->district}", $url);
        $this->mailBusiness('application_received', ['bayi' => $a->legal_name], ['url' => $url, 'label' => 'Başvuruyu incele'],
            ['Yetkili' => $a->contact_name, 'Telefon' => $a->phone, 'E-posta' => $a->email], $a);
    }

    /** @return EmailLog e-postanın gönderilip gönderilmeyeceğini (status) çağıran bilir */
    public function applicationApproved(DealerApplication $a, ?string $activationUrl): EmailLog
    {
        return $activationUrl
            ? $this->mailer->queue('application_approved', $a->email, [], ['url' => $activationUrl, 'label' => 'Hesabımı etkinleştir'], [], 'dealer_application', $a->id)
            : $this->mailer->queue('relationship_added', $a->email, [], ['url' => route('dealer.login'), 'label' => 'Giriş yap'], [], 'dealer_application', $a->id);
    }

    public function dealerCreated(DealerRelationship $rel, ?string $activationUrl): EmailLog
    {
        $email = $this->dealerEmail($rel);

        return $activationUrl
            ? $this->mailer->queue('dealer_activation', $email, [], ['url' => $activationUrl, 'label' => 'Parolamı belirle'], [], 'dealer_relationship', $rel->id)
            : $this->mailer->queue('relationship_added', $email, [], ['url' => route('dealer.login'), 'label' => 'Giriş yap'], [], 'dealer_relationship', $rel->id);
    }

    /** Toptancının aktif bayisi için başlattığı parola sıfırlama (bağlantı toptancıya gösterilmez). */
    public function dealerPasswordReset(DealerRelationship $rel, string $resetUrl): EmailLog
    {
        return $this->mailer->queue('dealer_password_reset', $this->dealerEmail($rel), [], ['url' => $resetUrl, 'label' => 'Yeni parola belirle'], [], 'dealer_relationship', $rel->id);
    }

    public function applicationRejected(DealerApplication $a): void
    {
        $this->mailer->queue('application_rejected', $a->email, ['neden' => $a->decision_note], [], [], 'dealer_application', $a->id);
    }

    public function applicationInfoRequested(DealerApplication $a): void
    {
        $this->mailer->queue('application_info_requested', $a->email, ['neden' => $a->info_request_note], [], [], 'dealer_application', $a->id);
    }

    /* ---------------- Sipariş ---------------- */

    public function orderSubmitted(Order $order): void
    {
        $dealer = $order->relationship->dealer->legal_name;
        $url = route('business.orders.show', $order);
        $this->toBusiness('order', "Yeni sipariş {$order->order_no}", "{$dealer} · ".Money::format($order->grand_total), $url);
        $this->mailBusiness('order_submitted', ['bayi' => $dealer, 'siparis_no' => $order->order_no, 'tutar' => Money::format($order->grand_total)],
            ['url' => $url, 'label' => 'Siparişi görüntüle'], ['Kalem' => (string) $order->items()->count(), 'Genel toplam' => Money::format($order->grand_total)], $order);
    }

    public function orderStatusChanged(Order $order, ?string $note = null): void
    {
        $status = $order->statusLabel();
        $url = route('dealer.orders.show', $order);
        $reason = $note ?? match ($order->status) {
            'rejected' => 'Ret nedeni: '.$order->rejected_reason,
            'cancelled' => 'İptal nedeni: '.$order->cancel_reason,
            default => '',
        };

        $this->toDealer($order->relationship, 'order', "Sipariş {$order->order_no}: {$status}", Str::limit($reason, 200), $url);

        $details = ['Genel toplam' => Money::format($order->grand_total)];
        if ($order->delivery_planned_on) {
            $details['Planlanan teslim'] = $order->delivery_planned_on->format('d.m.Y');
        }
        $this->mailer->queue('order_status', $this->dealerEmail($order->relationship),
            ['siparis_no' => $order->order_no, 'durum' => $status, 'neden' => $reason], ['url' => $url, 'label' => 'Siparişi görüntüle'], $details, 'order', $order->id);
    }

    public function cancelRequested(Order $order): void
    {
        $dealer = $order->relationship->dealer->legal_name;
        $url = route('business.orders.show', $order);
        $this->toBusiness('order', "İptal talebi: {$order->order_no}", "{$dealer}: {$order->cancel_reason}", $url);
        $this->mailBusiness('cancel_requested', ['bayi' => $dealer, 'siparis_no' => $order->order_no, 'neden' => $order->cancel_reason], ['url' => $url, 'label' => 'Talebi değerlendir'], [], $order);
    }

    public function orderCancelledByDealer(Order $order): void
    {
        $this->toBusiness('order', "Sipariş iptal edildi: {$order->order_no}", $order->relationship->dealer->legal_name.': '.$order->cancel_reason, route('business.orders.show', $order));
    }

    /* ---------------- Mesaj ---------------- */

    public function newMessage(Message $message): void
    {
        $conversation = $message->conversation()->with('relationship.dealer')->first();
        $preview = Str::limit($message->body, 140);

        if ($message->sender_side === 'dealer') {
            $url = route('business.messages.show', $conversation);
            $sender = $conversation->relationship->dealer->legal_name;
            $this->toBusiness('message', "Yeni mesaj: {$conversation->subject}", "{$sender}: {$preview}", $url);
            $this->mailBusiness('new_message', ['konu' => $conversation->subject, 'gonderen' => $sender, 'onizleme' => $preview], ['url' => $url, 'label' => 'Mesajı oku'], [], $conversation);
        } else {
            $url = route('dealer.messages.show', $conversation);
            $sender = $conversation->relationship->business?->display_name ?? 'Toptancınız';
            $this->toDealer($conversation->relationship, 'message', "Yeni mesaj: {$conversation->subject}", $preview, $url);
            $this->mailer->queue('new_message', $this->dealerEmail($conversation->relationship),
                ['konu' => $conversation->subject, 'gonderen' => $sender, 'onizleme' => $preview], ['url' => $url, 'label' => 'Mesajı oku'], [], 'conversation', $conversation->id);
        }
    }

    /* ---------------- Ürün talebi ---------------- */

    public function requestUpdated(ProductRequest $request): void
    {
        $product = $request->product->name;
        $status = ProductRequest::STATUSES[$request->status];
        $url = route('dealer.requests.index');
        $this->toDealer($request->relationship, 'request', "{$product}: {$status}", $request->response_note, $url);
        $this->mailer->queue('request_update', $this->dealerEmail($request->relationship),
            ['urun' => $product, 'durum' => $status, 'neden' => $request->response_note], ['url' => $url, 'label' => 'Taleplerim'], [], 'product_request', $request->id);
    }

    public function stockArrived(ProductRequest $request): void
    {
        $product = $request->product->name;
        $url = route('dealer.catalog.show', $request->product_id);
        $this->toDealer($request->relationship, 'request', "{$product} stoğa girdi", 'Sipariş vermek için ürüne gidin.', $url);
        $this->mailer->queue('stock_arrived', $this->dealerEmail($request->relationship), ['urun' => $product], ['url' => $url, 'label' => 'Ürüne git'], [], 'product_request', $request->id);
    }

    /* ---------------- Cari ve duyuru ---------------- */

    public function paymentReminder(DealerRelationship $rel, string $overdue, string $dueSoon): EmailLog
    {
        $url = route('dealer.account.statement');
        $this->toDealer($rel, 'ledger', 'Cari hesap vade hatırlatması', 'Vadesi geçmiş: '.Money::format($overdue).' · Yaklaşan: '.Money::format($dueSoon), $url);

        return $this->mailer->queue('payment_reminder', $this->dealerEmail($rel),
            ['gecikmis' => Money::format($overdue), 'yaklasan' => Money::format($dueSoon)], ['url' => $url, 'label' => 'Ekstreyi görüntüle'],
            ['Vadesi geçmiş' => Money::format($overdue), '7 gün içinde' => Money::format($dueSoon)], 'dealer_relationship', $rel->id);
    }

    public function announcement(Announcement $announcement): int
    {
        $relationships = DealerRelationship::where('status', 'active')
            ->when($announcement->dealer_group_id, fn ($q, $g) => $q->where('dealer_group_id', $g))
            ->with('dealer.user')->get();

        foreach ($relationships as $rel) {
            $this->toDealer($rel, 'announcement', $announcement->title, Str::limit($announcement->body, 200), route('dealer.dashboard'));
            if ($announcement->send_email && $rel->dealer->user?->status === 'active') {
                $this->mailer->queue('announcement', $rel->dealer->user->email, ['baslik' => $announcement->title, 'metin' => $announcement->body],
                    ['url' => route('dealer.dashboard'), 'label' => 'Panele git'], [], 'announcement', $announcement->id);
            }
        }

        return $relationships->count();
    }

    /* ---------------- Yardımcılar ---------------- */

    private function toBusiness(string $type, string $title, ?string $body, string $url): void
    {
        AppNotification::create(['recipient_side' => 'business', 'type' => $type, 'title' => Str::limit($title, 200, ''), 'body' => $body ? Str::limit($body, 500, '') : null, 'url' => $url]);
    }

    private function toDealer(DealerRelationship $rel, string $type, string $title, ?string $body, string $url): void
    {
        AppNotification::create([
            'recipient_side' => 'dealer', 'dealer_relationship_id' => $rel->id, 'type' => $type,
            'title' => Str::limit($title, 200, ''), 'body' => $body ? Str::limit($body, 500, '') : null, 'url' => $url,
        ]);
    }

    private function mailBusiness(string $key, array $vars, array $action, array $details, $related): void
    {
        $to = BusinessUser::where('business_id', app(TenantContext::class)->businessId())->where('status', 'active')->value('email');
        if ($to) {
            $this->mailer->queue($key, $to, $vars, $action, $details, $related ? Str::snake(class_basename($related)) : null, $related?->getKey());
        }
    }

    private function dealerEmail(DealerRelationship $rel): string
    {
        return DealerUser::where('dealer_id', $rel->dealer_id)->value('email');
    }
}
