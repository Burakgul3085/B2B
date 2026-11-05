<x-layouts.panel :area="\App\Domain\Auth\Area::Business" title="Klasör hazırlama kılavuzu">
    <x-page-header title="Klasör hazırlama kılavuzu" subtitle="Ürünlerinizi klasörden eklemek için klasörlerinizi nasıl hazırlayacağınız, adım adım ve örneklerle.">
        <x-slot:breadcrumb><a href="{{ route('business.products.index') }}" class="hover:underline">Ürünler</a> › <a href="{{ route('business.products.folder') }}" class="hover:underline">Klasörden ekle</a></x-slot:breadcrumb>
    </x-page-header>

    @include('partials.folder-import-manual', ['mode' => 'b2b', 'maxImages' => $maxImages, 'importUrl' => route('business.products.folder')])
</x-layouts.panel>
