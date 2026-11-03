{{--
    Toptancı panelinde sesli yeni bayi siparişi alarmı (her sayfanın üstünde yapışık şerit; mağaza yönetimindeki alarmın
    B2B karşılığı). Panel 15 saniyede bir sorar; görülmemiş sipariş varken şerit yanıp söner, alarm çalar, sekme başlığı
    yanıp söner. Toptancı Siparişler sayfasını açınca susar (App\Domain\Ordering\NewOrderAlerts). Tarayıcı, sayfaya bir kez
    tıklanmadan ses çaldırmaz; o durumda "Sesi aç" görünür. Ayar: Ayarlar → Sesli sipariş alarmı.
--}}
@props(['user'])
@if (\App\Models\BusinessSetting::where('business_id', $user->business_id)->value('order_alarm'))
    @php
        $alarm = [
            'url' => route('business.orders.alarm'),
            'ordersUrl' => route('business.orders.index'),
            'state' => app(\App\Domain\Ordering\NewOrderAlerts::class)->pending($user),
            'noun' => 'bayi siparişi',
            'tag' => 'bayi-siparis',
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
