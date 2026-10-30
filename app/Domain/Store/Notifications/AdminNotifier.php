<?php

namespace App\Domain\Store\Notifications;

use App\Domain\Store\Customers\PhoneNumber;
use App\Domain\Store\Mail\StoreMailer;
use App\Domain\Support\Money;
use App\Models\Store\StoreAdmin;
use App\Models\Store\StoreContactMessage;
use App\Models\Store\StoreOrder;
use App\Models\Store\StoreProductVariant;
use App\Models\Store\StoreReturn;
use App\Models\Store\StoreSetting;
use App\Models\Store\StoreStockMovement;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Mağaza yöneticilerine e-posta bildirimleri (docs/21-magaza-m8-raporu.md §3): yeni sipariş, yeni iade talebi,
 * kritik stok, iletişim formu mesajı. Alıcılar Mağaza Ayarları'ndaki listedir; liste boşsa etkin tüm mağaza yöneticileri.
 * Gönderim yanıt döndükten sonra yapılır; e-posta hatası işlemi etkilemez.
 */
class AdminNotifier
{
    /** @return list<string> */
    public function recipients(): array
    {
        $configured = array_values(array_filter(array_map('trim', explode(',', (string) StoreSetting::get('notify_emails')))));

        return $configured ?: StoreAdmin::where('is_active', true)->orderBy('id')->pluck('email')->all();
    }

    public function newOrder(StoreOrder $order): void
    {
        if (! StoreSetting::flag('notify_orders')) {
            return;
        }
        $intro = match (true) {
            $order->status === 'awaiting_approval' => 'Kapıda ödemeli ilk sipariş: müşteriyi arayıp teyit edin.',
            $order->status === 'awaiting_transfer' => 'Havale / EFT ile ödenecek yeni sipariş. Ödeme hesaba geçince sipariş sayfasında "Havale alındı" ile onaylayın.',
            $order->payment_method === 'card' => 'Kartla ödemesi alınmış yeni sipariş hazırlanmayı bekliyor.',
            default => 'Kapıda ödemeli yeni sipariş hazırlanmayı bekliyor.',
        };

        $this->send("Yeni sipariş {$order->order_no} — ".Money::format($order->total), 'Yeni sipariş', $intro, [
            ['Müşteri', $order->shipping_name],
            ['Teslimat', $order->shipping_district.' / '.$order->shipping_province],
            ['Ödeme', StoreOrder::PAYMENT_METHODS[$order->payment_method]],
            ['Tutar', Money::format($order->total)],
        ], route('store_admin.orders.show', $order), 'Siparişi aç');
    }

    public function returnRequested(StoreReturn $return, StoreOrder $order): void
    {
        if (! StoreSetting::flag('notify_returns')) {
            return;
        }
        $this->send("Yeni iade talebi {$return->return_no}", 'Yeni iade talebi', "{$order->order_no} numaralı sipariş için iade talebi açıldı.", [
            ['Müşteri', $order->shipping_name],
            ['Neden', StoreReturn::REASONS[$return->reason] ?? $return->reason],
        ], route('store_admin.returns.show', $return), 'Talebi aç');
    }

    /**
     * Satışla kritik stok sınırına inen (önceden üstündeyken) seçenekler için tek e-posta.
     *
     * @param  Collection<int, StoreStockMovement>  $movements
     */
    public function lowStock(Collection $movements): void
    {
        if (! StoreSetting::flag('notify_low_stock') || $movements->isEmpty()) {
            return;
        }
        $variants = StoreProductVariant::with('product:id,name')->whereIn('id', $movements->pluck('variant_id'))->whereNotNull('critical_stock')->get()->keyBy('id');
        $rows = [];
        foreach ($movements as $m) {
            $variant = $variants[$m->variant_id] ?? null;
            $before = $m->balance_after - $m->quantity; // satışta miktar eksi işaretlidir
            if ($variant && $before > $variant->critical_stock && $m->balance_after <= $variant->critical_stock) {
                $rows[] = [$variant->product->name.($variant->option_value ? " ({$variant->option_value})" : '').' · '.$variant->sku, $m->balance_after.' adet kaldı'];
            }
        }
        if ($rows) {
            $this->send('Kritik stok uyarısı', 'Kritik stok', 'Aşağıdaki ürünlerin stoğu belirlediğiniz kritik seviyeye indi.', $rows,
                route('store_admin.stock.index'), 'Stok durumunu aç');
        }
    }

