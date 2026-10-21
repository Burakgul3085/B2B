<x-layouts.panel :area="\App\Domain\Auth\Area::Platform" :title="$target->name">
    <x-page-header :title="$target->name" :subtitle="$target->roleLabel().' · '.$target->email.' · '.($target->is_active ? 'Etkin' : 'Pasif')">
        <x-slot:breadcrumb><a href="{{ route('platform.store-admins.index') }}" class="hover:underline">Mağaza Yöneticileri</a></x-slot:breadcrumb>
    </x-page-header>

    @error('admin')<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</div>@enderror

    <p class="mb-5 text-sm text-muted">Giriş adresi: <a href="{{ route('store_admin.login') }}" class="font-medium text-primary hover:underline" target="_blank" rel="noopener">{{ route('store_admin.login') }}</a></p>

    @include('store.admin.partials.admin-forms', [
        'target' => $target,
        'routes' => [
            'update' => route('platform.store-admins.update', $target),
            'password' => route('platform.store-admins.password', $target),
            'status' => route('platform.store-admins.status', $target),
            'twoFactor' => route('platform.store-admins.two-factor', $target),
        ],
    ])
</x-layouts.panel>
