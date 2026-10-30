@php $super = (bool) auth('store_admin')->user()?->is_super; @endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" title="İşlem Geçmişi">
    <x-page-header title="İşlem Geçmişi" :subtitle="$super ? 'Mağaza yönetiminde kim, ne zaman, ne yaptı. Kayıtlar değiştirilemez; yalnızca süper admin silebilir ve her silme kayda geçer.' : 'Mağaza yönetiminde kim, ne zaman, ne yaptı. Kayıtlar değiştirilemez.'" />

    <x-card :padding="false">
        <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-line p-4">
            <x-field name="admin" label="Yönetici" :options="['' => 'Tümü'] + $admins->pluck('name', 'id')->all()" :value="$filters['admin'] ?? ''" class="w-64" />
            <x-button variant="secondary" icon="filter">Filtrele</x-button>
        </form>
        @if ($super && $logs->isNotEmpty())
            <form method="POST" action="{{ route('store_admin.activity.destroy-many') }}" x-data="pickList" data-confirm="Seçilen kayıtlar kalıcı olarak silinecek. Devam edilsin mi?">
                @csrf
                @method('DELETE')
                @include('store.admin.partials.activity-table', ['logs' => $logs, 'names' => $names, 'selectable' => true])
                <div class="flex flex-wrap items-center gap-3 border-t border-line p-4">
                    <x-button variant="danger" icon="trash" x-bind:disabled="none">Seçilenleri sil</x-button>
                    <span class="text-sm text-muted" x-show="! none"><span x-text="count"></span> kayıt seçildi</span>
                </div>
            </form>
            @foreach ($logs as $log)
                <form id="kayit-sil-{{ $log->id }}" method="POST" action="{{ route('store_admin.activity.destroy-many') }}" data-confirm="Bu kayıt kalıcı olarak silinecek. Devam edilsin mi?" class="hidden">@csrf @method('DELETE')<input type="hidden" name="logs[]" value="{{ $log->id }}"></form>
            @endforeach
        @else
            @include('store.admin.partials.activity-table', ['logs' => $logs, 'names' => $names])
        @endif
        @if ($logs->hasPages())<div class="border-t border-line p-4">{{ $logs->links() }}</div>@endif
    </x-card>

    @if ($super && $logs->isNotEmpty())
        <form method="POST" action="{{ route('store_admin.activity.destroy-all') }}" class="mt-6 flex flex-wrap items-center gap-3 rounded-xl border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900" data-confirm="Mağaza işlem geçmişinin TAMAMI kalıcı olarak silinecek. Devam edilsin mi?">
            @csrf
            @method('DELETE')
            <label class="flex items-center gap-2"><input type="checkbox" name="confirm" value="1" class="size-4 rounded border-line text-danger"> Tüm işlem geçmişini silmek istiyorum</label>
            <x-button variant="danger" size="sm" icon="trash">Tümünü sil</x-button>
            @error('confirm')<p class="w-full text-danger">{{ $message }}</p>@enderror
        </form>
    @endif
</x-layouts.panel>