    /** İletişim formundan yeni mesaj. Mesaj metni e-postada kısaltılır; tamamı Mesajlar ekranındadır. */
    public function contactMessage(StoreContactMessage $message): void
    {
        if (! StoreSetting::flag('notify_messages')) {
            return;
        }
        $rows = array_values(array_filter([
            ['Gönderen', $message->name],
            $message->phone ? ['Telefon', PhoneNumber::format($message->phone)] : null,
            $message->email ? ['E-posta', $message->email] : null,
            ['Konu', StoreContactMessage::SUBJECTS[$message->subject] ?? $message->subject],
            ['Mesaj', Str::limit($message->message, 300)],
        ]));
        $this->send("Yeni iletişim mesajı — {$message->name}", 'Yeni iletişim mesajı', 'Mağazanın iletişim sayfasından yeni bir mesaj geldi.', $rows,
            route('store_admin.messages.show', $message), 'Mesajı aç');
    }

    /** @param  list<array{0: string, 1: string}>  $rows */
    public function returnShipped(StoreReturn $return, StoreOrder $order): void
    {
        if (! StoreSetting::flag('notify_returns')) {
            return;
        }
        $rows = [['Müşteri', $order->shipping_name], ['Kargo', $return->cargo_company.' · '.$return->cargo_tracking]];
        if ($return->cargo_fee !== null) {
            $rows[] = ['Müşterinin ödediği kargo', Money::format($return->cargo_fee)];
        }
        $this->send("İade kargoya verildi {$return->return_no}", 'İade kargoya verildi',
            "{$order->order_no} numaralı siparişin iadesi ({$return->return_no}) müşteri tarafından kargoya verildi. Ürünler gelince \"Teslim alındı, iade et\" ile kapatın.",
            $rows, route('store_admin.returns.show', $return), 'İadeyi aç');
    }

    /** Müşteri siparişini kendisi iptal etti (kargodan önce) ya da havalesi alınmış siparişte iptal istedi. */
    public function customerCancelled(StoreOrder $order, bool $requestOnly, ?string $previousStatus = null): void
    {
        if (! StoreSetting::flag('notify_orders')) {
            return;
        }
        $intro = $requestOnly
            ? "Müşteri havalesi alınmış {$order->order_no} numaralı siparişin iptalini istedi. Tutarı bildirdiği hesaba gönderip \"Para iadesi\" ile kaydedin, sonra siparişi iptal edin."
            : "Müşteri {$order->order_no} numaralı siparişini kargoya verilmeden iptal etti. Ürünler stoğa döndü".($order->payment_status === 'refunded' ? ', ödeme kartına iade edildi.' : '.')
                .($previousStatus === 'preparing' ? ' Sipariş hazırlanıyordu: paketlediyseniz ürünleri rafa geri koyun.' : '');
        $rows = [['Müşteri', $order->shipping_name], ['Neden', (string) ($requestOnly ? $order->cancel_request_reason : $order->cancel_reason)], ['Tutar', Money::format($order->total)]];
        if ($requestOnly) {
            $rows[] = ['İade hesabı', trim(chunk_split((string) $order->cancel_refund_iban, 4, ' ')).' · '.$order->cancel_refund_name];
        }
        $this->send(($requestOnly ? 'İptal talebi ' : 'Müşteri iptali ').$order->order_no, $requestOnly ? 'Müşteri iptal istedi' : 'Müşteri siparişi iptal etti',
            $intro, $rows, route('store_admin.orders.show', $order), 'Siparişi aç');
    }

    private function send(string $subject, string $title, string $intro, array $rows, string $url, string $label): void
    {
        $recipients = $this->recipients();
        $content = ['tagline' => 'Mağaza yönetimi', 'eyebrow' => 'Yönetici bildirimi', 'title' => $title, 'intro' => $intro, 'rows' => $rows,
            'action_url' => $url, 'action_label' => $label, 'outro' => 'Bu bildirimleri Mağaza Ayarları → Yönetici bildirimleri bölümünden değiştirebilirsiniz.'];
        dispatch(function () use ($recipients, $subject, $content) {
            foreach ($recipients as $to) {
                app(StoreMailer::class)->send($to, $subject, $content);
            }
        })->afterResponse();
    }
}
