<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" title="Klasörden ürün ekle">
    <x-page-header title="Klasörden ürün ekle" subtitle="Ürünlerinizi klasörlere yerleştirin, klasörü bırakın: ürün adı, fiyatı, barkodu, kategorisi, markası, seçenekleri ve resimleri kendiliğinden eklenir.">
        <x-slot:breadcrumb><a href="{{ route('store_admin.products.index') }}" class="hover:underline">Ürünler</a></x-slot:breadcrumb>
    </x-page-header>

    <div class="space-y-6">
        @include('partials.folder-import-guide', ['maxImages' => $maxImages, 'guideUrl' => route('store_admin.products.folder.guide')])
        @include('partials.folder-import', [
            'config' => [
                'mode' => 'store',
                'urls' => ['lookup' => route('store_admin.products.folder.lookup'), 'prepare' => route('store_admin.products.folder.prepare'), 'store' => route('store_admin.products.folder.store')],
                'csrf' => csrf_token(),
                'maxImages' => $maxImages,
                'maxDepth' => $maxDepth,
                'defaultTax' => '20',
            ],
            'taxOptions' => collect($vatRates)->mapWithKeys(fn ($r) => [$r => '%'.$r])->all(),
            'productsUrl' => route('store_admin.products.index'),
            'activateLabel' => 'Ürünler hemen yayına alınsın',
            'includesTaxToggle' => false,
        ])
    </div>
</x-layouts.panel>
