{{--
    Sesli yeni sipariş alarmı (mağaza yönetimi, her sayfanın üstünde yapışık şerit). Panel 15 saniyede bir sorar;
    görülmemiş yeni sipariş varken şerit yanıp söner, alarm sesi çalar, sekme başlığı yanıp söner. Yönetici Siparişler
    sayfasını açınca susar (NewOrderAlerts). Tarayıcı, sayfaya bir kez tıklanmadan ses çaldırmaz; o durumda
    "Sesi aç" düğmesi görünür. Ayar: Mağaza Ayarları → Yönetici bildirimleri → Sesli sipariş alarmı.
--}}
@props(['admin'])
@if (\App\Models\Store\StoreSetting::flag('order_alarm'))
    @php
        $alarm = [
            'url' => route('store_admin.orders.alarm'),
            'ordersUrl' => route('store_admin.orders.index'),
            'state' => app(\App\Domain\Store\Orders\NewOrderAlerts::class)->pending($admin),
        ];
    @endphp
    <div x-data="orderAlarm(@alpine($alarm))" class="sticky top-0 z-40 print:hidden">
        <div x-cloak x-show="active" x-transition.opacity role="alert" aria-live="assertive"
            class="order-alarm flex flex-wrap items-center gap-x-4 gap-y-3 bg-linear-to-r from-red-600 via-orange-500 to-red-600 px-4 py-3 text-white shadow-lg shadow-red-900/30 sm:px-6">
            <span class="order-alarm-bell flex size-10 shrink-0 items-center justify-center rounded-full bg-white/20 text-xl ring-2 ring-white/60" aria-hidden="true">🔔</span>
            <div class="min-w-0 flex-1">
                <p class="font-display text-base font-extrabold tracking-tight sm:text-lg" x-text="heading"></p>
                <p class="truncate text-sm text-white/90" x-show="latest" x-text="latest"></p>
            </div>
            <button type="button" x-show="blocked" @click="enableSound()" class="inline-flex h-10 items-center gap-2 rounded-xl bg-white/15 px-4 text-sm font-bold ring-1 ring-white/70 hover:bg-white/25">🔊 Sesi aç</button>
            <a href="{{ $alarm['ordersUrl'] }}" class="inline-flex h-10 items-center gap-2 rounded-xl bg-[#fff] px-4 text-sm font-bold text-red-700 shadow hover:bg-red-50">Siparişlere git <x-icon name="arrow-right" class="size-4" /></a>
        </div>
    </div>
@endif
