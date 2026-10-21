<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" title="İşlem Geçmişi">
    <x-page-header title="İşlem Geçmişi" subtitle="Mağaza yönetiminde kim, ne zaman, ne yaptı. Kayıtlar değiştirilemez ve silinemez." />

    <x-card :padding="false">
        <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-line p-4">
            <x-field name="admin" label="Yönetici" :options="['' => 'Tümü'] + $admins->pluck('name', 'id')->all()" :value="$filters['admin'] ?? ''" class="w-64" />
            <x-button variant="secondary" icon="filter">Filtrele</x-button>
        </form>
        @include('store.admin.partials.activity-table', ['logs' => $logs, 'names' => $names])
        @if ($logs->hasPages())<div class="border-t border-line p-4">{{ $logs->links() }}</div>@endif
    </x-card>
</x-layouts.panel>
