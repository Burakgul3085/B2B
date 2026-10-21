@php
    // Yol haritası: docs/13-magaza-mimarisi.md §13. Her aşama tamamlandıkça buradan kalkar ve menüye eklenir.
    $upcoming = [
        ['cart', 'Siparişler ve ödeme', 'iyzico, kapıda ödeme (Eskişehir) ve SMS doğrulama'],
        ['map-pin', 'Teslimat ve fatura', 'Kargo, kendi teslimatınız, iade ve e-Arşiv (Uyumsoft)'],
        ['percent', 'Kampanya ve raporlar', 'Kupon, kampanya ve satış raporları'],
    ];
@endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" title="Genel Bakış">
    <x-page-header :title="'Hoş geldiniz, '.$admin->name" :subtitle="$admin->roleLabel().' · '.config('store.name').' mağaza yönetimi'" />

    @if (! $admin->hasTwoFactorEnabled())
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-warning-soft px-5 py-4 text-sm">
            <p class="flex items-center gap-2 text-amber-900"><x-icon name="shield" class="size-4" /> Hesabınızı korumak için iki adımlı doğrulamayı açmanızı öneririz.</p>
            <x-button :href="route('store_admin.two-factor.setup')" variant="secondary" size="sm">Şimdi kur</x-button>
        </div>
    @endif

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
        <x-card title="Yakında bu panelde" description="Mağaza modülleri aşama aşama eklenecek." class="lg:col-span-2">
            <ul class="space-y-4">
                @foreach ($upcoming as [$icon, $title, $text])
                    <li class="flex gap-3">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-violet-50 text-violet-700"><x-icon :name="$icon" class="size-4" /></span>
                        <div><p class="text-sm font-medium text-ink">{{ $title }}</p><p class="text-xs text-muted">{{ $text }}</p></div>
                    </li>
                @endforeach
            </ul>
        </x-card>

        <x-card title="Son işlemler" :padding="false" class="lg:col-span-3">
            <x-slot:actions><a href="{{ route('store_admin.activity') }}" class="text-sm font-medium text-primary hover:underline">Tümü</a></x-slot:actions>
            @include('store.admin.partials.activity-table', ['logs' => $recent, 'names' => $names])
        </x-card>
    </div>
</x-layouts.panel>
