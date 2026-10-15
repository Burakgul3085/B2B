<x-layouts.panel :area="\App\Domain\Auth\Area::Platform" title="Denetim Kayıtları">
    <x-page-header title="Denetim Kayıtları" subtitle="Platform yöneticilerinin yaptığı işlemler ve platform girişleri." />

    <x-card :padding="false">
        <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-line p-4">
            <x-field name="action" label="İşlem" :options="['' => 'Tümü', 'auth.' => 'Giriş ve güvenlik', 'platform.' => 'İşletme yönetimi']" :value="$filters['action'] ?? ''" class="w-56" />
            <x-field name="business_id" label="İşletme" :options="['' => 'Tümü'] + $businesses->all()" :value="$filters['business_id'] ?? ''" class="w-64" />
            <x-button variant="secondary">Filtrele</x-button>
        </form>
        @include('platform.partials.audit-table', ['logs' => $logs])
        @if ($logs->hasPages())<div class="border-t border-line p-4">{{ $logs->links() }}</div>@endif
    </x-card>
</x-layouts.panel>
