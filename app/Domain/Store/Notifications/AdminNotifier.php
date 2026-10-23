<?php

namespace App\Domain\Store\Notifications;

use App\Domain\Mail\SystemMailer;
use App\Domain\Support\Money;
use App\Models\Store\StoreAdmin;
use App\Models\Store\StoreOrder;
use App\Models\Store\StoreProductVariant;
use App\Models\Store\StoreReturn;
use App\Models\Store\StoreSetting;
use App\Models\Store\StoreStockMovement;
use Illuminate\Support\Collection;

/**
 * Mağaza yöneticilerine e-posta bildirimleri (docs/21-magaza-m8-raporu.md §3): yeni sipariş, yeni iade talebi,
 * kritik stok. Alıcılar Mağaza Ayarları'ndaki listedir; liste boşsa etkin tüm mağaza yöneticileri.
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

    /** @param  list<array{0: string, 1: string}>  $rows */
    private function send(string $subject, string $title, string $intro, array $rows, string $url, string $label): void
    {
        $recipients = $this->recipients();
        $content = ['brand' => config('store.name').' · Mağaza yönetimi', 'title' => $title, 'intro' => $intro, 'rows' => $rows,
            'action_url' => $url, 'action_label' => $label, 'outro' => 'Bu bildirimleri Mağaza Ayarları → Yönetici bildirimleri bölümünden değiştirebilirsiniz.'];
        dispatch(function () use ($recipients, $subject, $content) {
            foreach ($recipients as $to) {
                app(SystemMailer::class)->send($to, $subject, $content);
            }
        })->afterResponse();
    }
}
