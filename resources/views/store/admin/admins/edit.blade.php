<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" :title="$target->name">
    <x-page-header :title="$target->name" :subtitle="$target->roleLabel().' · '.($target->is_active ? 'Etkin' : 'Pasif')">
        <x-slot:breadcrumb><a href="{{ route('store_admin.admins.index') }}" class="hover:underline">Yöneticiler</a></x-slot:breadcrumb>
    </x-page-header>

    @error('admin')<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror

    @include('store.admin.partials.admin-forms', [
        'target' => $target,
        'routes' => [
            'update' => route('store_admin.admins.update', $target),
            'password' => route('store_admin.admins.password', $target),
            'status' => route('store_admin.admins.status', $target),
        ],
    ])
</x-layouts.panel>
