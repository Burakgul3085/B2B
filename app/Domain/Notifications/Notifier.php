<?php

namespace App\Domain\Notifications;

use App\Domain\Inventory\Quantity;
use App\Domain\Mail\BusinessMailer;
use App\Domain\Mail\EmailSettingService;
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
            ['Yetkili' => $a->contact_name, 'Telefon' => $a->phone, 'E-posta' => $a->email, 'İl / ilçe' => "{$a->city} / {$a->district}"], $a);
        // Başvuru sahibine otomatik "başvurunuz alındı" e-postası.
        $this->mailer->queue('application_received_applicant', $a->email, ['bayi' => $a->legal_name, 'yetkili' => $a->contact_name], [],
            ['Başvuru tarihi' => $a->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i')], 'dealer_application', $a->id);
    }

    /** Onaylanan başvuru sahibine hesap bilgileri (şifre e-postayla gönderilmez). */
    public function applicationApproved(DealerApplication $a, string $loginEmail, bool $existingAccount): EmailLog
    {
        return $existingAccount
            ? $this->mailer->queue('relationship_added', $loginEmail, [], ['url' => route('dealer.login'), 'label' => 'Giriş yap'], [], 'dealer_application', $a->id)
            : $this->mailer->queue('application_approved', $loginEmail, ['eposta' => $loginEmail], ['url' => route('dealer.login'), 'label' => 'Giriş yap'], [], 'dealer_application', $a->id);
    }

    /** Yeni açılan bayiye hesap bilgileri (şifre e-postayla gönderilmez). */
    public function dealerCreated(DealerRelationship $rel, bool $existingAccount): EmailLog
    {
        $email = $this->dealerEmail($rel);

        return $existingAccount
            ? $this->mailer->queue('relationship_added', $email, [], ['url' => route('dealer.login'), 'label' => 'Giriş yap'], [], 'dealer_relationship', $rel->id)
            : $this->mailer->queue('dealer_activation', $email, ['eposta' => $email], ['url' => route('dealer.login'), 'label' => 'Giriş yap'], [], 'dealer_relationship', $rel->id);
    }

    public function applicationRejected(DealerApplication $a): void
    {
        $this->mailer->queue('application_rejected', $a->email, ['neden' => $a->decision_note], [], [], 'dealer_application', $a->id);
    }

    public function applicationInfoRequested(DealerApplication $a, string $replyUrl): void
    {
        $this->mailer->queue('application_info_requested', $a->email, ['neden' => $a->info_request_note],
            ['url' => $replyUrl, 'label' => 'Bilgileri gönder'], [], 'dealer_application', $a->id);
    }

    /** Başvuran ek bilgiyi web formundan gönderdi. */
    public function applicationInfoReplied(DealerApplication $a): void
    {
        $url = route('business.applications.show', $a);
        $this->toBusiness('application', "Başvuruya ek bilgi geldi: {$a->legal_name}", mb_substr((string) $a->info_reply, 0, 120), $url);
        $this->mailBusiness('application_info_replied', ['bayi' => $a->legal_name], ['url' => $url, 'label' => 'Başvuruyu incele'], [], $a);
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

    /**
     * Onay bildirimi: ödeme bekleniyorsa "onaylanan tutarı öde" e-postası; değişen kalemler (istenen → onaylanan)
     * e-postada ayrıca listelenir.
     */
    public function orderApproved(Order $order): void
    {
        $order->loadMissing('items');
        $changes = $order->approvalChanges();
        if (! $order->awaitingPayment()) {
            $this->orderStatusChanged($order, $changes ? 'Bazı kalemler istediğinizden farklı miktarda onaylandı; tutar onaylanan miktarlara göre hesaplandı.' : null);

            return;
        }

        $url = route('dealer.orders.show', $order);
        $total = Money::format($order->grand_total);
        $change = '';
        if ($changes) {
            $change = "\nDİKKAT: Siparişinizde değişiklik yapıldı. Bazı kalemler istediğinizden farklı miktarda onaylandı";
            $change .= $order->requested_total !== null ? ' (sipariş tutarınız '.Money::format($order->requested_total).' idi).' : '.';
            $change .= " Lütfen yalnızca onaylanan tutarı ({$total}) ödeyin.\n";
        }
        $this->toDealer($order->relationship, 'order', "Sipariş {$order->order_no} onaylandı: {$total} ödeyin",
            $changes ? 'Bazı kalemler farklı miktarda onaylandı. Onaylanan tutarı ödeyin.' : 'Ödemeniz alındıktan sonra siparişiniz hazırlanır.', $url);

        $details = ['Ödenecek tutar' => $total, 'Ödeme yöntemi' => $order->paymentMethodLabel()];
        if ($changes && $order->requested_total !== null) {
            $details['Sipariş ettiğiniz tutar'] = Money::format($order->requested_total);
        }
        foreach ($changes as $c) {
            $details['Değişti: '.Str::limit($c['name'], 60)] = Quantity::format($c['requested'])." {$c['unit']} istendi → ".Quantity::format($c['approved'])." {$c['unit']} onaylandı";
        }
        if ($order->delivery_planned_on) {
            $details['Planlanan teslim'] = $order->delivery_planned_on->format('d.m.Y');
        }
        $this->mailer->queue('order_payment_due', $this->dealerEmail($order->relationship),
            ['siparis_no' => $order->order_no, 'tutar' => $total, 'degisiklik' => $change], ['url' => $url, 'label' => 'Siparişi görüntüle ve öde'], $details, 'order', $order->id);
    }

    /** Fazla / geç kart ödemesi: otomatik iade edildi veya edilemedi (toptancıya e-posta + bildirim, bayiye bildirim). */
    public function duplicatePayment(Order $order, string $amount, bool $refunded): void
    {
        $dealer = $order->relationship->dealer->legal_name;
        $total = Money::format($amount);
        $state = $refunded ? 'Otomatik olarak karta iade edildi.' : 'Otomatik iade EDİLEMEDİ; iyzico panelinden iade edin.';
        $this->toBusiness('order', ($refunded ? 'Fazla ödeme iade edildi: ' : 'DİKKAT, fazla ödeme iade edilemedi: ').$order->order_no, "{$dealer} · {$total}", route('business.orders.show', $order));
        $this->mailBusiness('payment_duplicate', ['bayi' => $dealer, 'siparis_no' => $order->order_no, 'tutar' => $total, 'durum' => $state],
            ['url' => route('business.orders.show', $order), 'label' => 'Siparişi görüntüle'], ['Tutar' => $total, 'Durum' => $state], $order);
        $this->toDealer($order->relationship, 'order', "Fazla ödemeniz: {$order->order_no}",
            $refunded ? "{$total} tutarındaki ikinci ödemeniz kartınıza iade edildi." : "{$total} tutarındaki ikinci ödemeniz en kısa sürede iade edilecek.", route('dealer.orders.show', $order));
    }

    /** Ödeme alındı: toptancıya bildirim + e-posta, bayiye uygulama içi bildirim. */
    public function orderPaid(Order $order, string $method): void
    {
        $dealer = $order->relationship->dealer->legal_name;
        $total = Money::format($order->grand_total);
        $this->toBusiness('order', "Ödeme alındı: {$order->order_no}", "{$dealer} · {$total} · {$method}", route('business.orders.show', $order));
        $this->mailBusiness('order_paid', ['bayi' => $dealer, 'siparis_no' => $order->order_no, 'tutar' => $total, 'yontem' => $method],
            ['url' => route('business.orders.show', $order), 'label' => 'Siparişi görüntüle'], ['Tutar' => $total, 'Yöntem' => $method], $order);
        $this->toDealer($order->relationship, 'order', "Ödemeniz alındı: {$order->order_no}", "{$total} · {$method}. Siparişiniz hazırlanacak.", route('dealer.orders.show', $order));
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
            $this->toDealer($rel, 'announcement', $announcement->title, Str::limit($announcement->body, 200), route('dealer.announcements.index'));
            if ($announcement->send_email && $rel->dealer->user?->status === 'active') {
                $this->mailer->queue('announcement', $rel->dealer->user->email, ['baslik' => $announcement->title, 'metin' => $announcement->body],
                    ['url' => route('dealer.announcements.index'), 'label' => 'Duyuruyu görüntüle'], [], 'announcement', $announcement->id);
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
        // Toptancıya gelen bildirimler işletmenin ana e-posta adresine (E-posta Ayarları) düşer.
        $to = EmailSettingService::inboxAddress(BusinessUser::where('business_id', app(TenantContext::class)->businessId())->value('email'));
        if ($to) {
            $this->mailer->queue($key, $to, $vars, $action, $details, $related ? Str::snake(class_basename($related)) : null, $related?->getKey());
        }
    }

    private function dealerEmail(DealerRelationship $rel): string
    {
        return DealerUser::where('dealer_id', $rel->dealer_id)->value('email');
    }
}
