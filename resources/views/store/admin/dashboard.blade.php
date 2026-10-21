@php
    // Yol haritası: docs/13-magaza-mimarisi.md §13. Her aşama tamamlandıkça buradan kalkar ve menüye eklenir.
    $upcoming = [
        ['package', 'Katalog', 'Kategori, marka, ürün, varyant, görsel ve fiyatlar'],
        ['layers', 'Stok', 'Mal girişi, sayım ve kritik stok uyarıları'],
        ['image', 'Vitrin', 'Ana sayfa, banner\'lar, kategori ve ürün sayfaları'],
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

    <div class="grid gap-4 sm:grid-cols-3">
        <x-kpi label="Etkin yönetici" :value="$activeAdmins" :href="$admin->is_super ? route('store_admin.admins.index') : null" :hint="$admin->is_super ? 'Yöneticileri yönet' : null" />
        <div class="rounded-xl border border-line bg-white p-5 shadow-xs sm:col-span-2">
            <p class="text-sm font-medium text-muted">Mağaza adresi</p>
            <p class="mt-2 truncate text-xl font-semibold tracking-tight text-ink">{{ config('store.domain') }}</p>
            <p class="mt-1 text-xs text-muted">Mağaza henüz yayında değil; ziyaretçiler "yakında" sayfasını görür.</p>
        </div>
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
