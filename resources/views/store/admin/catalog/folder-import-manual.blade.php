<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" title="Klasör hazırlama kılavuzu">
    <x-page-header title="Klasör hazırlama kılavuzu" subtitle="Ürünlerinizi klasörden eklemek için klasörlerinizi nasıl hazırlayacağınız, adım adım ve örneklerle.">
        <x-slot:breadcrumb><a href="{{ route('store_admin.products.index') }}" class="hover:underline">Ürünler</a> › <a href="{{ route('store_admin.products.folder') }}" class="hover:underline">Klasörden ekle</a></x-slot:breadcrumb>
    </x-page-header>

    @include('partials.folder-import-manual', ['mode' => 'store', 'maxImages' => $maxImages, 'importUrl' => route('store_admin.products.folder')])
</x-layouts.panel>
