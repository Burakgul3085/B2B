<?php

namespace App\Domain\Ordering;

use App\Domain\Support\Money;
use App\Models\BusinessSetting;
use App\Models\BusinessUser;
use App\Models\Order;

/**
 * Toptancı panelindeki sesli yeni sipariş alarmı: toptancının Siparişler sayfasını son açışından sonra bayi siparişi
 * geldiyse panelin her sayfasında çalar; Siparişler sayfası açılınca susar. Ayar: Ayarlar → order_alarm.
 * Sayım işletme kapsamındadır (BelongsToBusiness); başka işletmenin siparişi sayılmaz.
 */
class NewOrderAlerts
{
    /** @return array{count: int, latest: ?string} toptancının henüz görmediği yeni bayi siparişleri */
    public function pending(BusinessUser $user): array
    {
        if (! BusinessSetting::where('business_id', $user->business_id)->value('order_alarm')) {
            return ['count' => 0, 'latest' => null];
        }
        $query = Order::where('created_at', '>=', $user->orders_seen_at ?? $user->created_at);
        $count = (clone $query)->count();
        $latest = $count ? $query->with('relationship.dealer:id,legal_name')->latest('created_at')->latest('id')->first() : null;

        return ['count' => $count, 'latest' => $latest
            ? $latest->order_no.' · '.Money::format($latest->grand_total).' · '.$latest->relationship->dealer->legal_name
            : null];
    }

    /** Siparişler sayfası açıldı: o ana kadar gelen siparişler görüldü sayılır, alarm susar. */
    public function acknowledge(BusinessUser $user): void
    {
        $user->forceFill(['orders_seen_at' => now()])->saveQuietly();
    }
}
