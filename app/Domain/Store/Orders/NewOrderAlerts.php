<?php

namespace App\Domain\Store\Orders;

use App\Domain\Support\Money;
use App\Models\Store\StoreAdmin;
use App\Models\Store\StoreOrder;
use App\Models\Store\StoreSetting;

/**
 * Yönetim panelindeki sesli sipariş alarmı. Sipariş yeni sipariş olarak duyurulduğunda (kartla ödendi, kapıda ödeme
 * veya havale siparişi oluştu) işaretlenir; alarm, yöneticinin Siparişler sayfasını son açışından sonra duyurulan
 * sipariş varken panelin her sayfasında çalar. Her yönetici için ayrı tutulur. Ayar: Mağaza Ayarları → order_alarm.
 */
class NewOrderAlerts
{
    public function raise(StoreOrder $order): void
    {
        StoreOrder::whereKey($order->id)->whereNull('alerted_at')->update(['alerted_at' => now()]);
    }

    /** @return array{count: int, latest: ?string} yöneticinin henüz görmediği yeni siparişler */
    public function pending(StoreAdmin $admin): array
    {
        if (! StoreSetting::flag('order_alarm')) {
            return ['count' => 0, 'latest' => null];
        }
        $query = StoreOrder::where('alerted_at', '>=', $admin->orders_seen_at ?? $admin->created_at);
        $count = (clone $query)->count();
        $latest = $count ? $query->latest('alerted_at')->first(['order_no', 'total', 'shipping_name']) : null;

        return ['count' => $count, 'latest' => $latest ? $latest->order_no.' · '.Money::format($latest->total).' · '.$latest->shipping_name : null];
    }

    /** Siparişler sayfası açıldı: o ana kadar duyurulan siparişler görüldü sayılır, alarm susar. */
    public function acknowledge(StoreAdmin $admin): void
    {
        $admin->forceFill(['orders_seen_at' => now()])->saveQuietly();
    }
}
