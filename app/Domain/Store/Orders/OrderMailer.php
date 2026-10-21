<?php

namespace App\Domain\Store\Orders;

use App\Domain\Mail\SystemMailer;
use App\Domain\Support\Money;
use App\Models\Store\StoreOrder;

/**
 * Müşteriye sipariş e-postaları (sistem e-posta hesabıyla). Gönderim yanıt döndükten sonra yapılır;
 * e-posta sunucusundaki gecikme veya hata siparişi etkilemez.
 */
class OrderMailer
{
    public function __construct(private SystemMailer $mailer) {}

    public function placed(StoreOrder $order): void
    {
        $intro = $order->payment_method === 'cod'
            ? ($order->status === 'awaiting_approval'
                ? 'Siparişinizi aldık. Kısa süre içinde sizi arayarak teyit edeceğiz; ödemeyi teslimatta yapacaksınız.'
                : 'Siparişinizi aldık ve hazırlamaya başladık. Ödemeyi teslimatta yapacaksınız.')
            : 'Ödemeniz alındı, siparişinizi hazırlamaya başladık.';

        $this->send($order, "Siparişiniz alındı — {$order->order_no}", 'Siparişiniz için teşekkürler', $intro, true);
    }

    public function shipped(StoreOrder $order): void
    {
        $intro = $order->delivery_method === 'cargo'
            ? "Siparişiniz kargoya verildi. Kargo firması: {$order->carrier}, takip numarası: {$order->tracking_number}."
            : 'Siparişiniz teslimat için yola çıktı.';

        $this->send($order, "Siparişiniz yolda — {$order->order_no}", 'Siparişiniz yolda', $intro, false);
    }

    public function cancelled(StoreOrder $order): void
    {
        $this->send($order, "Siparişiniz iptal edildi — {$order->order_no}", 'Siparişiniz iptal edildi',
            "{$order->order_no} numaralı siparişiniz iptal edildi. Sorularınız için bize ulaşabilirsiniz.", false);
    }

    private function send(StoreOrder $order, string $subject, string $title, string $intro, bool $withItems): void
    {
        $rows = [];
        if ($withItems) {
            foreach ($order->items()->get() as $item) {
                $rows[] = [$item->product_name.($item->option_label ? " ({$item->option_label})" : '')." × {$item->quantity}", Money::format($item->line_total)];
            }
            if (bccomp((string) $order->shipping_fee, '0', 2) > 0) {
                $rows[] = ['Teslimat', Money::format($order->shipping_fee)];
            }
            if (bccomp((string) $order->cod_fee, '0', 2) > 0) {
                $rows[] = ['Kapıda ödeme bedeli', Money::format($order->cod_fee)];
            }
            $rows[] = ['Toplam (KDV dahil)', Money::format($order->total)];
        }

        $content = [
            'brand' => config('store.name'),
            'title' => $title,
            'intro' => $intro,
            'rows' => $rows,
            'action_url' => route('store.order', ['orderNo' => $order->order_no, 'token' => $order->access_token]),
            'action_label' => 'Siparişi görüntüle',
            'outro' => "Sipariş no: {$order->order_no}",
        ];
        $email = $order->email;
        $mailer = $this->mailer;

        dispatch(fn () => $mailer->send($email, $subject, $content))->afterResponse();
    }
}
