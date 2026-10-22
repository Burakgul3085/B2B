<?php

namespace App\Domain\Store\Orders;

use App\Domain\Mail\SystemMailer;
use App\Domain\Support\Money;
use App\Models\Store\StoreInvoice;
use App\Models\Store\StoreLegalDocument;
use App\Models\Store\StoreOrder;
use App\Models\Store\StoreReturn;

/**
 * Müşteriye sipariş e-postaları (sistem e-posta hesabıyla). Gönderim yanıt döndükten sonra yapılır;
 * e-posta sunucusundaki gecikme veya hata siparişi etkilemez.
 */
class OrderMailer
{
    public function placed(StoreOrder $order): void
    {
        $intro = $order->payment_method === 'cod'
            ? ($order->status === 'awaiting_approval'
                ? 'Siparişinizi aldık. Kısa süre içinde sizi arayarak teyit edeceğiz; ödemeyi teslimatta yapacaksınız.'
                : 'Siparişinizi aldık ve hazırlamaya başladık. Ödemeyi teslimatta yapacaksınız.')
            : 'Ödemeniz alındı, siparişinizi hazırlamaya başladık.';

        // Mesafeli Sözleşmeler Yönetmeliği: onaylanan Ön Bilgilendirme Formu ve Mesafeli Satış Sözleşmesi tüketiciye kalıcı
        // veri saklayıcısıyla (e-posta) iletilir; siparişte saklanan, onaylandığı sürümdeki doldurulmuş metinler gönderilir.
        $documents = $order->consents()->get()->map(fn ($c) => [
            'title' => (StoreLegalDocument::KEYS[$c->document_key] ?? $c->document_key)." (sürüm {$c->version})",
            'html' => (string) LegalDocuments::html($c->body),
        ])->all();

        $this->send($order, "Siparişiniz alındı — {$order->order_no}", 'Siparişiniz için teşekkürler',
            $intro.($documents ? ' Onayladığınız sözleşmeler bu e-postanın altındadır; saklamanızı öneririz.' : ''), true, null, $documents);
    }

    public function shipped(StoreOrder $order): void
    {
        $intro = $order->delivery_method === 'cargo'
            ? "Siparişiniz kargoya verildi. Kargo firması: {$order->carrier}, takip numarası: {$order->tracking_number}."
            : 'Siparişiniz teslimat için yola çıktı.';

        $this->send($order, "Siparişiniz yolda — {$order->order_no}", 'Siparişiniz yolda', $intro, false,
            $order->tracking_url ? [$order->tracking_url, 'Kargoyu takip et'] : null);
    }

    public function cancelled(StoreOrder $order): void
    {
        $refund = $order->payment_status === 'refunded'
            ? ' Ödemeniz kartınıza iade edildi; tutarın hesabınıza yansıması bankanıza göre birkaç gün sürebilir.'
            : '';
        $this->send($order, "Siparişiniz iptal edildi — {$order->order_no}", 'Siparişiniz iptal edildi',
            "{$order->order_no} numaralı siparişiniz iptal edildi.{$refund} Sorularınız için bize ulaşabilirsiniz.", false);
    }

    public function refunded(StoreOrder $order, string $amount, string $reason): void
    {
        $where = $order->payment_method === 'card'
            ? 'ödemede kullandığınız karta gönderildi; hesabınıza yansıması bankanıza göre birkaç gün sürebilir'
            : 'banka hesabınıza gönderildi';
        $this->send($order, "Para iadesi — {$order->order_no}", 'Para iadeniz yapıldı',
            "{$order->order_no} numaralı siparişiniz için ".Money::format($amount)." tutarındaki iadeniz {$where}. Açıklama: {$reason}", false);
    }

    public function returnUpdate(StoreOrder $order, StoreReturn $return, string $title, string $intro): void
    {
        $this->send($order, "{$title} — {$return->return_no}", $title, $intro, false);
    }

    public function invoiceReady(StoreOrder $order, StoreInvoice $invoice): void
    {
        $this->send($order, "Faturanız hazır — {$order->order_no}", 'Faturanız hazır',
            "{$order->order_no} numaralı siparişinizin faturası ({$invoice->invoice_no}) hazır. Sipariş sayfasından indirebilirsiniz.", false);
    }

    /** @param  array{0: string, 1: string}|null  $action  ek bağlantı (adres, etiket); yoksa sipariş sayfası */
    private function send(StoreOrder $order, string $subject, string $title, string $intro, bool $withItems, ?array $action = null, array $documents = []): void
    {
        $rows = [];
        if ($withItems) {
            foreach ($order->items()->get() as $item) {
                $rows[] = [$item->product_name.($item->option_label ? " ({$item->option_label})" : '')." × {$item->quantity}", Money::format($item->line_total)];
            }
            if (bccomp((string) $order->discount_total, '0', 2) > 0) {
                $rows[] = [(string) $order->discount_name, '−'.Money::format($order->discount_total)];
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
            'action_url' => $action[0] ?? route('store.order', ['orderNo' => $order->order_no, 'token' => $order->access_token]),
            'action_label' => $action[1] ?? 'Siparişi görüntüle',
            'outro' => "Sipariş no: {$order->order_no}",
            'documents' => $documents,
        ];
        $email = $order->email;

        dispatch(fn () => app(SystemMailer::class)->send($email, $subject, $content))->afterResponse();
    }
}
