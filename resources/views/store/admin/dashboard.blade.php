@php use App\Domain\Support\Money; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" title="Genel Bakış">
    <x-page-header :title="'Hoş geldiniz, '.$admin->name" :subtitle="$admin->roleLabel().' · '.config('store.name').' mağaza yönetimi'" />

    @php $n = fn (int $v) => number_format($v, 0, ',', '.'); $v = $visitors; @endphp
    <div class="mb-1 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <x-kpi label="Şu an sitede" :value="$n($v['online'])" :hint="'Son '.\App\Domain\Store\Visitors\VisitorCounter::ONLINE_MINUTES.' dakikada sayfa açan'" />
        <x-kpi label="Bugün ziyaretçi" :value="$n($v['today']['visitors'])" :hint="$n($v['today']['views']).' sayfa görüntüleme'" />
        <x-kpi label="Dün ziyaretçi" :value="$n($v['yesterday']['visitors'])" :hint="$n($v['yesterday']['views']).' sayfa görüntüleme'" />
        <x-kpi label="Bu ay ziyaretçi" :value="$n($v['month']['visitors'])" :hint="$n($v['month']['views']).' sayfa görüntüleme'" />
        <x-kpi label="Toplam ziyaretçi" :value="$n($v['total']['visitors'])" :hint="$n($v['total']['views']).' sayfa görüntüleme'" />
    </div>
    <p class="mb-6 text-xs text-muted">Bir kişi bir gün içinde kaç sayfa açarsa açsın bir kez sayılır; botlar sayılmaz.@if ($v['since']) Sayım {{ \Illuminate\Support\Carbon::parse($v['since'])->format('d.m.Y') }} tarihinde başladı.@endif</p>

    <div class="mb-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-kpi label="Bugün sipariş" :value="$today['orders']" :hint="Money::format($today['gross'])" :href="route('store_admin.orders.index')" />
        <x-kpi label="Bu ay net satış" :value="Money::format($month['net'])" :hint="$month['orders'].' sipariş · ortalama '.Money::format($month['average'])" :href="route('store_admin.reports.index')" />
        <x-kpi label="Bu ay indirim" :value="Money::format($month['discounts'])" hint="Kupon ve kampanyalar" :href="route('store_admin.discounts.index')" />
        <x-kpi label="Bu ay iade" :value="Money::format($month['refunds_in_period'])" hint="Yapılan para iadeleri" :href="route('store_admin.returns.index')" />
    </div>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-kpi label="Yayındaki ürün" :value="$stats['published']" :hint="$stats['products'].' ürünün'" :href="route('store_admin.products.index', ['status' => 'published'])" />
        <x-kpi label="Kritik stok" :value="$stats['critical']" :href="route('store_admin.stock.index', ['status' => 'critical'])" hint="Listele" />
        <x-kpi label="Stokta yok (satışta)" :value="$stats['out']" :href="route('store_admin.stock.index', ['status' => 'out'])" hint="Listele" />
        <x-kpi label="Etkin yönetici" :value="$activeAdmins" :href="$admin->is_super ? route('store_admin.admins.index') : null" :hint="$admin->is_super ? 'Yöneticileri yönet' : null" />
    </div>
    <div class="mt-4 flex flex-wrap items-center gap-3 rounded-xl border border-line bg-white px-5 py-3 text-sm shadow-xs">
        @if (\App\Models\Store\StoreSetting::isOpen())
            <x-badge tone="success">Mağaza açık</x-badge>
            <a href="{{ route('store.home') }}" target="_blank" rel="noopener" class="font-medium text-primary hover:underline">{{ config('store.domain') }}</a>
        @else
            <x-badge tone="warning">Mağaza kapalı</x-badge>
            <span class="text-muted">Ziyaretçiler "yakında" sayfasını görüyor.</span>
            <a href="{{ \App\Http\Controllers\Store\Admin\SettingsController::previewUrl() }}" target="_blank" rel="noopener" class="font-medium text-primary hover:underline">Mağazayı önizle</a>
        @endif
        <a href="{{ route('store_admin.settings.edit') }}" class="ml-auto text-muted hover:underline">Yayın ayarları</a>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-5">
        <x-card title="Bekleyen işler" :padding="false" class="lg:col-span-2">
            <ul class="divide-y divide-line text-sm">
                @foreach ($waiting as [$label, $count, $url])
                    <li><a href="{{ $url }}" class="flex items-center justify-between px-5 py-3 hover:bg-slate-50">
                        <span @class(['text-muted' => $count === 0, 'font-medium text-ink' => $count > 0])>{{ $label }}</span>
                        <span @class(['rounded-full px-2 py-0.5 text-xs font-semibold tabular-nums', 'bg-amber-100 text-amber-900' => $count > 0, 'text-muted' => $count === 0])>{{ $count }}</span>
                    </a></li>
                @endforeach
            </ul>
        </x-card>

        <x-card title="Son işlemler" :padding="false" class="lg:col-span-3">
            <x-slot:actions><a href="{{ route('store_admin.activity') }}" class="text-sm font-medium text-primary hover:underline">Tümü</a></x-slot:actions>
            @include('store.admin.partials.activity-table', ['logs' => $recent, 'names' => $names])
        </x-card>
    </div>
</x-layouts.panel>
