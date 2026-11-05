@php
    $default = $taxRates->firstWhere(fn ($t) => bccomp((string) $t->rate, '20', 2) === 0) ?? $taxRates->first();
@endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::Business" title="Klasörden ürün ekle">
    <x-page-header title="Klasörden ürün ekle" subtitle="Ürünlerinizi klasörlere yerleştirin, klasörü bırakın: ürün adı, fiyatı, barkodu, kategorisi, markası ve resimleri kendiliğinden eklenir.">
        <x-slot:breadcrumb><a href="{{ route('business.products.index') }}" class="hover:underline">Ürünler</a></x-slot:breadcrumb>
    </x-page-header>

    @if ($taxRates->isEmpty())
        <div role="alert" class="rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">Ürün eklemek için önce Ayarlar › KDV Oranları sayfasından en az bir KDV oranı tanımlayın.</div>
    @else
        <div class="space-y-6">
            @include('partials.folder-import-guide', ['maxImages' => $maxImages, 'guideUrl' => route('business.products.folder.guide')])
            @include('partials.folder-import', [
                'config' => [
                    'mode' => 'b2b',
                    'urls' => ['lookup' => route('business.products.folder.lookup'), 'prepare' => route('business.products.folder.prepare'), 'store' => route('business.products.folder.store')],
                    'csrf' => csrf_token(),
                    'maxImages' => $maxImages,
                    'maxDepth' => \App\Domain\Catalog\FolderImport::MAX_DEPTH,
                    'defaultTax' => (string) $default->id,
                ],
                'taxOptions' => $taxRates->mapWithKeys(fn ($t) => [(string) $t->id => $t->name])->all(),
                'productsUrl' => route('business.products.index'),
                'activateLabel' => 'Ürünler hemen satışa açılsın',
                'includesTaxToggle' => true,
            ])
        </div>
    @endif
</x-layouts.panel>
